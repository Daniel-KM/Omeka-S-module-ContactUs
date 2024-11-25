<?php declare(strict_types=1);

namespace ContactUs\Form;

use Laminas\Filter;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\Validator;
use Omeka\Entity\User;

/**
 * A simplified version of ContactUsForm.
 *
 * @see \ContactUs\Form\ContactUsForm
 */
class NewsletterForm extends Form
{
    protected $formOptions = [];
    protected $consentLabel = '';
    protected $unsubscribe = false;
    protected $unsubscribeLabel = 'Unsubscribe';
    protected $question = '';
    protected $answer = '';
    protected $checkAnswer = '';
    protected $user = null;

    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);
        $this->formOptions = $options['formOptions'] ?? [];
        $this->consentLabel = $options['consent_label'] ?? '';
        $this->unsubscribe = !empty($options['unsubscribe']);
        $this->unsubscribeLabel = $options['unsubscribe_label'] ?? 'Unsubscribe';
        $this->question = $options['question'] ?? '';
        $this->answer = $options['answer'] ?? '';
        $this->checkAnswer = $options['check_answer'] ?? '';
        $this->user = $options['user'] ?? null;
    }

    public function init(): void
    {
        $this
            ->setAttribute('class', 'newsletter')
            ->setName('newsletter');

        // "From" is used instead of "email" to avoid some basic spammers.
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
                    'value' => $this->user ? $this->user->getEmail() : '',
                    'required' => true,
                    'pattern' => '[\w\.\-]+@([\w\-]+\.)+[\w\-]{2,}',
                ],
            ]);

        if ($this->unsubscribe) {
            $this
                ->add([
                    'name' => 'unsubscribe',
                    'type' => Element\Checkbox::class,
                    'options' => [
                        'label' => $this->unsubscribeLabel ?: 'Unsubscribe',
                        'label_attributes' => [
                            'class' => 'required',
                        ],
                    ],
                    'attributes' => [
                        'id' => 'unsubscribe',
                        'required' => true,
                    ],
                ]);
        } else {
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

    public function setConsentLabel($consentLabel): self
    {
        $this->consentLabel = $consentLabel;
        return $this;
    }

    public function setUnsubscribe($unsubscribe): self
    {
        $this->unsubscribe = (bool) $unsubscribe;
        return $this;
    }

    public function setUnsubscribeLabel($unsubscribeLabel): self
    {
        $this->unsubscribeLabel = $unsubscribeLabel;
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
}
