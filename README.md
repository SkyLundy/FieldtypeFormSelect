# Fieldtype Form Select

A ProcessWire Fieldtype for creating fields where a form built using Form Builder can be chosen via a select element.

## Requirements

- ProcessWire >= 3.0
- FormBuilder
- PHP >= 8.1

This module was developed using FormBuilder 0.5.5 and ProcessWire 3.0.235, however it should be compatible with other versions.

## How To Use

Install in ProcessWire as a module from the [ProcessWire directory](https://processwire.com/modules/fieldtype-form-select/).

Or install with composer using `composer require firewire/fieldtype-form-select`.

Ensure that FormBuilder is installed, then install this module. Create some forms. Create a Form Select field and then specify which forms you would like to appear in the field under the "Details" tab. The options to select which forms will appear in the select field are as follows:

- All forms (default)
- Include only forms you choose
- Include all forms except those you choose to exclude
- Include forms where the name starts with a specified string
- Include forms where the name ends with a specified string
- Include forms where the name contains a specified string

Optionally, choose how the form names will appear in the select element when rendered. Options include:

- Default name: my-form-name
- Lowercase and spaced: my form name
- Capitalized and spaced: My Form Name

This field stores the ID of the for that is selected, or null otherwise.

If a form is deleted, all Form Select fields containing a reference/ID to that form will have their value cleared and set to `null`.

## Nifty Tricks

Form Select provides another way to render your form. To render the markup for a form that was selected, call `$page->render('your_form_select_field')`, and voila- your form is rendered to the page.

This module is compatible with [FormBuilderHtmx](https://processwire.com/modules/form-builder-htmx/) which adds AJAX submission abilities to forms built with FormBuilder.
