<?php declare(strict_types=1);

namespace ContactUs\Form;

use Laminas\Form\Fieldset;
use Omeka\Form\Element\ArrayTextarea;

class SettingsFieldset extends Fieldset
{
    protected $label = 'Contact us'; // @translate

    public function init(): void
    {
        $this
            ->add([
                'name' => 'contactus_notify_recipients',
                'type' => ArrayTextarea::class,
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
        ;
    }
}
