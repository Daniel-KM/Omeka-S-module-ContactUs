<?php declare(strict_types=1);

namespace ContactUs\Form;

use Laminas\Filter;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\Validator;
use Omeka\Entity\User;

class ContactUsForm extends Form
{
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

        foreach ($this->fields ?? [] as $name => $data) {
            if (!is_array($data)) {
                $data = [
                    'label' => $data,
                    'type' => Element\Text::class,
                ];
                // Update original fields to prepare required input fields below.
                $this->fields[$name] = $data;
            }
            // Manage multiple attached resource ids.
            if ($name === 'id') {
                $name = 'id[]';
            }
            $isMultiple = substr($name, -2) === '[]';
            if ($isMultiple) {
                $fieldType = $data['type'] ?? Element\Select::class;
                $fieldValue = isset($data['value']) ? (is_array($data['value']) ? $data['value'] : [$data['value']]) : [];
                if (strtolower($fieldType) === 'hidden' || $fieldType === Element\Hidden::class) {
                    $fieldValue = json_encode($fieldValue);
                }
                $this
                    ->add([
                        'name' => 'fields[' . substr($name, 0, -2) . '][]',
                        'type' => $fieldType,
                        'options' => [
                            'label' => $data['label'] ?? null,
                            'value_options' => $data['value_options'] ?? [],
                        ],
                        'attributes' => [
                            'id' => 'fields-' . substr($name, 0, -2),
                            'class' => $data['class'] ?? '',
                            'multiple' => 'multiple',
                            'value' => $fieldValue,
                            'required' => !empty($data['required']),
                        ],
                    ]);
            } else {
                $fieldValue = isset($data['value']) ? (is_array($data['value']) ? json_encode($data['value'], 320) : (string) $data['value']) : '';
                $this
                    ->add([
                        'name' => 'fields[' . $name . ']',
                        'type' => $data['type'] ?? Element\Text::class,
                        'options' => [
                            'label' => $data['label'] ?? null,
                            'value_options' => $data['value_options'] ?? [],
                        ],
                        'attributes' => [
                            'id' => 'fields-' . $name,
                            'class' => $data['class'] ?? '',
                            'value' => $fieldValue,
                            'required' => !empty($data['required']),
                        ],
                    ]);
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

        if ($this->user || !$this->consentLabel) {
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

        $this
            ->add([
                'name' => 'submit',
                'type' => Element\Submit::class,
                'attributes' => [
                    'id' => 'submit',
                    'value' => 'Send message', // @translate
                ],
            ]);

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
        ;

        // Add an input filter for all fields because the theme may adapt them.
        foreach ($this->fields ?? [] as $name => $data) {
            // Manage multiple attached resource ids.
            if ($name === 'id') {
                $name = 'id[]';
            }
            if (empty($data['required'])) {
                $isMultiple = substr($name, -2) === '[]';
                $inputFilter
                    ->add([
                        'name' => $isMultiple
                            ? 'fields[' . substr($name, 0, -2) . '][]'
                            : 'fields[' . $name . ']',
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
