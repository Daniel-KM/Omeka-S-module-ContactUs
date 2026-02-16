<?php declare(strict_types=1);

namespace ContactUs\Form;

use Laminas\EventManager\EventManagerAwareTrait;
use Laminas\EventManager\Event;
use Laminas\Filter;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Laminas\Validator;
use Omeka\Entity\User;

class ContactUsForm extends Form
{
    use EventManagerAwareTrait;

    protected $formOptions = [];
    protected $fields = [];
    protected $attachFile = false;
    protected $consentLabel = '';
    protected $newsletterLabel = '';
    protected $question = '';
    protected $answer = '';
    protected $checkAnswer = '';
    protected $user = null;
    protected $isContactAuthor = false;
    protected $recaptcha = false;

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
    }

    public function init(): void
    {
        $this
            ->setAttribute('class', 'contact-form')
            ->setName('contact-us');

        // "From" is used instead of "email" to avoid some basic spammers.
        if ($this->user) {
            if (!empty($this->formOptions['form_display_user_email_hidden'])) {
                $this
                    ->add([
                        'name' => 'from',
                        'type' => Element\Hidden::class,
                        'attributes' => [
                            'id' => 'from',
                            'value' => $this->user->getEmail(),
                            'required' => false,
                        ],
                    ]);
            } else {
                $this
                    ->add([
                        'name' => 'from',
                        'type' => Element\Email::class,
                        'options' => [
                            'label' => 'Email', // @translate
                        ],
                        'attributes' => [
                            'id' => 'from',
                            'value' => $this->user->getEmail(),
                            'readonly' => 'readonly',
                            'pattern' => '[\w\.\-]+@([\w\-]+\.)+[\w\-]{2,}',
                        ],
                    ]);
            }
            if (!empty($this->formOptions['form_display_user_name_hidden'])) {
                $this
                    ->add([
                        'name' => 'name',
                        'type' => Element\Hidden::class,
                        'attributes' => [
                            'id' => 'name',
                            'value' => $this->user->getName(),
                            'required' => false,
                        ],
                    ]);
            } else {
                $this
                    ->add([
                        'name' => 'name',
                        'type' => Element\Text::class,
                        'options' => [
                            'label' => 'Name', // @translate
                        ],
                        'attributes' => [
                            'id' => 'name',
                            'value' => $this->user->getName(),
                            'required' => false,
                        ],
                    ]);
            }
        } else {
            $this
                ->add([
                    'name' => 'from',
                    'type' => Element\Email::class,
                    'options' => [
                        'label' => 'Email', // @translate
                        'label_attributes' => [
                            'class' => 'required',
                        ],
                    ],
                    'attributes' => [
                        'id' => 'from',
                        'required' => true,
                        'pattern' => '[\w\.\-]+@([\w\-]+\.)+[\w\-]{2,}',
                    ],
                ])
                ->add([
                    'name' => 'name',
                    'type' => Element\Text::class,
                    'options' => [
                        'label' => 'Name', // @translate
                    ],
                    'attributes' => [
                        'id' => 'name',
                        'required' => false,
                    ],
                ]);
        }

        $this
            ->add([
                'name' => 'subject',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Subject', // @translate
                ],
                'attributes' => [
                    'id' => 'subject',
                    'required' => false,
                    'maxlength' => 190,
                ],
            ])
            ->add([
                'name' => 'message',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Message', // @translate
                    'label_attributes' => [
                        'class' => 'required',
                    ],
                ],
                'attributes' => [
                    'id' => 'message',
                    'rows' => 10,
                    'required' => true,
                ],
            ])
        ;

        // Keep FieldsFieldset to manage input.
        $fieldsFieldset = $this->appendFields();

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

        // Add an input filter for all fields because the theme may adapt them.
        if ($fieldsFieldset) {
            $inputFilterFields = $inputFilter->get('fields');
            foreach ($fieldsFieldset->getElements() as $name => $element) {
                $isRequired = (bool) $element->getAttribute('required');
                if (!$isRequired) {
                    $inputFilterFields
                        ->add([
                            'name' => $name,
                            'required' => false,
                        ]);
                }
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
     * Fields are passed from theme, so may be badly or partially formatted.
     */
    protected function appendFields(): ?Fieldset
    {
        if (empty($this->fields)) {
            return null;
        }

        $this
            ->add([
                'name' => 'fields',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'More info', // @translate
                ],
                'attributes' => [
                    'id' => 'fields',
                    'required' => false,
                ],
            ]);

        /** @var \Laminas\Form\Fieldset $fieldsFieldset */
        $fieldsFieldset = $this->get('fields');

        $onlyHidden = true;

        foreach ($this->fields as $name => $data) {
            // Update original field to prepare field and required input field.
            if (!is_array($data)) {
                $data = [
                    'label' => $data,
                    'type' => Element\Text::class,
                    'options' => [],
                    'attributes' => [],
                ];
                $this->fields[$name] = $data;
            } else {
                $data['options'] ??= [];
                $data['attributes'] ??= [];
            }

            // "value", "multiple", "required", and "class" should be passed as
            // keys of "attributes". First level keys are kept for compatibility
            // with old themes.
            $fieldValue = $data['value'] ?? $data['attributes']['value'] ?? null;
            $isMultiple = is_array($fieldValue)
                || !empty($data['attributes']['multiple'])
                || $name === 'id'
                || substr($name, -2) === '[]';
            $isRequired = isset($data['required'])
                ? !empty($data['required'])
                : !empty($data['attributes']['required']);
            $class = $data['class'] ?? $data['attributes']['class'] ?? '';
            $nameNotArray = substr($name, -2) === '[]' ? substr($name, -2) : $name;

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
                    $fieldsFieldset
                        ->add([
                            'name' => $nameNotArray,
                            'type' => Element\Hidden::class,
                            'attributes' => [
                                'id' => 'fields-' . $nameNotArray,
                                'class' => $class,
                                'value' => json_encode($fieldValue, 320),
                            ],
                        ]);
                } else {
                    $onlyHidden = false;
                    $fieldsFieldset
                        ->add([
                            'name' => $nameNotArray,
                            'type' => $fieldType,
                            'options' => [
                                'label' => $data['label'] ?? $data['options']['label'] ?? null,
                                'value_options' => $data['value_options'] ?? $data['options']['value_options'] ?? [],
                            ] + $data['options'],
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
                $isHidden = strtolower($fieldType) === 'hidden' || $fieldType === Element\Hidden::class;
                $onlyHidden = $onlyHidden && $isHidden;
                $fieldsFieldset
                    ->add([
                        'name' => $nameNotArray,
                        'type' => $fieldType,
                        'options' => [
                            'label' => $data['label'] ?? $data['options']['label'] ?? null,
                            'value_options' => $data['value_options'] ?? $data['options']['value_options'] ?? [],
                        ] + $data['options'],
                        'attributes' => [
                            'id' => 'fields-' . $name,
                            'class' => $class,
                            'value' => $fieldValue,
                            'required' => $isRequired,
                        ] + $data['attributes'],
                    ]);
            }
        }

        if ($onlyHidden) {
            $fieldsFieldset
                ->setAttribute('class', 'hidden');
        }

        return $fieldsFieldset;
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
