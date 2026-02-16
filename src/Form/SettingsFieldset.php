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
            ->setOption('element_groups', $this->elementGroups)

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
        ;
    }
}
