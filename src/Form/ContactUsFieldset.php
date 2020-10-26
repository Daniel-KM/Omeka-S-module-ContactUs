<?php declare(strict_types=1);
namespace ContactUs\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class ContactUsFieldset extends Fieldset
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][heading]',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Block title', // @translate
                    'info' => 'Heading for the block, if any.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_heading',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][notify_recipients]',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'List of recipients to notify', // @translate
                    'info' => 'Let empty to use site settings.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_notify_recipients',
                    'required' => false,
                    'placeholder' => 'Let empty to use site settings.', // @translate
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][confirmation_enabled]',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Send a confirmation email', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_confirmation_enabled',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][confirmation_subject]',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Subject of the confirmation email', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_confirmation_subject',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][confirmation_body]',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Confirmation message', // @translate
                    'info' => 'Possible placeholders: {main_title}, {main_url}, {site_title}, {site_url}, {email}, {name}, {message}.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_confirmation_body',
                    'rows' => 5,
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][antispam]',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Enable simple antispam for visitors', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_antispam',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][questions]',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'List of antispam questions/answers', // @translate
                    'info' => 'Separate questions and answer with a "=". Questions may be translated.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_questions',
                    'placeholder' => 'How many are zero plus 1 (in number)? = 1
How many are one plus 1 (in number)? = 2',
                    'rows' => 5,
                ],
            ])
        ;
        if (class_exists('BlockPlus\Form\Element\TemplateSelect')) {
            $this
                ->add([
                    'name' => 'o:block[__blockIndex__][o:data][template]',
                    'type' => \BlockPlus\Form\Element\TemplateSelect::class,
                    'options' => [
                        'label' => 'Template to display', // @translate
                        'info' => 'Templates are in folder "common/block-layout" of the theme and should start with "contact-us".', // @translate
                        'template' => 'common/block-layout/contact-us',
                    ],
                    'attributes' => [
                        'class' => 'chosen-select',
                    ],
                ]);
        }
    }
}
