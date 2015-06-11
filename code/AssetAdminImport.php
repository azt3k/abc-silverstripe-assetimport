<?php

class AssetAdminImport extends LeftAndMainExtension {

    /**
     * @param Form $form
     * @return Form $form
     */
    public function updateEditForm(Form $form) {

        $html = '
            <a id="import-assets-btn"
               class="ss-ui-button ss-ui-action ui-button-text-icon-primary"
               data-icon="arrow-circle-135-left"
               title="Import Files"
               href="' . $this->owner->Link('import') . '">Import backup</a>
        ';

        $importButton = new LiteralField(
            'ImportButton',
            $html
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
}
