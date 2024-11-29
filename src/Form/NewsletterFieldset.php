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
class NewsletterFieldset extends Fieldset
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][unsubscribe]',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Checkbox to unsubscribe', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_unsubscribe',
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
                    'info' => 'Possible placeholders: {main_title}, {main_url}, {site_title}, {site_url}, {email}, {name}, {object}, {subject}, {message}, {newsletter}, {ip}.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_confirmation_body',
                    'rows' => 5,
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][unsubscribe_label]',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label for the unsubscribe checkbox', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_unsubscribe_label',
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
                    'info' => 'See the block "Newsletter" for a simple list. Separate questions and answer with a "=". Questions may be translated.', // @translate
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'contactus_questions',
                    'placeholder' => 'How many are zero plus 1 (in number)? = 1
How many are one plus 1 (in number)? = 2', // @translate
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
