<?php namespace ProcessWire;

// FileCompiler=0

class InputfieldFormSelect extends Inputfield {

  private const FORM_OPTION_STYLE_NAME = 'name';

  private const FORM_OPTION_STYLE_LABEL = 'label';

  private const FORM_OPTION_STYLE_LABEL_CAP = 'label_cap';

  public static function getModuleInfo() {
    return [
      'title' => __('FormBuilder Select Inputfield'),
      'summary' => __('Select a form created via the Pro Form Builder module', __FILE__),
      'version' => '100',
      'href' => 'https://processwire.com/talk/topic/29771-fieldtypeformselect-a-field-for-selecting-forms-built-using-the-pro-formbuilder-module/',
      'icon' => 'envelope-o',
      'autoload' => true,
      'singular' => true,
      'requires' => [
        'FormBuilder',
        'ProcessWire>=300',
        'PHP>=8.1',
        'FieldtypeFormSelect'
      ],
    ];
  }

  public function __construct() {
    parent::__construct();
    $this->set('form_option_style', self::FORM_OPTION_STYLE_NAME);
  }

  /**
   * Creates a null safe method of rendering the form using the iframe method.
   * @param  string                $fieldName Name of field
   * @param  string|Page|int|null  $page      ID or Page where field is located, if not provided the
   *                                          current page is assumed
   * @return string|null                      Markup for embedding or null if field is empty
   */
  public function embed(string $fieldName, string|Page|int|null $page = null): ?string {
    $targetPage = match (gettype($page)) {
      'string' => $this->pages->get($page),
      'int' => $this->pages->get($page),
      'object' => $page,
      default => $this->page,
    };

    $fieldValue = $targetPage->$fieldName;

    if (!$fieldValue) {
      return null;
    }

    return $this->forms->embed($targetPage->$fieldName);
  }

  /**
   * {@inheritdoc}
   */
  public function ___render() {
    $attrs = $this->getAttributes();
    $value = $attrs['value'];

    unset($attrs['value']);

    return <<<EOT
    <select {$this->getAttributesString($attrs)}>
    {$this->___renderOptions($value)}
    </select>
    EOT;
  }

  /**
   * Renders individual options for the select element
   * @param  string|null  $value          Current value
   * @param  bool|boolean $addEmptyOption Add an empty option
   * @return string
   */
  private function ___renderOptions(?string $value = null) {
    $markup =<<<EOT
    <option value="%{VALUE}" %{SELECTED}>%{LABEL}</option>
    EOT;

    // Format the field option style based on the Inputfield configuration
    $formatOptionLabel = function(string $val) {
      $label = $this->form_option_style === self::FORM_OPTION_STYLE_LABEL;
      $labelCap = $this->form_option_style === self::FORM_OPTION_STYLE_LABEL_CAP;

      ($label || $labelCap) && $val = preg_replace('/[-_]/', ' ', $val);
      $labelCap && $val = ucwords($val);

      return $val;
    };

    $forms = $this->___getFormOptions();

    $options = array_map(fn($form) => strtr($markup, [
      '%{VALUE}' => $form->id,
      '%{SELECTED}' => $form->id === (int) $value ? 'selected' : null,
      '%{LABEL}' => $formatOptionLabel($form->name),
    ]), $forms);

    array_unshift(
      $options,
      strtr($markup, ['%{VALUE}' => '', '%{SELECTED}' => '', '%{LABEL}' => ''])
    );

    return implode('', $options);
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
      'include_selected' => array_filter($forms, fn($form) => in_array($form->id, $filter)),
      'exclude_selected' => array_filter($forms, fn($form) => !in_array($form->id, $filter)),
      'include_name_startswith' => array_filter($forms, fn($form) => $nameMatch($form, 'starts')),
      'include_name_endswith' => array_filter($forms, fn($form) => $nameMatch($form, 'ends')),
      'include_name_contains' => array_filter($forms, fn($form) => $nameMatch($form, 'contains')),
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
      'label' => __('Select Option Style'),
      'type' => 'InputfieldSelect',
      'name' => 'form_option_style',
      'value' => $this->form_option_style ?? self::FORM_OPTION_STYLE_NAME,
      'description' => __('How the names of forms should be displayed when the field is rendered: as the FormBuilder form name, or as a "labelized" version of the form name.'),
      'notes' => __('The "labelized" version is created from the form name by replacing - and _ with a space and, optionally, capitalizing each word.'),
      'options' => [
        self::FORM_OPTION_STYLE_NAME => 'an-example-form (original)',
        self::FORM_OPTION_STYLE_LABEL => 'an example form (as label, original casing)',
        self::FORM_OPTION_STYLE_LABEL_CAP => 'An Example Form (as label, words capitalized)',
      ],
      'required' => true,
    ]);

    return $inputfields;
  }
}