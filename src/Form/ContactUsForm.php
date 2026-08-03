<?php declare(strict_types=1);

namespace ContactUs\Form;

use Laminas\EventManager\EventManagerAwareTrait;
use Laminas\EventManager\Event;
use Laminas\Filter;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\Validator;
use Omeka\Entity\User;

class ContactUsForm extends Form
{
    use EventManagerAwareTrait;

    /**
     * Core content fields, in canonical order, that a custom fields definition
     * may override.
     */
    protected const CORE_CONTENT = [
        'from',
        'name',
        'subject',
        'message',
    ];

    /**
     * Map of the user-facing names accepted in the fields definition to the
     * canonical core content field they reposition or relabel.
     */
    protected const FIELD_ALIASES = [
        'from' => 'from',
        'email' => 'from',
        'name' => 'name',
        'subject' => 'subject',
        'message' => 'message',
        'body' => 'message',
    ];

    /**
     * Names reserved by the form internals.
     *
     * A custom field should not use them.
     * "id" is allowed for attached resource ids.
     */
    protected const RESERVED_NAMES = [
        'contact_website',
        'file',
        'consent',
        'newsletter',
        'answer',
        'check',
        'pow_nonce',
        'submit',
    ];

    protected $formOptions = [];
    protected $fields = [];
    protected $customFieldNames = [];
    protected $attachFile = false;
    protected $consentLabel = '';
    protected $newsletterLabel = '';
    protected $question = '';
    protected $answer = '';
    protected $checkAnswer = '';
    protected $user = null;
    protected $isContactAuthor = false;
    protected $recaptcha = false;
    protected $powSalt = '';

    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);
        $this->formOptions = $options['formOptions'] ?? [];
        $this->fields = $options['fields'] ?? [];
        $this->attachFile = !empty($options['attach_file']);
        $this->consentLabel = $options['consent_label'] ?? '';
        $this->newsletterLabel = $options['newsletter_label'] ?? '';
        $this->question = $options['question'] ?? '';
        $this->answer = $options['answer'] ?? '';
        $this->checkAnswer = $options['check_answer'] ?? '';
        $this->user = $options['user'] ?? null;
        $this->isContactAuthor = ($options['contact'] ?? null) === 'author';
        $this->recaptcha = $options['recaptcha'] ?? false;
        $this->powSalt = (string) ($options['pow_salt'] ?? '');
    }

    public function init(): void
    {
        $this
            ->setAttribute('class', 'contact-form')
            ->setName('contact-us');

        // Honeypot. Hidden by CSS, aria and tabindex; bots fill it, users
        // don't. The check is performed server-side in the ContactUs view
        // helper.
        $this
            ->add([
                'name' => 'contact_website',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Website', // @translate
                ],
                'attributes' => [
                    'id' => 'contact_website',
                    'tabindex' => '-1',
                    'autocomplete' => 'off',
                    'aria-hidden' => 'true',
                    'style' => 'position:absolute;left:-10000px;width:1px;height:1px;opacity:0;',
                ],
            ]);

        // Build the content fields (email, name, subject, message) and the
        // custom fields as a single flat list.
        // The order comes from the custom fields definition.
        // A line whose name matches a core field repositions or relabels it,
        // any other line is a custom field. When no core field is listed, the
        // default order is kept.
        $partition = self::partitionFields($this->fields);
        $this->customFieldNames = array_keys($partition['custom']);
        $coreSpecs = $this->coreContentSpecs($partition['relabel']);
        foreach ($partition['order'] as $item) {
            if ($item['kind'] === 'core') {
                $this->add($coreSpecs[$item['name']]);
            } else {
                $this->addCustomField($item['name'], $item['spec']);
            }
        }

        if ($this->attachFile) {
            $this
                ->add([
                    'name' => 'file',
                    'type' => Element\File::class,
                    'options' => [
                        'label' => 'Attach a file', // @translate
                    ],
                    'attributes' => [
                        'id' => 'file',
                    ],
                ]);
        }

        $useHiddenConsent = $this->user || !$this->consentLabel;
        if ($useHiddenConsent) {
            $this
                ->add([
                    'name' => 'consent',
                    'type' => Element\Hidden::class,
                    'attributes' => [
                        'id' => 'consent',
                        'value' => true,
                    ],
                ]);
        } else {
            $this
                ->add([
                    'name' => 'consent',
                    'type' => Element\Checkbox::class,
                    'options' => [
                        'label' => $this->consentLabel,
                        'label_attributes' => [
                            'class' => 'required',
                        ],
                    ],
                    'attributes' => [
                        'id' => 'consent',
                        'required' => true,
                    ],
                ]);
        }

        if ($this->newsletterLabel) {
            $this
                ->add([
                    'name' => 'newsletter',
                    'type' => Element\Checkbox::class,
                    'options' => [
                        'label' => $this->newsletterLabel,
                        'use_hidden_element' => true,
                        'unchecked_value' => 'no', // @translate
                        'checked_value' => 'yes', // @translate
                    ],
                    'attributes' => [
                        'id' => 'newsletter',
                        'required' => false,
                    ],
                ]);
        }

        if ($this->question) {
            $this
                ->add([
                    'name' => 'answer',
                    'type' => Element\Text::class,
                    'options' => [
                        'label' => $this->question,
                        'label_attributes' => [
                            'class' => 'required',
                        ],
                    ],
                    'attributes' => [
                        'id' => 'answer',
                        'required' => true,
                    ],
                ])
                ->add([
                    'name' => 'check',
                    'type' => Element\Hidden::class,
                    'attributes' => [
                        'value' => substr(md5($this->question), 0, 16),
                    ],
                ]);
        }

        if ($this->recaptcha) {
            $this->add([
                'type' => \Omeka\Form\Element\Recaptcha::class,
            ]);
        }

        if ($this->powSalt !== '') {
            $this
                ->setAttribute('data-pow-salt', $this->powSalt)
                ->setAttribute('data-pow-difficulty', '4')
                ->add([
                    'name' => 'pow_nonce',
                    'type' => Element\Hidden::class,
                    'attributes' => [
                        'id' => 'pow_nonce',
                        'value' => '',
                    ],
                ]);
        }

        $this
            ->add([
                'name' => 'submit',
                'type' => Element\Submit::class,
                'attributes' => [
                    'id' => 'submit',
                    'value' => 'Send message', // @translate
                ],
            ]);

        $event = new Event('form.add_elements', $this);
        $this->getEventManager()->triggerEvent($event);

        $inputFilter = $this->getInputFilter();
        $inputFilter
            ->add([
                'name' => 'from',
                'required' => empty($this->user),
            ])
            ->add([
                'name' => 'message',
                'required' => true,
                'filters' => [
                    ['name' => Filter\StringTrim::class],
                ],
            ])
            ->add([
                'name' => 'subject',
                'required' => false,
                'filters' => [
                    ['name' => Filter\StringTrim::class],
                ],
                'validators' => [
                    [
                        'name' => Validator\StringLength::class,
                        'options' => [
                            'max' => 190,
                        ],
                    ],
                ],
            ])
        ;

        if ($this->newsletterLabel) {
            $inputFilter
                ->add([
                    'name' => 'newsletter',
                    'required' => false,
                ]);
        }

        // Add an input filter for all custom fields (theme or settings may
        // adapt them). They are now flat elements of the main form.
        foreach ($this->customFieldNames as $name) {
            if (!$this->has($name)) {
                continue;
            }
            if (!$this->get($name)->getAttribute('required')) {
                $inputFilter->add([
                    'name' => $name,
                    'required' => false,
                ]);
            }
        }

        if ($this->question) {
            $inputFilter->add([
                'name' => 'answer',
                'required' => true,
                'filters' => [
                    ['name' => Filter\StringTrim::class],
                ],
                'validators' => [
                    [
                        'name' => Validator\Callback::class,
                        'options' => [
                            'callback' => fn ($answer) => $answer === $this->checkAnswer,
                        ],
                    ],
                ],
            ]);
        }

        $event = new Event('form.add_input_filters', $this, ['inputFilter' => $inputFilter]);
        $this->getEventManager()->triggerEvent($event);
    }

    /**
     * Split the fields definition into the core fields to reposition/relabel
     * and the custom fields to add, keeping the render order.
     *
     * @param array $fields Fields specifications keyed by name.
     * @return array{order: array, relabel: array, custom: array}
     */
    public static function partitionFields(array $fields): array
    {
        $order = [];
        $relabel = [];
        $custom = [];
        $seenCore = [];
        foreach ($fields as $name => $spec) {
            $lower = strtolower((string) $name);
            if (isset(self::FIELD_ALIASES[$lower])) {
                $core = self::FIELD_ALIASES[$lower];
                if (isset($seenCore[$core])) {
                    continue;
                }
                $seenCore[$core] = true;
                $label = is_array($spec)
                    ? (string) ($spec['options']['label'] ?? $spec['label'] ?? '')
                    : (string) $spec;
                if (strpos($label, '* ') === 0) {
                    $label = trim(substr($label, 2));
                }
                if ($label !== '') {
                    $relabel[$core] = $label;
                }
                $order[] = ['kind' => 'core', 'name' => $core];
                continue;
            }
            if (in_array($lower, self::RESERVED_NAMES, true)) {
                continue;
            }
            $custom[$name] = $spec;
            $order[] = ['kind' => 'custom', 'name' => $name, 'spec' => $spec];
        }

        if (!$seenCore) {
            // No core field listed: keep the default order (core content fields
            // first, then the custom fields), for backward compatibility.
            $order = [];
            foreach (self::CORE_CONTENT as $core) {
                $order[] = ['kind' => 'core', 'name' => $core];
            }
            foreach ($custom as $name => $spec) {
                $order[] = ['kind' => 'custom', 'name' => $name, 'spec' => $spec];
            }
        } else {
            // Append the core fields not explicitly positioned, in canonical
            // order, after the listed ones.
            foreach (self::CORE_CONTENT as $core) {
                if (empty($seenCore[$core])) {
                    $order[] = ['kind' => 'core', 'name' => $core];
                }
            }
        }

        return ['order' => $order, 'relabel' => $relabel, 'custom' => $custom];
    }

    /**
     * Build the specifications of the core content fields, honoring the user
     * state and an optional relabel from the fields definition.
     *
     * @param array $relabel Canonical core name to overridden label.
     * @return array Specifications keyed by canonical core name.
     */
    protected function coreContentSpecs(array $relabel): array
    {
        return [
            'from' => $this->specFrom($relabel['from'] ?? null),
            'name' => $this->specName($relabel['name'] ?? null),
            'subject' => $this->specSubject($relabel['subject'] ?? null),
            'message' => $this->specMessage($relabel['message'] ?? null),
        ];
    }

    protected function specFrom(?string $label): array
    {
        // "From" is used instead of "email" to avoid some basic spammers.
        if ($this->user) {
            if (!empty($this->formOptions['form_display_user_email_hidden'])) {
                return [
                    'name' => 'from',
                    'type' => Element\Hidden::class,
                    'attributes' => [
                        'id' => 'from',
                        'value' => $this->user->getEmail(),
                        'required' => false,
                    ],
                ];
            }
            return [
                'name' => 'from',
                'type' => Element\Email::class,
                'options' => [
                    'label' => $label ?? 'Email', // @translate
                ],
                'attributes' => [
                    'id' => 'from',
                    'value' => $this->user->getEmail(),
                    'readonly' => 'readonly',
                    'pattern' => '[\w\.\-]+@([\w\-]+\.)+[\w\-]{2,}',
                ],
            ];
        }
        return [
            'name' => 'from',
            'type' => Element\Email::class,
            'options' => [
                'label' => $label ?? 'Email', // @translate
                'label_attributes' => [
                    'class' => 'required',
                ],
            ],
            'attributes' => [
                'id' => 'from',
                'required' => true,
                'pattern' => '[\w\.\-]+@([\w\-]+\.)+[\w\-]{2,}',
            ],
        ];
    }

    protected function specName(?string $label): array
    {
        if ($this->user && !empty($this->formOptions['form_display_user_name_hidden'])) {
            return [
                'name' => 'name',
                'type' => Element\Hidden::class,
                'attributes' => [
                    'id' => 'name',
                    'value' => $this->user->getName(),
                    'required' => false,
                ],
            ];
        }
        return [
            'name' => 'name',
            'type' => Element\Text::class,
            'options' => [
                'label' => $label ?? 'Name', // @translate
            ],
            'attributes' => [
                'id' => 'name',
                'value' => $this->user ? $this->user->getName() : null,
                'required' => false,
            ],
        ];
    }

    protected function specSubject(?string $label): array
    {
        return [
            'name' => 'subject',
            'type' => Element\Text::class,
            'options' => [
                'label' => $label ?? 'Subject', // @translate
            ],
            'attributes' => [
                'id' => 'subject',
                'required' => false,
                'maxlength' => 190,
            ],
        ];
    }

    protected function specMessage(?string $label): array
    {
        return [
            'name' => 'message',
            'type' => Element\Textarea::class,
            'options' => [
                'label' => $label ?? 'Message', // @translate
                'label_attributes' => [
                    'class' => 'required',
                ],
            ],
            'attributes' => [
                'id' => 'message',
                'rows' => 10,
                'required' => true,
            ],
        ];
    }

    /**
     * Add a custom field as a flat element of the main form.
     *
     * Fields may be passed from theme or settings, so may be badly or partially
     * formatted.
     *
     * @param string $name
     * @param array|string $data
     */
    protected function addCustomField(string $name, $data): void
    {
        // Update original field to prepare field and required input field.
        if (!is_array($data)) {
            // Support "* Label" syntax: a leading "* " marks the field as
            // required, e.g. "phone = * Phone" in settings.
            $label = (string) $data;
            $fieldRequired = false;
            if (strpos($label, '* ') === 0) {
                $label = substr($label, 2);
                $fieldRequired = true;
            }
            $data = [
                'label' => $label,
                'type' => Element\Text::class,
                'required' => $fieldRequired,
                'options' => [],
                'attributes' => [],
            ];
        } else {
            $data['options'] ??= [];
            $data['attributes'] ??= [];
        }

        // "value", "multiple", "required", and "class" should be passed as keys
        // of "attributes". First level keys are kept for compatibility with old
        // themes.
        $fieldValue = $data['value'] ?? $data['attributes']['value'] ?? null;
        $isMultiple = is_array($fieldValue)
            || !empty($data['attributes']['multiple'])
            || $name === 'id'
            || substr($name, -2) === '[]';
        $isRequired = isset($data['required'])
            ? !empty($data['required'])
            : !empty($data['attributes']['required']);
        $class = $data['class'] ?? $data['attributes']['class'] ?? '';
        $nameNotArray = substr($name, -2) === '[]' ? substr($name, 0, -2) : $name;

        if ($isMultiple) {
            // The value should be an array.
            if ($fieldValue === null || $fieldValue === '' || $fieldValue === []) {
                $fieldValue = [];
            } elseif (!is_array($fieldValue)) {
                $fieldValueJson = json_decode($fieldValue, true);
                $fieldValue = is_array($fieldValueJson) ? $fieldValueJson : [$fieldValue];
            }
            $fieldType = $data['type'] ?? Element\Select::class;
            $isHidden = strtolower($fieldType) === 'hidden' || $fieldType === Element\Hidden::class;
            if ($isHidden) {
                $this->add([
                    'name' => $nameNotArray,
                    'type' => Element\Hidden::class,
                    'attributes' => [
                        'id' => 'fields-' . $nameNotArray,
                        'class' => $class,
                        'value' => json_encode($fieldValue, 320),
                    ],
                ]);
            } else {
                $multiFieldOptions = [
                    'label' => $data['label'] ?? $data['options']['label'] ?? null,
                    'value_options' => $data['value_options'] ?? $data['options']['value_options'] ?? [],
                ] + $data['options'];
                if ($isRequired) {
                    $multiFieldOptions['label_attributes'] = ['class' => 'required'];
                }
                $this->add([
                    'name' => $nameNotArray,
                    'type' => $fieldType,
                    'options' => $multiFieldOptions,
                    'attributes' => [
                        'id' => 'fields-' . $nameNotArray,
                        // Kept for compatibility. Use attributes instead.
                        'class' => $class,
                        'value' => $fieldValue,
                        'required' => $isRequired,
                    ] + $data['attributes'],
                ]);
            }
        } else {
            // The value should be a scalar or a string.
            $fieldValue = isset($data['value'])
                ? (is_array($data['value']) ? json_encode($data['value'], 320) : (string) $data['value'])
                : '';
            $fieldType = $data['type'] ?? Element\Text::class;
            $fieldOptions = [
                'label' => $data['label'] ?? $data['options']['label'] ?? null,
                'value_options' => $data['value_options'] ?? $data['options']['value_options'] ?? [],
            ] + $data['options'];
            if ($isRequired) {
                $fieldOptions['label_attributes'] = ['class' => 'required'];
            }
            $this->add([
                'name' => $nameNotArray,
                'type' => $fieldType,
                'options' => $fieldOptions,
                'attributes' => [
                    'id' => 'fields-' . $name,
                    'class' => $class,
                    'value' => $fieldValue,
                    'required' => $isRequired,
                ] + $data['attributes'],
            ]);
        }
    }

    public function setFormOptions(array $formOptions): self
    {
        $this->formOptions = $formOptions;
        return $this;
    }

    public function setFields(?array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }

    public function setAttachFile($attachFile): self
    {
        $this->attachFile = $attachFile;
        return $this;
    }

    public function setConsentLabel($consentLabel): self
    {
        $this->consentLabel = $consentLabel;
        return $this;
    }

    public function setNewsletterLabel($newsletterLabel): self
    {
        $this->newsletterLabel = $newsletterLabel;
        return $this;
    }

    public function setQuestion($question): self
    {
        $this->question = $question;
        return $this;
    }

    public function setAnswer($answer): self
    {
        $this->answer = $answer;
        return $this;
    }

    public function setCheckAnswer($checkAnswer): self
    {
        $this->checkAnswer = $checkAnswer;
        return $this;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function setIsContactAuthor(bool $isContactAuthor): self
    {
        $this->isContactAuthor = $isContactAuthor;
        return $this;
    }
}
