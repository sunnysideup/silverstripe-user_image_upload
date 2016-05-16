<?php

class CustomerImage extends DataObject {

	private static $db = array(
		'Status' => "Enum('New,Approved,Declined,Example', 'New')",
		'FirstName' => 'Varchar',
		'Email' => 'Varchar(100)',
		'Surname' => 'Varchar',
		'Location' => 'Varchar(255)',
		'Feedback' => 'Text'
	);

	private static $has_one = array(
		'ProductPage' => 'ProductPage',
		'ProductVariation' => 'ProductVariation',
		'Image1' => 'Image',
		'Image2' => 'Image',
		'Image3' => 'Image',
		'Image4' => 'Image',
		'Image5' => 'Image',
		'FinalImage' => 'Image'
	);

	private static $default_sort = '"Created" DESC';

	private static $singular_name = 'Customer Image';

	private static $plural_name = 'Customer Images';

	private static $casting = array(
		"Title" => "Varchar",
		'Thumbnail' => "HTMLText"
	);

	private static $summary_fields = array(
		"Thumbnail" => "Thumbnail",
		"Status" => "Status",
		"Location" => "Location"
	);


	/**
	 * STANDARD SILVERSTRIPE STUFF
	 * @todo: how to translate this?
	 **/
	private static $searchable_fields = array(
		'Status',
		'FirstName' => 'PartialMatchFilter',
		'Surname' => 'PartialMatchFilter',
		'Location' => 'PartialMatchFilter',
		'ProductPageID'
	);

	public Function IsPortrait() {
		if($this->getWidth() < $this->getHeight()) {
			return true;
		}
	}

	public function canView($member = null) {
		if($this->Status == "Approved") {
			return true;
		}
		return $this->canDelete($member);
	}

	function getCMSFields() {
		$fields = parent::getCMSFields();

		//variations drop down
		$variations = ProductVariation::get()->filter(array("ProductID" => $this->ProductPageID))->map("ID", "Title")->toArray();
		$fields->addFieldToTab("Root.Main", new DropdownField('ProductVariationID', 'Model', $variations), "FinalImage");

		//final image field ...
		$newImageField = new UploadField("FinalImage");
		$newImageField->setFolderName("Customer-Photos");
		$newImageField->setAllowedExtensions(array("png", "jpg", "gif"));
		$fields->addFieldToTab("Root.Main", $newImageField, "Status");
		//loop through images
		for($i = 1; $i < 6; $i++) {
			$field = "Image$i"."ID";
			$method = "Image$i";
			if($this->$field && $image = $this->$method()) {
				$imageField = new UploadField($method);
				$imageField->setFolderName("Customer-Photos/Drafts");
				$imageField->setAllowedExtensions(array("png", "jpg", "gif"));
				$fields->addFieldToTab("Root.DraftImages", $imageField);
			}
			else {
				$fields->removeByName($method);
			}
		}
		return $fields;
	}

	function getThumbnail(){
		$image = $this->FinalImage();
		if(!$image->exists()) {
			$image = $this->Image1();
		}
		if($image->exists()) {
			$icon = "<img src=\"".$image->Link()."\" style=\"border: 1px solid black; height: 150px; \" height=\"200\" />";
		}
		else {
			$icon = "[NO IMAGE]";
		}
		return DBField::create_field("HTMLText", $icon);
	}

}



class CustomerImage_ModelAdmin extends ModelAdmin {

	private static $url_segment = 'customerimages';

	private static $menu_title = 'Customer Images';

	private static $managed_models = array('CustomerImage');

}
