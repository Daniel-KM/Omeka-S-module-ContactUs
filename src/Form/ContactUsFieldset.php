<?php declare(strict_types=1);

namespace ContactUs\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

/**
 * @see \Access\Form\AccessRequestFieldset
 * @see \ContactUs\Form\ContactUsFieldset
 * @see \ContactUs\Form\NewsletterFieldset
 */
class ContactUsFieldset extends Fieldset
{
    public function init(): void
    {
        $this
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
                    'info' => 'Possible placeholders: {main_title}, {main_url}, {site_title}, {site_url}, {email}, {name}, {object}, {subject}, {message}, {newsletter}, {ip}, {resource}, {resource_id}, {resource_title}, {resource_url}, {resource_url_admin}, {resource_link}, {resources}, {resources_ids}, {resources_url}, {resources_url_admin}, {resources_links}.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_confirmation_body',
                    'rows' => 5,
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][attach_file]',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Allow to attach a file', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_attach_file',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][consent_label]',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label for the consent checkbox', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_consent_label',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][newsletter]',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add a checkbox for a newsletter', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_newsletter',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][newsletter_label]',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label for the newsletter', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_newsletter_label',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][fields]',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Other fields to append to form', // @translate
                    'info' => 'Set the name (ascii only and no space) and the label separated by a "=", one by line. The elements may be adapted via the theme. If empty, the site settings or the main settings will be used.', // @translate
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'contactus_fields',
                    'placeholder' => 'phone = Phone', // @translate
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
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'List of antispam questions/answers', // @translate
                    'info' => 'See the block "Contact us" for a simple list. Separate questions and answer with a "=". Questions may be translated.', // @translate
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'contactus_questions',
                    'placeholder' => <<<'TXT'
                        How many are zero plus 1 (in number)? = 1
                        How many are one plus 1 (in number)? = 2
                        TXT, // @translate
                    'rows' => 5,
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][recaptcha]',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Enable Google Recaptcha antispam for visitors', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_recaptcha',
                ],
            ])
        ;
    }
}
