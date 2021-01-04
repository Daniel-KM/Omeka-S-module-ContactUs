<?php declare(strict_types=1);

namespace ContactUs\Form;

use Laminas\Form\Fieldset;
use Omeka\Form\Element\ArrayTextarea;

class SiteSettingsFieldset extends Fieldset
{
    protected $label = 'Contact us'; // @translate

    public function init(): void
    {
        $this
            ->add([
                'name' => 'contactus_notify_recipients',
                'type' => ArrayTextarea::class,
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
        ;
    }
}
