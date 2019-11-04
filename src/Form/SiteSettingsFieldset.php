<?php
namespace ContactUs\Form;

use Zend\Form\Element;
use Zend\Form\Fieldset;

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
                    'info' => 'The list of recipients to notify, one by row.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_notify_recipients',
                    'required' => false,
                    'placeholder' => 'Let empty to use main settings.
contact@example.org
info@example2.org', // @translate
                    'rows' => 5,
                ],
            ])
        ;
    }
}
