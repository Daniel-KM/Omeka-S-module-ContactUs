<?php declare(strict_types=1);

namespace ContactUs\Form\Element;

use Laminas\Filter;
use Laminas\Form\Element;
use Laminas\Validator;
use Omeka\Form\Element\ArrayTextarea;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Textarea holding a YAML definition of the fields, parsed into Laminas element
 * specifications ready for Fieldset::add().
 *
 * The friendly YAML is a mapping "name => (label | object)":
 * - a scalar value is the label of a simple text field;
 * - a null value is a core field kept at its default;
 * - an object accepts the keys "label", "type", "required", "values" (the value
 *   options of select/radio/multicheckbox) and "attributes" (a free map of html
 *   attributes such as placeholder, class or pattern).
 */
class FieldsTextarea extends ArrayTextarea
{
    /**
     * Map of the friendly type names to the Laminas form element classes.
     *
     * @var array
     */
    protected $typeClasses = [
        'text' => Element\Text::class,
        'textarea' => Element\Textarea::class,
        'email' => Element\Email::class,
        'tel' => Element\Tel::class,
        'phone' => Element\Tel::class,
        'number' => Element\Number::class,
        'url' => Element\Url::class,
        'date' => Element\Date::class,
        'time' => Element\Time::class,
        'datetime' => Element\DateTimeLocal::class,
        'password' => Element\Password::class,
        'hidden' => Element\Hidden::class,
        'select' => Element\Select::class,
        'radio' => Element\Radio::class,
        'checkbox' => Element\Checkbox::class,
        'multicheckbox' => Element\MultiCheckbox::class,
    ];

    /**
     * Types requiring a non-empty list of value options.
     *
     * @var array
     */
    protected $typesWithOptions = ['select', 'radio', 'multicheckbox'];

    /**
     * System field names that are not user fields and must not appear in the
     * definition. "id" carries the attached resource ids and is injected at
     * runtime, not configured here.
     *
     * @var array
     */
    protected $systemFields = ['id'];

    public function setOptions($options)
    {
        parent::setOptions($options);
        // Expose the editor options to the javascript through data attributes.
        // The form and the yaml modes are enabled by default; the preview is
        // opt-in; the default display is the form.
        $enableForm = !array_key_exists('enable_edit_form', $this->options)
            || $this->options['enable_edit_form'];
        $enableYaml = !array_key_exists('enable_edit_yaml', $this->options)
            || $this->options['enable_edit_yaml'];
        $default = ($this->options['default_display'] ?? 'form') === 'text' ? 'text' : 'form';
        $this->setAttribute('data-enable-form', $enableForm ? '1' : '0');
        $this->setAttribute('data-enable-yaml', $enableYaml ? '1' : '0');
        $this->setAttribute('data-enable-preview', empty($this->options['enable_preview']) ? '0' : '1');
        $this->setAttribute('data-default-display', $default);
        return $this;
    }

