<?php declare(strict_types=1);

namespace ContactUs\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class SendMessageForm extends Form
{
    public function init(): void
    {
        $this
            ->setAttribute('id', 'contactus-send-message-form')
            ->setAttribute('class', 'form-send-message jsend-form')
            ->setAttribute('method', 'post')
            ->setName('send-message');

        $this
            ->add([
                'name' => 'subject',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Subject', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus-send-subject',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'body',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Message', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus-send-body',
                    'rows' => 10,
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'myself',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Copy to me', // @translate
                    'value_options' => [
                        '' => 'No copy', // @translate
                        'cc' => 'Copy (cc)', // @translate
                        'bcc' => 'Hidden copy (bcc)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'contactus-send-myself',
                    'value' => '',
                ],
            ])
            ->add([
                'name' => 'submit',
                'type' => Element\Button::class,
                'options' => [
                    'label' => 'Send message', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus-send-submit',
                    'type' => 'submit',
                    'class' => 'submit',
                    'data-spinner' => 'true',
                ],
            ])
        ;
    }
}
