<?php declare(strict_types=1);

namespace ContactUs\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class SettingsFieldset extends Fieldset
{
    protected $label = 'Contact us'; // @translate

    protected $elementGroups = [
        'contact' => 'Contact Us', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'contact-us')
            ->setOption('element_groups', $this->elementGroups);

        $moduleManager = $this->getOption('module_manager');
        $botGuard = $moduleManager ? $moduleManager->getModule('BotGuard') : null;
        $hasBotGuard = $botGuard && $botGuard->getState() === \Omeka\Module\Manager::STATE_ACTIVE;
        if (!$hasBotGuard) {
            $this->add([
                'name' => 'contactus_botguard_note',
                'type' => CommonElement\Note::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Advanced anti-spam protection', // @translate
                    'text' => 'For richer anti-spam protection (Proof-of-Work, rate-limiting, DNSBL, banned IP lists, structured logging), install the BotGuard module.', // @translate
                ],
            ]);
        }

        $this
            ->add([
                'name' => 'contactus_fields',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Specific fields to append to form', // @translate
                    'info' => 'Set the name (ascii only and no space) and the label separated by a "=", one by line. Prefix the label with "* " to make the field required (e.g. "phone = * Phone"). The elements may be adapted via the theme. This setting may be overridden by site or block settings.', // @translate
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'contactus_fields',
                    'placeholder' => 'phone = Phone', // @translate
                ],
            ])

            ->add([
                'name' => 'contactus_sender_email',
                'type' => CommonElement\OptionalEmail::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Email of the sender (else no-reply user or administrator)', // @translate
                    'info' => 'The no-reply email can be set via module EasyAdmin. The administrator email can set above.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_sender_email',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'contactus_sender_name',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Name of the sender when email above is set', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_sender_name',
                    'required' => false,
                ],
            ])

            ->add([
                'name' => 'contactus_notify_recipients',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Emails to notify', // @translate
                    'info' => 'The list of recipients to notify, one by row.', // @translate
                    // TODO Check "The format "name <email>" can be used.", in particular when (string) null is used.
                ],
                'attributes' => [
                    'id' => 'contactus_notify_recipients',
                    'required' => false,
                    'placeholder' => <<<'TXT'
                        contact@example.org
                        info@example2.org
                        TXT, // @translate
                    'rows' => 3,
                ],
            ])

            ->add([
                'name' => 'contactus_reply_to_email',
                'type' => CommonElement\OptionalEmail::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Reply-to address when answering a contact', // @translate
                    'info' => 'Address set as reply-to when an admin answers a message. If empty, the reply-to is the email of the connected admin. The sender (from) remains the unique installation address.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_reply_to_email',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'contactus_reply_subject',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Default subject when answering a contact', // @translate
                    'info' => 'Placeholders: {name}, {email}, {subject}, {message}, {main_title}, {main_url}.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_reply_subject',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'contactus_reply_body',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Default message when answering a contact', // @translate
                    'info' => 'Placeholders: {name}, {email}, {subject}, {message}, {main_title}, {main_url}.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_reply_body',
                    'required' => false,
                    'rows' => 5,
                ],
            ])

            ->add([
                'name' => 'contactus_author',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Property where email of the author is stored', // @translate
                    'info' => 'Allows visitors to contact authors via an email.', // @translate
                    'empty_option' => '',
                    'prepend_value_options' => [
                        'disabled' => 'Disable feature', // @translate
                        'owner' => 'Owner of the resource', // @translate
                    ],
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'contactus_author',
                    'multiple' => false,
                    'required' => false,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])
            ->add([
                'name' => 'contactus_author_only',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Send email to author only, not admins (hidden)', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_author_only',
                ],
            ])
            ->add([
                'name' => 'contactus_send_with_user_email',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Use the user email to send message to author (warning: many email providers reject them as spam)', // @translate
                    'info' => 'This option is not recommended, unless you have a good smtp server. If not set, the user email will be set as reply-to.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_send_with_user_email',
                ],
            ])

            ->add([
                'name' => 'contactus_create_zip',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Zipped files to send', // @translate
                    'value_options' => [
                        'original' => 'Original', // @translate
                        'large' => 'Large', // @translate
                        'medium' => 'Medium', // @translate
                        'square' => 'Square', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'contactus_create_zip',
                    'required' => false,
                    'value' => 'original',
                ],
            ])
            ->add([
                'name' => 'contactus_delete_zip',
                'type' => Element\Number::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Remove zip files after some days', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_delete_zip',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'contactus_pow_skip',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Skip the client-side proof-of-work', // @translate
                    'info' => 'By default, the browser must compute a small SHA-256 hashcash challenge before the form can be submitted. This blocks bots that do not run JavaScript. Invisible for real users (about one second). Check to disable.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_pow_skip',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'contactus_check_dns_mx',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Check mx records of the email domain', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_check_dns_mx',
                    'required' => false,
                ],
            ])
        ;
    }
}
