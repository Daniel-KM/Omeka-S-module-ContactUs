<?php declare(strict_types=1);

namespace ContactUs\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class SettingsFieldset extends Fieldset
{
    protected $label = 'Contact us'; // @translate

    public function init(): void
    {
        $this
            ->setAttribute('id', 'contact-us')
            ->add([
                'name' => 'contactus_notify_recipients',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Default emails to notify', // @translate
                    'info' => 'The default list of recipients to notify, one by row. First email is used for confirmation.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_notify_recipients',
                    'required' => false,
                    'placeholder' => 'First email is used for confirmation.
contact@example.org
info@example2.org', // @translate
                    'rows' => 5,
                ],
            ])
            ->add([
                'name' => 'contactus_author',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
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
                    'label' => 'Send email to author only, not admins (hidden)', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_author_only',
                ],
            ])
        ;
    }
}
