<?php declare(strict_types=1);

namespace ContactUs\Form;

use Laminas\Filter;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\Validator;

class ContactUsForm extends Form
{
    protected $attachFile = false;
    protected $newsletterLabel = '';
    protected $question = '';
    protected $answer = '';
    protected $checkAnswer = '';
    protected $isAuthenticated = false;

    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);
        $this->attachFile = !empty($options['attach_file']);
        $this->newsletterLabel = $options['newsletter_label'] ?? '';
        $this->question = $options['question'] ?? '';
        $this->answer = $options['answer'] ?? '';
        $this->checkAnswer = $options['check_answer'] ?? '';
        $this->isAuthenticated = !empty($options['is_authenticated']);
    }

    public function init(): void
    {
        $this->setAttribute('class', 'contact-form');
        $this->setName('contact-us');

        // "From" is used instead of "email" to avoid some basic spammers.
        $this
            ->add([
                'name' => 'from',
                'type' => Element\Email::class,
                'options' => [
                    'label' => 'Email', // @translate
                ],
                'attributes' => [
                    'id' => 'from',
                    'required' => !$this->isAuthenticated,
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
            ])
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
                ],
                'attributes' => [
                    'id' => 'message',
                    'required' => true,
                ],
            ])
        ;

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

        if ($this->newsletterLabel) {
            $this
                ->add([
                    'name' => 'newsletter',
                    'type' => Element\Checkbox::class,
                    'options' => [
                        'label' => $this->newsletterLabel, // @translate
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
                'required' => !$this->isAuthenticated,
            ])
            ->add([
                'name' => 'message',
                'required' => true,
                'filters' => [
                    ['name' => Filter\StringTrim::class],
                ],
            ])
        ;
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
                            'callback' => function ($answer) {
                                return $answer === $this->checkAnswer;
                            },
                            'callbackOptions' => [
                                'checkAnswer' => $this->checkAnswer,
                            ],
                        ],
                    ],
                ],
            ]);
        }
    }

    public function setAttachFile($attachFile)
    {
        $this->attachFile = $attachFile;
        return $this;
    }

    public function setNewsletterLabel($newsletterLabel)
    {
        $this->newsletterLabel = $newsletterLabel;
        return $this;
    }

    public function setQuestion($question)
    {
        $this->question = $question;
        return $this;
    }

    public function setAnswer($answer)
    {
        $this->answer = $answer;
        return $this;
    }

    public function setCheckAnswer($checkAnswer)
    {
        $this->checkAnswer = $checkAnswer;
        return $this;
    }

    public function setIsAuthenticated($isAuthenticated)
    {
        $this->isAuthenticated = $isAuthenticated;
        return $this;
    }
}
