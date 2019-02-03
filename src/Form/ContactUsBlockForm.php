<?php
namespace ContactUs\Form;

use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Form\Form;

class ContactUsBlockForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data]',
            'type' => Fieldset::class,
        ]);
        $dataFieldset = $this->get('o:block[__blockIndex__][o:data]');

        $dataFieldset->add([
            'name' => 'antispam',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Enable simple antispam for visitors', // @translate
            ],
            'attributes' => [
                'id' => 'antispam',
            ],
        ]);

        $dataFieldset->add([
            'name' => 'questions',
            'type' => Element\Textarea::class,
            'options' => [
                'label' => 'List of antispam questions/answers', // @translate
                'info' => 'Separate questions and answer with a "=". Questions may be translated.', // @translate
            ],
            'attributes' => [
                'id' => 'questions',
                'rows' => 5,
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'o:block[__blockIndex__][o:data]',
            'required' => false,
        ]);
    }
}
