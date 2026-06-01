<?php declare(strict_types=1);

namespace ContactUs\Form;

use Common\Form\Element as CommonElement;
use Laminas\EventManager\Event;
use Laminas\EventManager\EventManagerAwareTrait;
use Laminas\Form\Element;
use Laminas\Form\Form;

class QuickSearchForm extends Form
{
    use EventManagerAwareTrait;

    public function init(): void
    {
        $this->setAttribute('method', 'get');

        // GET form: no csrf token in the query string.
        $this->remove('csrf');

        $this
            ->add([
                'name' => 'name',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Sender name', // @translate
                ],
                'attributes' => [
                    'id' => 'name',
                ],
            ])
            ->add([
                'name' => 'email',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Sender email', // @translate
                ],
                'attributes' => [
                    'id' => 'email',
                ],
            ])
            ->add([
                'name' => 'ip',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'IP', // @translate
                ],
                'attributes' => [
                    'id' => 'ip',
                ],
            ])

            ->add([
                'name' => 'is_spam',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Spam', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: .5em;',
                    ],
                    'value_options' => [
                        '' => 'All', // @translate
                        '0' => 'No', // @translate
                        '1' => 'Yes', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'is_spam',
                    'value' => '0',
                ],
            ])
            ->add([
                'name' => 'is_read',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Read', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: .5em;',
                    ],
                    'value_options' => [
                        '' => 'All', // @translate
                        '0' => 'No', // @translate
                        '1' => 'Yes', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'is_read',
                    'value' => '',
                ],
            ])
            ->add([
                'name' => 'has_file',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Has file', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: .5em;',
                    ],
                    'value_options' => [
                        '' => 'All', // @translate
                        '0' => 'No', // @translate
                        '1' => 'Yes', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'has_file',
                    'value' => '',
                ],
            ])
            ->add([
                'name' => 'has_resource',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Has resource', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: .5em;',
                    ],
                    'value_options' => [
                        '' => 'All', // @translate
                        '0' => 'No', // @translate
                        '1' => 'Yes', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'has_resource',
                    'value' => '',
                ],
            ])
            ->add([
                'name' => 'newsletter',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Newsletter', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: .5em;',
                    ],
                    'value_options' => [
                        '' => 'All', // @translate
                        '0' => 'No', // @translate
                        '1' => 'Yes', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'newsletter',
                    'value' => '',
                ],
            ])
            ->add([
                'name' => 'to_author',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'To author', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: .5em;',
                    ],
                    'value_options' => [
                        '' => 'All', // @translate
                        '0' => 'No', // @translate
                        '1' => 'Yes', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'to_author',
                    'value' => '',
                ],
            ]);

        $addEvent = new Event('form.add_elements', $this);
        $this->getEventManager()->triggerEvent($addEvent);

        $this->add([
            'name' => 'submit',
            'type' => Element\Button::class,
            'options' => [
                'label' => 'Search', // @translate
            ],
            'attributes' => [
                'type' => 'submit',
                'class' => 'button',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $event = new Event('form.add_input_filters', $this, ['inputFilter' => $inputFilter]);
        $this->getEventManager()->triggerEvent($event);
    }
}