    public function getInputSpecification()
    {
        return [
            'name' => $this->getName(),
            'required' => false,
            'allow_empty' => true,
            'filters' => [
                [
                    'name' => Filter\Callback::class,
                    'options' => ['callback' => [$this, 'stringToArray']],
                ],
            ],
            'validators' => [
                [
                    'name' => Validator\Callback::class,
                    'options' => [
                        'callback' => [$this, 'validateFields'],
                        'messages' => [
                            Validator\Callback::INVALID_VALUE =>
                                'Invalid fields: check the YAML syntax, the field names (ascii, no space), the types and, for select/radio/multicheckbox, the list of values.', // @translate
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Parse the YAML textarea into Laminas element specifications keyed by
     * name.
     *
     * On a YAML error the raw string is returned unchanged, which the validator
     * detects to block the save.
     *
     * @param string|array $string
     * @return array|string
     */
    public function stringToArray($string)
    {
        if (is_array($string)) {
            // Already normalized specifications (from the database or a
            // re-set); drop the system fields.
            return array_diff_key($string, array_flip($this->systemFields));
        }

        $string = $this->fixEndOfLine((string) $string);
        if (trim($string) === '') {
            return [];
        }

        try {
            $parsed = Yaml::parse($string);
        } catch (ParseException $e) {
            return $string;
        }
        if (!is_array($parsed)) {
            return $string;
        }

        $result = [];
        foreach ($parsed as $name => $value) {
            if (in_array((string) $name, $this->systemFields, true)) {
                continue;
            }
            $result[(string) $name] = $this->normalizeEntry((string) $name, $value);
        }
        return $result;
    }

    /**
     * Render the stored specifications back to the editable YAML.
     *
     * @param string|array $array
     * @return string
     */
    public function arrayToString($array)
    {
        if (is_string($array)) {
            return $array;
        }
        $friendly = [];
        foreach ($array ?? [] as $name => $data) {
            if (in_array((string) $name, $this->systemFields, true)) {
                continue;
            }
            $friendly[(string) $name] = $this->specToFriendly($data);
        }
        return $friendly ? Yaml::dump($friendly, 4, 2) : '';
    }

    public function validateFields($value): bool
    {
        // A string here means the YAML could not be parsed into a mapping.
        if (is_string($value)) {
            return trim($value) === '';
        }
        $specs = is_array($value) ? $value : [];
        foreach ($specs as $name => $spec) {
            if (!preg_match('/^[A-Za-z0-9_-]+$/', (string) $name)) {
                return false;
            }
            $type = array_search($spec['type'] ?? Element\Text::class, $this->typeClasses, true);
            if ($type === false) {
                return false;
            }
            if (in_array($type, $this->typesWithOptions, true)
                && empty($spec['options']['value_options'])
            ) {
                return false;
            }
        }
        return true;
    }

    protected function normalizeEntry(string $name, $value): array
    {
        if ($value === null) {
            $value = [];
        }

        if (is_scalar($value)) {
            $label = (string) $value;
            $required = false;
            if (strpos($label, '* ') === 0) {
                $required = true;
                $label = trim(substr($label, 2));
            }
            return $this->buildSpec($name, $label, 'text', $required, [], [], []);
        }

        $value = (array) $value;
        $label = (string) ($value['label'] ?? '');
        $required = !empty($value['required']);
        if (strpos($label, '* ') === 0) {
            $required = true;
            $label = trim(substr($label, 2));
        }
        $type = strtolower((string) ($value['type'] ?? 'text'));
        $valueOptions = $this->normalizeValues($value['values'] ?? []);
        $attributes = isset($value['attributes']) && is_array($value['attributes'])
            ? $value['attributes']
            : [];
        $elementOptions = isset($value['options']) && is_array($value['options'])
            ? $value['options']
            : [];

        return $this->buildSpec($name, $label, $type, $required, $valueOptions, $attributes, $elementOptions);
    }

    protected function buildSpec(string $name, string $label, string $type, bool $required, array $valueOptions, array $attributes, array $elementOptions): array
    {
        // The friendly "label" and "values" win over the Laminas options bag,
        // which carries the other element options (empty_option, info, etc.).
        $options = $elementOptions;
        $options['label'] = $label;
        if ($valueOptions) {
            $options['value_options'] = $valueOptions;
        }
        $spec = [
            'name' => $name,
            'type' => $this->typeClasses[$type] ?? Element\Text::class,
            'options' => $options,
            'attributes' => $attributes,
        ];
        if ($required) {
            $spec['attributes']['required'] = true;
        }
        // A multi checkbox needs its value handled as an array downstream.
        if ($type === 'multicheckbox') {
            $spec['attributes']['multiple'] = true;
        }
        return $spec;
    }

    /**
     * @param string|array $spec
     * @return string|array|null
     */
    protected function specToFriendly($spec)
    {
        if (!is_array($spec)) {
            return $spec === '' || $spec === null ? null : (string) $spec;
        }

        $options = $spec['options'] ?? [];
        $label = (string) ($options['label'] ?? $spec['label'] ?? '');
        $type = array_search($spec['type'] ?? Element\Text::class, $this->typeClasses, true) ?: 'text';
        $valueOptions = $options['value_options'] ?? $spec['value_options'] ?? [];
        $elementOptions = $options;
        unset($elementOptions['label'], $elementOptions['value_options']);
        $attributes = $spec['attributes'] ?? [];
        $required = !empty($attributes['required']);
        unset($attributes['required'], $attributes['multiple']);

        if ($type === 'text' && !$valueOptions && !$attributes && !$required && !$elementOptions) {
            return $label === '' ? null : $label;
        }

        $entry = [];
        if ($label !== '') {
            $entry['label'] = $label;
        }
        if ($type !== 'text') {
            $entry['type'] = $type;
        }
        if ($required) {
            $entry['required'] = true;
        }
        if ($valueOptions) {
            $entry['values'] = $this->valuesToFriendly($valueOptions);
        }
        if ($elementOptions) {
            $entry['options'] = $elementOptions;
        }
        if ($attributes) {
            $entry['attributes'] = $attributes;
        }
        return $entry ?: null;
    }

    protected function normalizeValues($values): array
    {
        if (!is_array($values)) {
            return [];
        }
        $result = [];
        $isList = array_keys($values) === range(0, count($values) - 1);
        foreach ($values as $key => $val) {
            if ($isList) {
                $result[(string) $val] = (string) $val;
            } else {
                $result[(string) $key] = (string) $val;
            }
        }
        return $result;
    }

    /**
     * @return array List when keys equal labels, else a map.
     */
    protected function valuesToFriendly(array $valueOptions)
    {
        foreach ($valueOptions as $value => $label) {
            if ((string) $value !== (string) $label) {
                return $valueOptions;
            }
        }
        return array_values($valueOptions);
    }
}
