<?php
namespace ContactUs\Form;

use Omeka\Form\Element\CkeditorInline;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SiteSettingsFieldset extends Fieldset
{
    protected $label = 'Contact us'; // @translate

    public function init()
    {
        $this
            ->add([
                'name' => 'contactus_notify_recipients',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Emails to notify', // @translate
                    'info' => 'The list of recipients to notify, one by row. First email is used for confirmation.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_notify_recipients',
                    'required' => false,
                    'placeholder' => 'Let empty to use main settings. First email is used for confirmation.
contact@example.org
info@example2.org', // @translate
                    'rows' => 5,
                ],
            ])
            ->add([
                'name' => 'contactus_html',
                'type' => CkeditorInline::class,
                'options' => [
                    'label' => 'Text', // @translate
                    'info' => 'Text to use to explain the aim of the form.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_html',
                ],
            ])
            ->add([
                'name' => 'contactus_confirmation_enabled',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Send a confirmation email', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_confirmation_enabled',
                ],
            ])
            ->add([
                'name' => 'contactus_confirmation_subject',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Subject of the confirmation email', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_confirmation_subject',
                ],
            ])
            ->add([
                'name' => 'contactus_confirmation_body',
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
                'name' => 'contactus_antispam',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Enable simple antispam for visitors', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_antispam',
                ],
            ])
            ->add([
                'name' => 'contactus_questions',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'List of antispam questions/answers', // @translate
                    'info' => 'See the block "Contact us" for a simple list. Separate questions and answer with a "=". Questions may be translated.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_questions',
                    'placeholder' => 'How many are zero plus 1 (in number)? = 1
How many are one plus 1 (in number)? = 2',
                    'rows' => 5,
                ],
            ])
        ;
    }
}
