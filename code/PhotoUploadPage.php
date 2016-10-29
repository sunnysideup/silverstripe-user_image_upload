<?php

class PhotoUploadPage extends Page
{
    private static $icon = 'mysite/images/treeicons/PhotoUploadPage';

    private static $db = array(
        'InvitationMessage' => 'HTMLText',
        'InviteButtonText' => 'Varchar(30)',
        'UploadExplanation' => 'HTMLText',
        'AlertEmail1' => 'Varchar(100)',
        'AlertEmail2' => 'Varchar(100)',
        'NumberOfImages' => 'Int',
        'ThankYouTitle' => 'Varchar(200)',
        'ThankYouMessage' => 'HTMLText',
        'FirstName_Note' => 'Varchar(255)',
        'Surname_Note' => 'Varchar(255)',
        'Email_Note' => 'Varchar(255)',
        'Image_Note' => 'Varchar(255)'
    );

    private static $description = 'Photo Upload Page';

    private static $can_be_root = true;

    private static $allow_children = 'none';

    private static $default = array(
        "NumberOfImages" => 1
    );

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        //invite section
        $fields->addFieldToTab('Root.CustomerImages', new HeaderField('Invite to upload'));
        //InvitationMessage
        $invitationMessageField = new HtmlEditorField("InvitationMessage", "Invite");
        $invitationMessageField->setRows(5);
        $invitationMessageField->setRightTitle("Invite the user to upload their images.");
        $fields->addFieldToTab('Root.CustomerImages', $invitationMessageField);
        //InviteButtonText
        $fields->addFieldToTab('Root.CustomerImages', $inviteButtonTextField = new TextField('InviteButtonText', 'Invite Button Text'));
        $inviteButtonTextField->setRightTitle("Button that the user clicks to show the form for uploading the customer images.");

        //upload section
        $fields->addFieldToTab('Root.CustomerImages', new HeaderField('Upload Explanations'));
        $uploadExplanationField = new HtmlEditorField("UploadExplanation", "Explanation");
        $uploadExplanationField->setRows(5);
        $uploadExplanationField->setRightTitle("tell the user how to enter their details and upload the image (e.g. what size image, what format, etc...)");
        $fields->addFieldToTab('Root.CustomerImages', $uploadExplanationField);
        $fields->addFieldToTab('Root.CustomerImages', new TextField("Email_Note", "Explanation about E-mail"));
        $fields->addFieldToTab('Root.CustomerImages', new TextField("Image_Note", "Explanation about Image"));

        //thank you section
        $fields->addFieldToTab('Root.CustomerImages', new HeaderField('Thank You'));
        $fields->addFieldToTab('Root.CustomerImages', new TextField("ThankYouTitle", "Title shown on thank you page."));
        $thankYouMessage = new HtmlEditorField("ThankYouMessage", "Thank you message after uploading");
        $thankYouMessage->setRows(5);
        $fields->addFieldToTab('Root.CustomerImages', $thankYouMessage);


        //settings
        $fields->addFieldToTab('Root.CustomerImages', new HeaderField('Settings'));
        $fields->addFieldToTab('Root.CustomerImages', $emailField1 = new EmailField("AlertEmail1"));
        $emailField1->setRightTitle("Alert email 1 goes to (alert emails let the website owner know that a new customer image has been uploaded)");
        $fields->addFieldToTab('Root.CustomerImages', $emailField2 = new EmailField("AlertEmail2"));
        $emailField2->setRightTitle("Alert email 2 goes to (alert emails let the website owner know that a new customer image has been uploaded)");
        $fields->addFieldToTab('Root.CustomerImages', $numberOfImagesField = new NumericField("NumberOfImages"));
        $numberOfImagesField->setRightTitle("Number of images that can be uploaded at any one time");

        //previous images
        $fields->addFieldToTab('Root.UploadedImages',
             new GridField('images', '', CustomerImage::get(), GridFieldConfig_RecordEditor::create())
        );
        return $fields;
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        $pages = PhotoUploadPage::get();
        foreach ($pages as $page) {
            $write = false;
            if (strlen($page->InvitationMessage) < 20) {
                $write = true;
                $page->InvitationMessage = '
        <p>
            Invite message
        </p>';
            }
            if (!$page->InviteButtonText) {
                $write = true;
                $page->InviteButtonText = 'Upload Now';
            }
            if ($write) {
                $page->writeToStage();
                $page->publish("Stage", "Live");
            }
        }
    }
}

class PhotoUploadPage_Controller extends Page_Controller
{
    private static $allowed_actions = array(
        "thankyou",
        "deleteimage",
        "Form",
        "ajaxform"
    );

