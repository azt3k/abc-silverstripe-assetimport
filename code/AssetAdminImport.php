<?php

class AssetAdminImport extends LeftAndMainExtension {

    private static $allowed_actions = array(
        'import'
    );

    /**
     * @param Form $form
     * @return Form $form
     */
    public function updateEditForm(Form $form) {

        $script = <<<SCRIPT
            <script>
                $('#import-assests-input').on('change', function() {

                    var $this = $(this),
                        $form = $this.closest('form');

                    $form.attr('action', $this->owner->Link('import'));
                         .trigger('submit');

                });
				$('#import-assests-btn').on('click', function(e) {
					e.preventDefault();
					$('#import-assests-input').trigger('click');
				});
            </script>
        SCRIPT;

        $html = <<<HTML
            <input style="display: none" type="file" id="import-assests-file" name="import-assests-file" onChange={this.handlePhotoFromFs} />
            <a id="import-assests-btn" class="ss-ui-button ss-ui-action ui-button-text-icon-primary" data-icon="arrow-circle-135-left" title="%s" href="%s">%s</a>
        HTML;

        $importButton = new LiteralField(
            'ImportButton',
            $script . $html;
        );

        if($field = $this->fieldByExtraClass($form->Fields(), 'cms-actions-row')) {
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
     * @return SS_HTTPRequest
     */
    public function import() {
        $zip = new ZipArchive;
        $res = $zip->open($_FILES['import-assests-file']['tmp_name']);
        if ($res === TRUE) {
            $zip->extractTo(ASSETS_PATH);
            $zip->close();
            echo 'woot!';
        } else {
            echo 'doh!';
        }
    }

}
