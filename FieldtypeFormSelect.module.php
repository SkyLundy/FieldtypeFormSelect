<?php namespace ProcessWire;

use InvalidArgumentException;

class FieldtypeFormSelect extends FieldType {

  public static function getModuleInfo() {
    return [
      'title' => __('Form Select'),
      'summary' => __('Select a form created via the Pro Form Builder module', __FILE__),
      'version' => '101',
      'href' => 'https://processwire.com/talk/topic/29771-fieldtypeformselect-a-field-for-selecting-forms-built-using-the-pro-formbuilder-module/',
      'icon' => 'envelope-o',
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
    if ($value instanceof FormBuilderForm) {
      return $value->render();
    }

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
      'form_id_or_null' => $value ? $value->id : $this->getBlankValue($page, $field),
      'form_name_or_null' => $value ? $value->name : $this->getBlankValue($page, $field),
      default => $value ? $value : $this->getBlankValue($page, $field),
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
    return null;
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
      'label' => '',
      'skipLabel' =>  Inputfield::skipLabelHeader,
      'name' => 'included_forms',
      'collapsed' => Inputfield::collapsedNever,
      'children' => [
        // Select forms to appear in select field
        'form_select_option_type' => [
          'label' => __('Form select options'),
          'type' => 'InputfieldRadios',
          'icon' => 'list',
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
      'label' => '',
      'skipLabel' =>  Inputfield::skipLabelHeader,
      'name' => 'field_output_types',
      'collapsed' => Inputfield::collapsedNever,
      'children' => [
        'field_output' => [
          'label' => __('Field output value'),
          'icon' => 'tasks',
          'type' => 'InputfieldRadios',
          'defaultValue' => '1',
          'value' => $field->get('field_output') ?? 'form_id_or_null',
          'options' => [
            'form_id_or_null' => __('Form ID or null when a form is not selected'),
            'form_name_or_null' => __('Form name or null when a form is not selected'),
            'form_builder_form' => __('FormBuilder form object or null when a form is not selected'),
          ],
        ],
        'example_field_id_or_false' => [
          'type' => 'InputfieldMarkup',
          'label' => __('API usage example - Form ID or null when a form is not selected'),
          'icon' => 'code',
          'showIf' => 'field_output=form_id_or_null',
          'value' => <<<EOT
          <p>Using the Page field render method, renders as 'Option C' or empty string if a form is not selected</p>
          <pre><code>if (&dollar;page->your_field) {
            echo &dollar;page->render('your_field');
          }
          </code></pre>
          <p>Using the FormBuilder render/embed methods</p>
          <pre><code>if (&dollar;page->your_field) {
             &dollar;form = &dollar;forms->form(&dollar;page->your_field);

             echo &dollar;form->styles;
             echo &dollar;form->scripts;
             echo &dollar;form->render();
          }
          </code></pre>
          EOT,
        ],
        'example_field_name_or_empty_string' => [
          'type' => 'InputfieldMarkup',
          'label' => __('API usage example - Form name or empty null when a form is not selected'),
          'icon' => 'code',
          'showIf' => 'field_output=form_name_or_null',
          'value' => <<<EOT
          <p>Using the Page field render method, renders as 'Option C' or empty string if a form is not selected</p>
          <pre><code>if (&dollar;page->your_field) {
            echo &dollar;page->render('your_field');
          }
          </code></pre>
          <p>Using the FormBuilder render/embed methods</p>
          <pre><code>if (&dollar;page->your_field) {
             &dollar;form = &dollar;forms->form(&dollar;page->your_field);

             echo &dollar;form->styles;
             echo &dollar;form->scripts;
             echo &dollar;form->render();
          }
          </code></pre>
          EOT,
        ],
        'example_form_builder_form' => [
          'type' => 'InputfieldMarkup',
          'label' => __('API usage example - FormBuilder form or null when a form is not selected'),
          'description' => __('Using this method will pass the FormBuilder form object directly from the field'),
          'icon' => 'code',
          'showIf' => 'field_output=form_builder_form',
          'value' => <<<EOT
          <p>Conditionally render/embed using a nullsafe operator</p>
          <pre><code>echo &dollar;page->your_field?->render();

          echo &dollar;page->your_field?->embed();
          </code></pre>
          <p>Conditionally render/embed with assets</p>
          <pre><code>&dollar;form = &dollar;page->your_field;

          echo &dollar;form?->styles;
          echo &dollar;form?->scripts;
          echo &dollar;form?->render();
          </code></pre>
          <p>Render using FormBuilder</p>
          <pre><code>if (&dollar;page->your_field) {
            echo &dollar;forms->render(&dollar;page->your_field);
          }

          </code></pre>
          <p>Using the Page field render method, renders as 'Option C' or empty string when a form is not selected</p>
          <pre><code>echo &dollar;page->render('your_field');
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