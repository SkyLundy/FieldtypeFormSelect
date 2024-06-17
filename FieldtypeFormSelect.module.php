<?php namespace ProcessWire;

use InvalidArgumentException;

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
   * @param array<string> $fieldNames
   */
  public function clearFieldValuesWithFormId(
    Page $page,
    int $deletedFormId,
    array $fieldNames = []
  ): void {
    foreach ($fieldNames as $fieldName) {
      (int) $page->$fieldName === $deletedFormId && $page->setAndSave($fieldName, '');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function sanitizeValue(
    Page $page,
    Field $field,
    $value
  ) {
    if (wire('page')->template->name === 'admin') {
      if ($value instanceof FormBuilderForm) {
        return $value->id;
      }

      return $value ? (int) $value : '';
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function ___markupValue(Page $page, Field $field, $value = null, $property = '') {
    return $value ? $this->modules->FormBuilder->render($value) : '';
  }

  /**
   * {@inheritdoc}
   */
  public function ___wakeupValue(Page $page, Field $field, $value): FormBuilderForm|int|null|string|false {
    if ($value) {
      $value = $this->modules->FormBuilder->form($value);
    }

    if (wire('page')->template->name === 'admin') {
      if ($value instanceof FormBuilderForm) {
        return $value->id;
      }

      return $value ? (int) $value : '';
    }

    return match ($field->field_output) {
      'form_id_or_false' => $value ? $value->id : false,
      'form_name_or_empty_string' => $value ? $value->name : '',
      default => $value ? $value : null,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function ___sleepValue(Page $page, Field $field, $value) {
    return $value ? (int) $value : '';
  }

  /**
   * {@inheritdoc}
   */
  public function getBlankValue(Page $page, Field $field) {
    return match ($field->field_output) {
      'form_id_or_false' => false,
      'form_name_or_empty_string' => '',
      default => null,
    };
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
        'label' => __('Form select options'),
        'value' => __("There are no forms present created using Form Builder. This field will render an empty select element."),
        'themeColor' => 'warning',
      ]);

      return $inputfields;
    }

    $allForms = $forms->loadAll();

    // Create options with form name and form ID
    array_walk($allForms, fn(&$v) => $v = $v->id);

    $inputfields->add([
      'type' => 'InputfieldFieldset',
      'label' => __('Included forms'),
      'name' => 'included_forms',
      'collapsed' => Inputfield::collapsedNever,
      'children' => [
        // Select forms to appear in select field
        'form_select_option_type' => [
          'label' => __('Form select options'),
          'type' => 'InputfieldRadios',
          'icon' => 'list',
          'required' => 1,
          'defaultValue' => '1',
          'description' => __('Which forms should be available for selection in this field?'),
          'value' => $field->get('form_select_option_type') ?? 'all',
          'options' => [
            'all' => __('All forms'),
            'include_selected' => __('Choose forms to include'),
            'exclude_selected' => __('Choose forms to exclude'),
            'include_name_startswith' => __('Include where name starts with value'),
            'include_name_endswith' => __('Include where name ends with value'),
            'include_name_contains' => __('Include where name contains value'),
          ],
        ],
        // Select forms to include
        'included_form_ids' => [
          'label' => __('Included forms'),
          'type' => 'InputfieldAsmSelect',
          'value' => $field->get('included_form_ids') ?? [],
          'options' => array_flip($allForms),
          'showIf' => 'form_select_option_type=include_selected',
          'required' => true,
          'requiredIf' => 'form_select_option_type=include_selected',
        ],
        // Select forms to exclude
        'excluded_form_ids' => [
          'type' => 'InputfieldAsmSelect',
          'label' => __('Excluded forms'),
          'value' => $field->get('excluded_form_ids') ?? [],
          'options' => array_flip($allForms),
          'showIf' => 'form_select_option_type=exclude_selected',
          'required' => true,
          'requiredIf' => 'form_select_option_type=exclude_selected',
        ],
        // Select forms where name starts with string
        'name_startswith_value' => [
          'type' => 'InputfieldText',
          'label' => __('Forms where name starts with'),
          'value' => $field->get('name_startswith_value') ?? '',
          'showIf' => 'form_select_option_type=include_name_startswith',
          'required' => true,
          'requiredIf' => 'form_select_option_type=include_name_startswith',
        ],
        // Select forms where name ends with string
        'name_endswith_value' => [
          'type' => 'InputfieldText',
          'label' => __('Forms where name ends with'),
          'value' => $field->get('name_endswith_value') ?? '',
          'showIf' => 'form_select_option_type=include_name_endswith',
          'required' => true,
          'requiredIf' => 'form_select_option_type=include_name_endswith',
        ],
        // Select forms where name contains string
        'name_contains_value' => [
          'type' => 'InputfieldText',
          'label' => __('Forms where name contains'),
          'value' => $field->get('name_contains_value') ?? '',
          'showIf' => 'form_select_option_type=include_name_contains',
          'required' => true,
          'requiredIf' => 'form_select_option_type=include_name_contains',
        ]
      ],
    ]);

    // Field output value when value is present
    $inputfields->add([
      'type' => 'InputfieldFieldset',
      'label' => __('Form field value type'),
      'name' => 'field_output_types',
      'collapsed' => Inputfield::collapsedNever,
      'children' => [
        'field_output' => [
          'label' => __('Value when a form has been selected'),
          'icon' => 'check-circle',
          'type' => 'InputfieldRadios',
          'required' => true,
          'defaultValue' => '1',
          'value' => $field->get('field_output') ?? 'form_id_or_false',
          'options' => [
            'form_id_or_false' => __('Form ID or boolean false when none selected'),
            'form_name_or_empty_string' => __('Form name or empty string when none selected'),
            'form_builder_form' => __('FormBuilder form object or null when none selected'),
          ],
        ],
        'example_field_id_or_false' => [
          'type' => 'InputfieldMarkup',
          'label' => __('API usage example - Form ID or boolean false when none selected'),
          'icon' => 'code',
          'showIf' => 'field_output=form_id_or_false',
          'value' => <<<EOT
          <pre><code>// Using the page field render method

          if (&dollar;page->field_name) {
            echo &dollar;page->render('field_name');
          }

          // Using the FormBuilder render method

          if (&dollar;page->field_name) {
            echo &dollar;forms->render(&dollar;forms->render(&dollar;page->field_name));
          }
          </code></pre>
          EOT,
        ],
        'example_field_name_or_empty_string' => [
          'type' => 'InputfieldMarkup',
          'label' => __('API usage example - Form name or empty string when none selected'),
          'icon' => 'code',
          'showIf' => 'field_output=form_name_or_empty_string',
          'value' => <<<EOT
          <pre><code>// Using the page field render method

          if (&dollar;page->field_name) {
            echo &dollar;page->render('field_name');
          }

          // Using the FormBuilder render method

          if (&dollar;page->field_name) {
            echo &dollar;forms->render(&dollar;forms->render(&dollar;page->field_name));
          }
          </code></pre>
          EOT,
        ],
        'example_form_builder_form' => [
          'type' => 'InputfieldMarkup',
          'label' => __('API usage example - Form name or empty string when none selected'),
          'icon' => 'code',
          'showIf' => 'field_output=form_builder_form',
          'value' => <<<EOT
          <pre><code>// Conditionally render using a nullsafe operator and FormBuilder API

          echo &dollar;page->field_name?->render();

          echo &dollar;page->field_name?->embed();

          // With assets

          &dollar;form = &dollar;page->field_name;

          echo &dollar;form?->styles;
          echo &dollar;form?->scripts;

          echo &dollar;form?->render();

          // Using the FormBuilder render method

          if (&dollar;page->field_name) {
            echo &dollar;forms->render(&dollar;page->field_name);
          }
          </code></pre>
          EOT,
        ],
      ],
    ]);

    return $inputfields;
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