<?php namespace ProcessWire;

class FieldtypeFormSelect extends FieldType {

  public static function getModuleInfo() {
    return [
      'title' => __('Form Select'),
      'summary' => __('Select a form created via the Pro Form Builder module', __FILE__),
      'version' => '100',
      'href' => 'https://processwire.com/talk/topic/29771-fieldtypeformselect-a-field-for-selecting-forms-built-using-the-pro-formbuilder-module/',
      'icon' => 'envelope-o',
      'autoload' => true,
      'singular' => true,
      'requires' => [
        'FormBuilder',
        'ProcessWire>=300',
        'PHP>=8.1'
      ],
      'installs' => 'InputfieldFormSelect'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function ready() {
    $this->addHooks();
  }

  /**
   * Add hooks to operations where necessary
   */
  private function addHooks(): void {
    // When a form is deleted, remove that value from any fields that may have that form selected
    $this->wire->addHookBefore('FormBuilder::delete', null, function(HookEvent $e) {
      $formSelectFields = array_values($this->fields->find("type=FieldtypeFormSelect")->getArray());

      if (!$formSelectFields) {
        return;
      }

      $fieldNames = array_map(fn($field) => $field->name, $formSelectFields);
      $deletedFormId = (int) $e->arguments(0)->id;

      $selector = array_map(fn($fieldName) => "{$fieldName}={$deletedFormId}", $fieldNames);
      $selector = implode('|', $selector);

      foreach ($this->pages->find($selector) as $foundPage) {
        $this->clearFieldValuesWithFormId($foundPage, $deletedFormId, $fieldNames);
      }
    });
  }

  /**
   * Removes the values of all form select fields with the value of the given ID on a given page
   * @param array<string> $fieldNames
   */
  public function clearFieldValuesWithFormId(
    Page $page,
    int $deletedFormId,
    array $fieldNames = []
  ): void {
    foreach ($fieldNames as $fieldName) {
      (int) $page->$fieldName === $deletedFormId && $page->set($fieldName, null) && $page->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function ___getConfigAllowContext($field) {
    return [
      'form_option_style',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function ___getConfigInputfields(Field $field) {
    $inputfields = parent::___getConfigInputfields($field);
    $forms = $this->modules->FormBuilder->forms;

    if (!$forms->count()) {
      $inputfields->add([
        'type' => 'InputfieldMarkup',
        'label' => __('Form Select Options'),
        'value' => __("There are no forms present created using Form Builder. This field will render an empty select element."),
        'themeColor' => 'warning',
      ]);

      return $inputfields;
    }

    $allForms = $forms->loadAll();

    // Create options with form name and form ID
    array_walk($allForms, fn(&$v) => $v = $v->id);

    // Select forms to appear in select field
    $inputfields->add([
      'label' => __('Form Select Options'),
      'name' => 'form_select_option_type',
      'type' => 'InputfieldRadios',
      'collapsed' => Inputfield::collapsedNever,
      'required' => 1,
      'defaultValue' => '1',
      'description' => __('Which forms should be available for selection in this field?'),
      'value' => $field->get('form_select_option_type') ?? 'all',
      'options' => [
        'all' => __('All Forms'),
        'include_selected' => __('Choose Forms To Include'),
        'exclude_selected' => __('Choose Forms To Exclude'),
        'include_name_startswith' => __('Include Where Names Start With Value'),
        'include_name_endswith' => __('Include Where Names End With Value'),
        'include_name_contains' => __('Include Where Names Contain Value'),
      ],
    ]);

    // Select forms to include
    $inputfields->add([
      'label' => __('Included Forms'),
      'type' => 'InputfieldAsmSelect',
      'name' => 'included_form_ids',
      'value' => $field->get('included_form_ids') ?? [],
      'description' => __('Forms that are included in this field'),
      'options' => array_flip($allForms),
      'showIf' => 'form_select_option_type=include_selected',
    ]);

    // Select forms to exclude
    $inputfields->add([
      'type' => 'InputfieldAsmSelect',
      'name' => 'excluded_form_ids',
      'label' => __('Excluded Forms'),
      'description' => __('Forms that are excluded from this field'),
      'value' => $field->get('excluded_form_ids') ?? [],
      'options' => array_flip($allForms),
      'showIf' => 'form_select_option_type=exclude_selected',
    ]);

    // Select forms where name starts with string
    $inputfields->add([
      'type' => 'InputfieldText',
      'name' => 'name_startswith_value',
      'label' => __('Name Starts With Value'),
      'description' => __('Forms with names that start with this value will be included'),
      'value' => $field->get('name_startswith_value') ?? '',
      'showIf' => 'form_select_option_type=include_name_startswith',
      'required' => true,
      'requiredIf' => 'form_select_option_type=include_name_startswith',
    ]);

    // Select forms where name ends with string
    $inputfields->add([
      'type' => 'InputfieldText',
      'name' => 'name_endswith_value',
      'label' => __('Name Ends With Value'),
      'description' => __('Forms with names that end with this value will be included'),
      'value' => $field->get('name_endswith_value') ?? '',
      'showIf' => 'form_select_option_type=include_name_endswith',
      'required' => true,
      'requiredIf' => 'form_select_option_type=include_name_endswith',
    ]);

    // Select forms where name contains string
    $inputfields->add([
      'type' => 'InputfieldText',
      'name' => 'name_contains_value',
      'label' => __('Name Contains Value'),
      'description' => __('Forms with names that contain this value will be included'),
      'value' => $field->get('name_endswith_value') ?? '',
      'showIf' => 'form_select_option_type=include_name_contains',
      'required' => true,
      'requiredIf' => 'form_select_option_type=include_name_contains',
    ]);

    return $inputfields;
  }

  /**
   * We are storing the name of a form, so we sanitize to the form name requirements
   * {@inheritdoc}
   */
  public function sanitizeValue(Page $page, Field $field, $value): string {
    return preg_replace('/[^a-z0-9-_]/i', '', $value);
  }

  /**
   * Return the rendered Form Builder form
   * {@inheritdoc}
   */
  public function ___markupValue(Page $page, Field $field, $value = null, $property = '') {
    return $this->modules->FormBuilder->render($value);
  }


  /**
   * {@inheritdoc}
   */
  public function getBlankValue(Page $page, Field $field) {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getInputfield(Page $page, Field $field) {
    return $this->modules->InputfieldFormSelect;
  }

  /**
   * {@inheritdoc}
   */
  public function ___saveFieldReady(Field $field) {
    $selectOptionType = $field->get('form_select_option_type');
    $includedIds = $field->get('included_form_ids') ?? [];
    $excludedIds = $field->get('excluded_form_ids') ?? [];

    $filterValues = match ($selectOptionType) {
      'include_selected' => $includedIds,
      'exclude_selected' => $excludedIds,
      'include_name_startswith' => $field->get('name_startswith_value') ?? '',
      'include_name_endswith' => $field->get('name_endswith_value') ?? '',
      'include_name_contains' => $field->get('name_contains_value') ?? '',
      default => null,
    };

    $field->set('form_select_option_type', $selectOptionType);
    $field->set('included_form_ids', $includedIds);
    $field->set('excluded_form_ids', $excludedIds);
    $field->set('filter_values', $filterValues);
  }

  /**
   * {@inheritdoc}
   */
  public function getDatabaseSchema(Field $field): array {
    $schema = parent::getDatabaseSchema($field);

    $schema['data'] = 'text NOT NULL';
    $schema['keys']['data'] = 'FULLTEXT KEY `data` (`data`)';

    return $schema;
  }
}