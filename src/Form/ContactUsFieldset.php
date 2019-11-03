<?php
namespace ContactUs\Form;

use Zend\Form\Element;
use Zend\Form\Fieldset;

class ContactUsFieldset extends Fieldset
{
    public function init()
    {
        $this
            ->add([
                'name' => 'confirmation_enabled',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Send a confirmation email', // @translate
                ],
                'attributes' => [
                    'id' => 'confirmation_enabled',
                ],
            ])
            ->add([
                'name' => 'confirmation_subject',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Subject of the confirmation email', // @translate
                ],
                'attributes' => [
                    'id' => 'confirmation_subject',
                ],
            ])
            ->add([
                'name' => 'confirmation_body',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Confirmation message', // @translate
                    'info' => 'Possible placeholders: {main_title}, {main_url}, {site_title}, {site_url}, {email}, {name}, {message}.', // @translate
                ],
                'attributes' => [
                    'id' => 'confirmation_body',
                    'rows' => 5,
                ],
            ])
            ->add([
                'name' => 'antispam',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Enable simple antispam for visitors', // @translate
                ],
                'attributes' => [
                    'id' => 'antispam',
                ],
            ])
            ->add([
                'name' => 'questions',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'List of antispam questions/answers', // @translate
                    'info' => 'Separate questions and answer with a "=". Questions may be translated.', // @translate
                ],
                'attributes' => [
                    'id' => 'questions',
                    'rows' => 5,
                ],
            ]);
    }
}
