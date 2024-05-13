<?php declare(strict_types=1);

namespace ContactUs\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class SiteSettingsFieldset extends Fieldset
{
    protected $label = 'Contact us'; // @translate

    protected $elementGroups = [
        'contact' => 'Contact', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'contact-us')
            ->setOption('element_groups', $this->elementGroups)
            ->add([
                'name' => 'contactus_notify_recipients',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'contact',
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
                'name' => 'contactus_notify_subject',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Notification email subject for admin', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_notify_subject',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'contactus_notify_body',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Notification message for admin', // @translate
                    'info' => 'Possible placeholders: {main_title}, {main_url}, {site_title}, {site_url}, {email}, {name}, {object}, {subject}, {message}, {newsletter}, {ip}.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_notify_body',
                    'rows' => 5,
                ],
            ])
            ->add([
                'name' => 'contactus_confirmation_enabled',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'contact',
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
                    'element_group' => 'contact',
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
                    'element_group' => 'contact',
                    'label' => 'Confirmation message', // @translate
                    'info' => 'Possible placeholders: {main_title}, {main_url}, {site_title}, {site_url}, {email}, {name}, {object}, {subject}, {message}, {newsletter}, {ip}.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_confirmation_body',
                    'rows' => 5,
                ],
            ])
            ->add([
                'name' => 'contactus_to_author_subject',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Subject of the email to author', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_to_author_subject',
                ],
            ])
            ->add([
                'name' => 'contactus_to_author_body',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Message to the author', // @translate
                    'info' => 'Possible placeholders: {main_title}, {main_url}, {site_title}, {site_url}, {email}, {name}, {object}, {subject}, {message}, {newsletter}, {ip}.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_to_author_body',
                    'rows' => 5,
                ],
            ])
            ->add([
                'name' => 'contactus_antispam',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Enable simple antispam for visitors', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_antispam',
                ],
            ])
            ->add([
                'name' => 'contactus_antispam',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Enable simple antispam for visitors', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_antispam',
                ],
            ])
            ->add([
                'name' => 'contactus_questions',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'List of antispam questions/answers', // @translate
                    'info' => 'See the block "Contact us" for a simple list. Separate questions and answer with a "=". Questions may be translated.', // @translate
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
                'name' => 'contactus_append_resource_show',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Append to resource page (deprecated, for themes without resource block)', // @translate
                    'value_options' => [
                        'items' => 'Items', // @translate
                        'medias' => 'Medias', // @translate
                        'item_sets' => 'Item sets', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'contactus_append_resource_show',
                ],
            ])
            ->add([
                'name' => 'contactus_append_items_browse',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'contact',
                    'label' => 'Append to items browse page', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_append_items_browse',
                ],
            ])
        ;
    }
}
