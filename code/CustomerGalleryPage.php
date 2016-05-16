<?php

class CustomerGalleryPage extends Page {

	private static $icon = 'mysite/images/treeicons/CustomerGalleryPage';

	private static $description = 'Gallery of Customer Feedback and Images';

	private static $can_be_root = false;

	private static $allow_children = 'none';

	public function canCreate($member = null) {
		return CustomerGalleryPage::get()->count() ? true : false;
	}

	function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->removeByName('FolderID');
		$fields->removeByName('Extensions');
		return $fields;
	}


}

class CustomerGalleryPage_Controller extends Page_Controller {

	private static $allowed_actions = array(
		"show",
		"Form"
	);

	protected $filter = '', $filterType = '', $filterValue = '', $join = '';

	function init() {
		parent::init();
		PrettyPhoto::include_code();
		Requirements::javascript("mysite/javascript/MediaArticles.js");
	}

	function PhotoUploadPage() {
		return PhotoUploadPage::get()->First();
	}


	function HasFilter() {
		return $this->filter ? true : false;
	}

	function Location() {
		$countries = DB::query("SELECT DISTINCT(\"PictureLocation\") FROM \"CustomerImage\" WHERE \"CustomerImage\".\"Status\" = 'Approved' ORDER BY \"PictureLocation\" ")->keyedColumn();
		return $this->makeDos("location", $countries);
	}

	/*
	function MyPictures() {
		$mypictures = array();
		$member = Member::currentUser();
		if($member) {
			$dos = CustomerImage::get()->filter(array("Status" => 'Approved', "OwnerID" => $member->ID));
			if($dos->count()) {
				foreach($dos as $do) {
					$mypictures[$member->ID] = "My pictures (".$dos->count().")";
				}
				return $this->makeDos("mypictures", $mypictures);
			}
		}
	}
	*/

	function Product() {
		$stage = Versioned::current_stage();
		$productTable = "SiteTree";
		if($stage) {
			$productTable .= "_".$stage;
		}
		$row = DB::query("SELECT DISTINCT(\"ProductPageID\") AS ProductPageID FROM \"CustomerImage\" INNER JOIN \"$productTable\" ON \"$productTable\".\"ID\" = \"CustomerImage\".\"ProductPageID\" WHERE \"CustomerImage\".\"Status\" = 'Approved' ORDER BY \"$productTable\".\"Title\"");
		$newArray = array();
		foreach($row as $dataArray) {
			$page = SiteTree::get()->byID($dataArray["ProductPageID"]);
			if($page) {
				$key = $page->ID;
				$value = $page->Title;
			}
			$newArray[$key] = $value;
		}
		return $this->makeDos("product", $newArray);
	}

	function Years() {
		$years = DB::query("SELECT DISTINCT(YEAR(\"Created\")) FROM \"CustomerImage\" INNER JOIN \"File\" ON \"File\".\"ID\" = \"CustomerImage\".\"ID\" WHERE \"CustomerImage\".\"Status\" = 'Approved' ORDER BY \"Created\" DESC")->keyedColumn();
		return $this->makeDos("year", $years);
	}

	protected function makeDos($title, $data) {
		$dos = new ArrayList();
		//sort($data);
		if($data && count($data)) {
			foreach($data as $key => $value) {
				if($key && $value) {
					$key = urlencode(preg_replace("/[^a-zA-Z0-9\s]/", "", $key));
					$do = new DataObject();
					$do->Code = $key;
					$do->Name = $value;
					$do->LinkingMode = (($this->filterType == $title) && ($this->filterValue == $key)) ? "current" : "link";
					$do->filter = (($this->filterType == $title) && ($this->filterValue == $key)) ? "alwaysfilter" : "filter";
					$do->Link = $this->Link("show/$title/".$key."/");
					$dos->push($do);
				}
			}
		}
		if($dos->count()) {
			return $dos;
		}
		return null;
	}


	function Items($limit = 0) {
		if($this->SortBy == 'Title') {
			$sort = array("File.Title", "ASC");
		}
		else if($this->SortBy == 'UploadDate ASC') {
			$sort = array("File.Created", "ASC");
		}
		else if($this->SortBy == 'UploadDate DESC') {
			$sort = array("File.Created", "DESC");
		}
		else {
			$sort = array();
		}
		if($this->filter) {
			$this->filter .= " AND ";
		}
		$this->filter .= "\"CustomerImage\".\"Status\" = 'Approved' ";
		return CustomerImage::get()->where($this->filter)->sort($sort)->limit($limit);
	}



	function Form() {
		$fields = new FieldList(
			new TextField('Keyword', 'Keyword(s)', $this->value)
			//$date = new DateField('Date')
		);
		//$date->setConfig('showcalendar', true);
		$actions = new FieldList(new FormAction('search', 'Search'));
		return new Form($this, 'Form', $fields, $actions);
	}


	function search($data, $form) {
		user_error("Not yet updated to 3.0");
		$this->value = $data['Keyword'];
		$data = Convert::raw2sql($data);
		if($data['Keyword']) {
			$stage = Versioned::current_stage();
			$productTable = "SiteTree";
			if($stage) {
				$productTable .= "_".$stage;
			}
			$kwords = trim($data['Keyword']);
			$kwordsKWArray=split(" ",$kwords);//Breaking the string to array of words
			// Now let us generate the sql
			$kwordsFilter = array();
			while(list($key,$val) = each($kwordsKWArray)){
				$val = trim($val);
				if($val<>" " and strlen($val) > 0){
					$kwordsFilter[] = " \"Member\".\"ScreenName\" LIKE '%$val%' OR \"$productTable\".\"Title\" LIKE '%$val%' OR \"PictureLocation\" LIKE '%$val%' ";
				}
			}
			$this->filter = " (". implode( " AND ", $kwordsFilter).") ";
			$this->join .= " LEFT JOIN \"$productTable\" ON \"ProductPageID\" = \"$productTable\".\"ID\" " ;
			$this->join .= " INNER JOIN \"Member\" ON \"OwnerID\" = \"Member\".\"ID\" " ;

		}
		return array();
	}

	function show($request) {
		$action = $request->param("ID");
		$value = Convert::raw2sql($request->param("OtherID"));
		switch ($action) {
			case "year":
				$where = "Year(\"File\".\"Created\") = '$value'";
				break;
			case "location":
				$where = "\"PictureLocation\" = '$value'";
				break;
			case "product":
				$where = "\"ProductPageID\" = '$value'";
				break;
			case "mypictures":
				$where = "\"OwnerID\" = '$value'";
				break;
			default:
				$where = "";
		}
		$this->filter = $where;
		$this->filterType = $action;
		$this->filterValue = $value;
		return array();
	}


}