    private static $number_of_images = 3;

    private static $images_session_name = 'Images';

    private static $customers_group = 'Customers';

    private $productID = 0;

    public function init()
    {
        parent::init();
        $this->productID = intval($this->request->param("ID"));
    }


    public function ajaxform()
    {
        return $this->renderWith("PhotoUploadPage_Ajax");
    }

    public function Form()
    {
        $settings = $this->dataRecord;
        $requiredFields = new RequiredFields('Image1');
        $fields = new FieldList();
        $required = ' <span class="required">*</span>';
        if ($this->UploadExplanation) {
            $fields->push(new LiteralField('UploadExplanation', $this->UploadExplanation));
        }
        $fields->push(new TextField('FirstName', 'Your First Name'));
        //$requiredFields->addRequiredField('FirstName');
        $fields->push(new TextField('Surname', 'Your Last Name'));
        $fields->push(new EmailField('Email', "Email$required"));
        $requiredFields->addRequiredField('Email');
        $fields->push(new TextField('Location', "Photograph Location$required"));
        //spam field
        $fields->push(new TextField('Website', "Website"));
        $product = Product::get()->byID($this->productID);
        if ($product) {
            $variations = ProductVariation::get()->filter(array("ProductID" => $product->ID))->map("ID", "Title")->toArray();
            $productDropdown = new HiddenField('ProductPageID', "Hidden Product", $product->ID);
            $variationsDropdown = new DropdownField('ProductVariationID', 'Model', $variations);
        } else {
            $products = Product::get()->map("ID", "Title")->toArray();
            $productDropdown = new DropdownField('ProductPageID', 'Product', $products);
            $variationsDropdown = new HiddenField('ProductVariationID', 0, 0);
        }
        $fields->push($productDropdown);
        $fields->push($variationsDropdown);

        $feedbackField = new TextareaField('Feedback', "Do you have any feedback or question (not published)");
        $feedbackField->setRows(5);
        $fields->push($feedbackField);

        for ($i = 1; ($i <= $this->NumberOfImages || $i == 1) && $i < 6; $i++) {
            $fields->push($field = new FileField("Image$i", ($i == 1 ? "Your Photo$required" : "Photo $i")));
            $field->setFolderName('Customer-Photos/Drafts');
            $field->getValidator()->setAllowedExtensions(array('jpg', 'gif', 'png'));
        }
        //final cleanup
        $requiredFields->addRequiredField('Image1');
        $fields->fieldByName("Email")->setRightTitle($settings->Email_Note);
        $fields->fieldByName("Image1")->setRightTitle($settings->Image_Note);
        $actions = new FieldList(
            new FormAction('upload', 'Upload')
        );
        $form = new Form($this, 'Form', $fields, $actions, $requiredFields);
        return $form;
    }

    public function upload($data, $form)
    {
        //check for spam
        if (isset($data["Website"]) && $data["Website"]) {
            $form->sessionMessage('Please dont be overzealous.', 'bad');
            $this->redirectBack();
        }
        if (!isset($data['Email'])) {
            $form->sessionMessage('Please fill in "Email", it is required.', 'bad');
            return $this->redirectBack();
        }
        if (!isset($data['Feedback'])) {
            $data['Feedback'] = 'NO FEEDBACK PROVIDED';
        }
        $customerImage = CustomerImage::create();
        $form->saveInto($customerImage);
        $customerImage->write();
        mail($this->AlertEmail1, "customer image uploaded", "customer image uploaded: FEEDBACK".$data['Feedback']);
        mail($this->AlertEmail2, "customer image uploaded", "customer image uploaded: FEEDBACK".$data['Feedback']);
        return $this->redirect($this->Link('thankyou'));
    }

    private $isThankYouContent = false;

    public function IsThankYouContent()
    {
        return $this->isThankYouContent;
    }

    public function thankyou()
    {
        $this->isThankYouContent = true;
        $this->Title = $this->ThankYouTitle;
        $this->MetaTitle = $this->ThankYouTitle;
        if (Director::is_ajax()) {
            return $this->renderWith("PhotoUploadPage_Ajax");
        } else {
            return array();
        }
    }
}
/*
class PhotoUploadPage_Uploader extends UploadField {

    public function saveInto(Member $member) {
        if(!isset($_FILES[$this->name])) return false;

        $file = new CustomerImage();

        $this->upload->loadIntoFile($_FILES[$this->name], $file, $this->folderName);
        if($this->upload->isError()) return false;

        $file = $this->upload->getFile();

        $file->OwnerID = $member->ID;
        $file->write();

        Session::add_to_array(PhotoUploadPage_Controller::$images_session_name, $file->ID);
    }
}*/
