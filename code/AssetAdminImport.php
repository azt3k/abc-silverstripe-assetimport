<?php

class AssetAdminImport extends LeftAndMainExtension {

    private static $allowed_actions = array(
        'import',
        'export',
        'ImportForm'
    );

    /**
     * @param Form $form
     * @return Form $form
     */
    public function updateEditForm(Form $form) {

        $importButton = new LiteralField(
            'ImportButton',
            '<a id="import-assets-btn"
                class="ss-ui-button ss-ui-action ui-button-text-icon-primary"
                data-icon="arrow-circle-135-left"
                title="Import Files"
                href="' . $this->owner->Link('import') . '?ID=' . $this->owner->currentPageID() . '">Import backup</a>'
        );

        $exportButton = new LiteralField(
            'ExportButton',
            sprintf(
                '<a class="ss-ui-button ss-ui-action ui-button-text-icon-primary" data-icon="arrow-circle-135-left" title="%s" href="%s">%s</a>',
                'Performs an asset export in ZIP format. Useful if you want all assets and have no FTP access',
                $this->owner->Link('export') . '?ID=' . $this->owner->currentPageID(),
                'Export backup'
            )
        );

        if($field = $this->fieldByExtraClass($form->Fields(), 'cms-actions-row')) {
            $field->push($exportButton);
            $field->push($importButton);
        }

        return $form;
    }

    /**
     * Recursively search & return a field by 'extra class' from FieldList.
     *
     * @todo Could be added as a FieldList extension but it's a bit overkill for the sake of a button
     *
     * @param FieldList $fields
     * @param $class The extra class name to search for
     * @return FormField|null
     */
    public function fieldByExtraClass(FieldList $fields, $class) {
        foreach($fields as $field)  {
            if($field->extraClasses && in_array($class, $field->extraClasses)) {
                return $field;
            }
            if($field->isComposite()) {
                return $this->fieldByExtraClass($field->FieldList(), $class);
            }
        }
    }

    /**
     * @return CMSForm
     */
    public function ImportForm() {
        Requirements::javascript(FRAMEWORK_DIR . '/javascript/AssetUploadField.js');
        Requirements::css(FRAMEWORK_DIR . '/css/AssetUploadField.css');

        if($currentPageID = $this->owner->currentPageID()){
            Session::set("{$this->owner->class}.currentPage", $currentPageID);
        }

        $folder = $this->owner->currentPage();

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
            $this->owner,
            'ImportForm',
            new FieldList(
                $uploadField,
                new HiddenField('ID')
            ),
            new FieldList()
        )->setHTMLID('Form_EditForm');

        $form->setResponseNegotiator($this->owner->getResponseNegotiator());
        $form->addExtraClass('center cms-edit-form ' . $this->owner->BaseCSSClasses());

        // Don't use AssetAdmin_EditForm, as it assumes a different panel structure
        $form->setTemplate($this->owner->getTemplatesWithSuffix('_EditForm'));
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

        return $form;
    }

    public function getDirContents($dir, &$results = array(), $blacklist = null) {

        // get blacklist
        if (!$blacklist)
            $blacklist = Config::inst()->get('Filesystem', 'sync_blacklisted_patterns');

        // get files in current dir
        $files = scandir($dir);

        // check each file
        foreach($files as $key => $file){

            // generate full path
            $path = realpath($dir . DIRECTORY_SEPARATOR . $file);

            // init skip each iteration
            $skip = false;

            // test files against blacklist
            foreach ($blacklist as $pattern) {
                if (preg_match($pattern, $file)) {
                    $skip = true;
                    break;
                }
            }

            // add the file if it's legal
            if (!$skip) {
                if (!is_dir($path)) {
                    $results[] = $path;
                } else if (is_dir($path) && $file != "." && $file != "..") {
                    // $results[] = $path;
                    $this->getDirContents($path, $results, $blacklist);
                }
            }
        }

        // return
        return $results;
    }

    /**
     *
     * @param  SS_HTTPRequest   $request [description]
     * @return string
     */
    public function import($request) {

        $obj = $this->owner->customise(array(
            'EditForm' => $this->owner->ImportForm()
        ));

        if($request->isAjax()) {
            // Rendering is handled by template, which will call EditForm() eventually
            $content = $obj->renderWith($this->owner->getTemplatesWithSuffix('_Content'));
        } else {
            $content = $obj->renderWith($this->owner->getViewer('show'));
        }

        return $content;


    }

    /**
     * @return SS_HTTPRequest
     */
    public function export() {

        // get folder
        $folder = $this->owner->currentPage();
        $fn = ($folder->exists() && $folder->getFilename()) ? str_replace(ASSETS_DIR, '', $folder->getFilename()) : '' ;
        $path = realpath(ASSETS_PATH . $fn);

        // init zip
        $fn = preg_replace('/-+/', '-', 'assets-' . preg_replace('/[^a-zA-Z0-9]+/', '-', $fn) . '-' . SS_DateTime::now()->Format('Y-m-d') . '.zip');
        $tmpName = TEMP_FOLDER . '/' . $fn;
        $zip = new ZipArchive();

        // create zip file for writing
        if($zip->open($tmpName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            user_error('Asset Export Extension: Unable to read/write temporary zip archive', E_USER_ERROR);
            return;
        }

        // get whitelisted files
        $files = $this->getDirContents($path);

        // build zip
        foreach($files as $file) {
            $local = trim(str_replace($path, '', $file), '/');
            $zip->addFile($file, $local);
        }

        // check the status
        if(!$zip->status == ZipArchive::ER_OK) {
            user_error('Asset Export Extension: ZipArchive returned an error other than OK', E_USER_ERROR);
            return;
        }

        $zip->close();

        if(ob_get_length()) {
            @ob_flush();
            @flush();
            @ob_end_flush();
        }
        @ob_start();

        $content = file_get_contents($tmpName);
        unlink($tmpName);

        return SS_HTTPRequest::send_file($content, $fn);
    }

}
