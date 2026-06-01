<?php declare(strict_types=1);

namespace ContactUs\Form;

use Laminas\EventManager\EventManagerAwareTrait;
use Laminas\EventManager\Event;
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
    use EventManagerAwareTrait;

    protected $formOptions = [];
    protected $consentLabel = '';
    protected $unsubscribe = false;
    protected $unsubscribeLabel = 'Unsubscribe';
    protected $question = '';
    protected $answer = '';
    protected $checkAnswer = '';
    protected $user = null;
    protected $recaptcha = false;
    protected $powSalt = '';

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
        $this->recaptcha = $options['recaptcha'] ?? false;
        $this->powSalt = (string) ($options['pow_salt'] ?? '');
    }

    public function init(): void
    {
        $this
            ->setAttribute('class', 'newsletter')
            ->setName('newsletter');

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

        $event = new Event('form.add_input_filters', $this, ['inputFilter' => $inputFilter]);
        $this->getEventManager()->triggerEvent($event);
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
