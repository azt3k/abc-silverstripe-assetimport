<?php
class ImportFileController extends CMSFileAddController {

    private static $url_segment = 'assets/import';

    /**
     * @param null $id Not used.
     * @param null $fields Not used.
     * @return Form
     * @todo what template is used here? AssetAdmin_UploadContent.ss doesn't seem to be used anymore
     */
    public function getEditForm($id = null, $fields = null) {

        Requirements::javascript(FRAMEWORK_DIR . '/javascript/AssetUploadField.js');
        Requirements::css(FRAMEWORK_DIR . '/css/AssetUploadField.css');

        if($currentPageID = $this->currentPageID()){
            Session::set("{$this->class}.currentPage", $currentPageID);
        }

        $folder = $this->currentPage();

        $uploadField = ImportUploadField::create('AssetUploadField', '');
        $uploadField->setConfig('previewMaxWidth', 40);
        $uploadField->setConfig('previewMaxHeight', 30);
        $uploadField->setConfig('changeDetection', false);
        $uploadField->addExtraClass('ss-assetuploadfield');
        $uploadField->removeExtraClass('ss-uploadfield');
        $uploadField->setTemplate('AssetUploadField');

        if($folder->exists() && $folder->getFilename()) {
            // The Upload class expects a folder relative *within* assets/
            $path = preg_replace('/^' . ASSETS_DIR . '\//', '', $folder->getFilename());
            $uploadField->setFolderName($path);
        } else {
            $uploadField->setFolderName('/'); // root of the assets
        }

        $exts = array('zip');
        $uploadField->Extensions = implode(', ', $exts);

        $form = CMSForm::create(
            $this,
            'EditForm',
            new FieldList(
                $uploadField,
                new HiddenField('ID')
            ),
            new FieldList()
        )->setHTMLID('Form_EditForm');
        $form->setResponseNegotiator($this->getResponseNegotiator());
        $form->addExtraClass('center cms-edit-form ' . $this->BaseCSSClasses());
        // Don't use AssetAdmin_EditForm, as it assumes a different panel structure
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
        $form->Fields()->push(
            new LiteralField(
                'BackLink',
                sprintf(
                    '<a href="%s" class="backlink ss-ui-button cms-panel-link" data-icon="back">%s</a>',
                    Controller::join_links(singleton('AssetAdmin')->Link('show'), $folder ? $folder->ID : 0),
                    _t('AssetAdmin.BackToFolder', 'Back to folder')
                )
            )
        );
        $form->loadDataFrom($folder);

        $this->extend('updateEditForm', $form);

        return $form;
    }
}
