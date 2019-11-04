<?php
namespace ContactUs\Form;

use Zend\Form\Element;
use Zend\Form\Fieldset;

class SettingsFieldset extends Fieldset
{
    protected $label = 'Contact us'; // @translate

    public function init()
    {
        $this
            ->add([
                'name' => 'contactus_notify_recipients',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Default emails to notify', // @translate
                    'info' => 'The default list of recipients to notify, one by row.', // @translate
                ],
                'attributes' => [
                    'id' => 'contactus_notify_recipients',
                    'required' => false,
                    'placeholder' => 'contact@example.org
info@example2.org',
                    'rows' => 5,
                ],
            ])
        ;
    }
}
