<?php


class PhotoUploadPage_MemberDecorator extends DataExtension {

	private static $db = array(
		"ScreenName" => "Varchar(100)",
		"Website" => "Varchar(100)"
	);

	private static $has_many = array(
		"CustomerImages" => "CustomerImage"
	);


	function addMemberImages($additionalImages) {
		$existingImages = $this->owner->MemberImages();
		// method 1: Add many by iteration
		foreach($additionalImages as $image) {
			$existingImages->add($image);
		}
	}



}
