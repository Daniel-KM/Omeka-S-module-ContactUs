<?php declare(strict_types=1);

namespace ContactUs\Form;

use Laminas\Filter;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\Validator;

class ContactUsForm extends Form
{
    protected $question = '';
    protected $answer = '';
    protected $checkAnswer = '';
    protected $isAuthenticated = false;

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
            ]);

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
