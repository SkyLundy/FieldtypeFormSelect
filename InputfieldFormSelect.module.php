<?php namespace ProcessWire;

// FileCompiler=0

class InputfieldFormSelect extends Inputfield {

  public static function getModuleInfo() {
    return [
      'title' => __('FormBuilder Select Inputfield'),
      'summary' => __('Select a form created via the Pro Form Builder module', __FILE__),
      'version' => '101',
      'href' => 'https://processwire.com/talk/topic/29771-fieldtypeformselect-a-field-for-selecting-forms-built-using-the-pro-formbuilder-module/',
      'icon' => 'envelope-o',
      'requires' => [
        'FormBuilder',
        'ProcessWire>=300',
        'PHP>=8.1',
        'FieldtypeFormSelect'
      ],
    ];
  }

  /**
   * Config options: Form select field label type
   */
  private const FORM_OPTION_STYLE_NAME = 'name';
  private const FORM_OPTION_STYLE_LABEL = 'label';
  private const FORM_OPTION_STYLE_LABEL_CAP = 'label_cap';
  private const FORM_OPTION_STYLE_LABEL_CAP_FIRST = 'label_cap_first';


  public function __construct() {
    parent::__construct();

    $this->set('form_option_style', self::FORM_OPTION_STYLE_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public function ___render() {
    ['value' => $value] = $this->getAttributes();

    $form = $this->wire('forms')->get($value);

    $select = $this->modules->get('InputfieldSelect');
    $select->name = $this->name;
    $select->skipLabel = Inputfield::skipLabelHeader;
    $select->value = $form?->id;
    $select = $this->___addOptions($select);

    return $select->render();
  }

  /**
   * Adds options to the InputfieldSelect
   * @param  InputfieldSelect $inputfieldSelect Inputfield without options set
   * @return InputfieldSelect                   Inputfield with options added
   */
  private function ___addOptions(InputfieldSelect $inputfieldSelect): InputfieldSelect {
    $forms = $this->___getFormOptions();

    return array_reduce($forms, fn ($inputfield, $form) => $inputfield->addOption(
      value: $form->id,
      label: $this->___formatOptionLabel($form),
    ), $inputfieldSelect);
  }

  /**
   * Creates the InputfieldSelect element option label by formatting as configured
   * @param  FormBuilderForm $form The form with the name to be formatted
   * @return string                Formatted select label
   */
  public function ___formatOptionLabel(FormBuilderForm $form): string {
    $value = $form->name;

    $label = $this->form_option_style === self::FORM_OPTION_STYLE_LABEL;
    $labelCap = $this->form_option_style === self::FORM_OPTION_STYLE_LABEL_CAP;
    $labelCapFirst = $this->form_option_style === self::FORM_OPTION_STYLE_LABEL_CAP_FIRST;

    ($label || $labelCap || $labelCapFirst) && $value = preg_replace('/[-_]/', ' ', $value);

    $labelCapFirst && $value = ucfirst($value);

    $labelCap && $value = ucwords($value);

    return $value;
  }

  /**
   * Returns the forms that should be included in a rendered field according to this field's
   * configuration
   * @return array<Form>
   */
  private function ___getFormOptions(): array {
    $field = $this->hasField;
    $filter = $field->filter_values;
    $forms = array_values($this->modules->FormBuilder->forms->loadAll());

    if (!$filter) {
      return $forms;
    }

    // Handles matching a name according to start/end/contains  the filtered tring
    $nameMatch = function($form, $constraint) use ($filter) {
      $name = strtolower($form->name);
      $filter = strtolower($filter);

      return match ($constraint) {
        'starts' => str_starts_with($name, $filter),
        'ends' => str_ends_with($name, $filter),
        'contains' => str_contains($name, $filter),
      };
    };

    $forms = match ($field->form_select_option_type) {
      'include_selected' => array_filter($forms, fn ($form) => in_array($form->id, $filter)),
      'exclude_selected' => array_filter($forms, fn ($form) => !in_array($form->id, $filter)),
      'include_name_startswith' => array_filter($forms, fn ($form) => $nameMatch($form, 'starts')),
      'include_name_endswith' => array_filter($forms, fn ($form) => $nameMatch($form, 'ends')),
      'include_name_contains' => array_filter($forms, fn ($form) => $nameMatch($form, 'contains')),
      default => $forms,
    };

    // Alphabetize
    usort(
      $forms,
      fn($a, $b) =>  strcmp($a->name, $b->name)
    );

    return $forms;
  }

  /**
   * {@inheritdoc}
   */
  public function ___getConfigInputfields() {
    $inputfields = parent::___getConfigInputfields();

    $inputfields->add([
      'label' => __('Select option style'),
      'type' => 'InputfieldSelect',
      'name' => 'form_option_style',
      'value' => $this->form_option_style ?? self::FORM_OPTION_STYLE_NAME,
      'description' => __('How should the names of forms be displayed in the select field?'),
      'options' => [
        self::FORM_OPTION_STYLE_NAME => 'an-example-form (original)',
        self::FORM_OPTION_STYLE_LABEL => 'an example form (as label, original casing)',
        self::FORM_OPTION_STYLE_LABEL_CAP => 'An Example Form (as label, words capitalized)',
        self::FORM_OPTION_STYLE_LABEL_CAP_FIRST => 'An example form (as label, first word capitalized)',
      ],
      'required' => true,
    ]);

    return $inputfields;
  }
}