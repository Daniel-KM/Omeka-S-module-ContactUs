<?php declare(strict_types=1);

namespace ContactUs\Form\Element;

use Laminas\Filter;
use Laminas\Form\Element;
use Laminas\Validator;
use Omeka\Form\Element\ArrayTextarea;

class Fields extends ArrayTextarea
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
                                'Invalid field: check the name (ascii, no space), the type and, for select/radio/multicheckbox, the list of options.', // @translate
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Parse the textarea into a list of Laminas element specifications keyed by
     * field name, ready to be passed to Fieldset::add().
     *
     * @param string|array $string
     * @return array
     */
    public function stringToArray($string)
    {
        if (is_array($string)) {
            return $this->normalizeArray($string);
        }

        $result = [];
        foreach ($this->stringToList($string) as $line) {
            [$name, $rest] = strpos($line, $this->keyValueSeparator) === false
                ? [$line, '']
                : array_map('trim', explode($this->keyValueSeparator, $line, 2));
            if ($name === '') {
                continue;
            }

            [$labelPart, $typePart] = strpos($rest, '|') === false
                ? [$rest, null]
                : array_map('trim', explode('|', $rest, 2));

            $required = false;
            $label = $labelPart;
            if (strpos($label, '* ') === 0) {
                $required = true;
                $label = trim(substr($label, 2));
            }

            $type = 'text';
            $valueOptions = [];
            if ($typePart !== null && $typePart !== '') {
                [$typeName, $optsPart] = strpos($typePart, ':') === false
                    ? [$typePart, null]
                    : array_map('trim', explode(':', $typePart, 2));
                $type = strtolower($typeName);
                if ($optsPart !== null) {
                    $valueOptions = $this->parseValueOptions($optsPart);
                }
            }

            $result[$name] = $this->lineToSpec($name, $label, $type, $required, $valueOptions);
        }
        return $result;
    }

    /**
     * Render the stored specifications back to the editable textarea syntax.
     *
     * @param string|array $array
     * @return string
     */
    public function arrayToString($array)
    {
        if (is_string($array)) {
            return $array;
        }
        $lines = [];
        foreach ($array ?? [] as $name => $data) {
            if (!is_array($data)) {
                // Old "name = label" format (label may be prefixed with "* ").
                $lines[] = strlen((string) $data)
                    ? "$name $this->keyValueSeparator $data"
                    : (string) $name;
                continue;
            }
            $lines[] = $this->specToLine((string) $name, $data);
        }
        return $lines ? implode("\n", $lines) . "\n" : '';
    }

    public function validateFields($value): bool
    {
        $specs = is_array($value) ? $value : $this->stringToArray($value);
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

    protected function lineToSpec(string $name, string $label, string $type, bool $required, array $valueOptions): array
    {
        $spec = [
            'name' => $name,
            'type' => $this->typeClasses[$type] ?? Element\Text::class,
            'options' => ['label' => $label],
            'attributes' => [],
        ];
        if ($valueOptions) {
            $spec['options']['value_options'] = $valueOptions;
        }
        if ($required) {
            $spec['attributes']['required'] = true;
        }
        // A multi checkbox needs its value handled as an array downstream.
        if ($type === 'multicheckbox') {
            $spec['attributes']['multiple'] = true;
        }
        return $spec;
    }

    protected function specToLine(string $name, array $spec): string
    {
        $label = (string) ($spec['options']['label'] ?? $spec['label'] ?? '');
        $required = !empty($spec['attributes']['required']) || !empty($spec['required']);
        $type = array_search($spec['type'] ?? Element\Text::class, $this->typeClasses, true) ?: 'text';
        $valueOptions = $spec['options']['value_options'] ?? $spec['value_options'] ?? [];

        $line = "$name $this->keyValueSeparator " . ($required ? '* ' : '') . $label;
        if ($type === 'text' && !$valueOptions) {
            return $line;
        }
        $line .= ' | ' . $type;
        if ($valueOptions) {
            $opts = [];
            foreach ($valueOptions as $value => $optLabel) {
                $opts[] = (string) $value === (string) $optLabel
                    ? (string) $optLabel
                    : "$value = $optLabel";
            }
            $line .= ': ' . implode(', ', $opts);
        }
        return $line;
    }

    protected function parseValueOptions(string $string): array
    {
        $result = [];
        foreach (array_filter(array_map('trim', explode(',', $string)), 'strlen') as $option) {
            if (strpos($option, '=') === false) {
                $result[$option] = $option;
            } else {
                [$value, $label] = array_map('trim', explode('=', $option, 2));
                $result[$value] = $label;
            }
        }
        return $result;
    }

    protected function normalizeArray(array $array): array
    {
        $result = [];
        foreach ($array as $name => $data) {
            if (is_array($data)) {
                $result[$name] = $data;
                continue;
            }
            $required = false;
            $label = (string) $data;
            if (strpos($label, '* ') === 0) {
                $required = true;
                $label = trim(substr($label, 2));
            }
            $result[$name] = $this->lineToSpec((string) $name, $label, 'text', $required, []);
        }
        return $result;
    }
}
