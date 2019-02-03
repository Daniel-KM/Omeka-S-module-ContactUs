<?php
namespace ContactUs;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'block_layouts' => [
        'factories' => [
            'contactUs' => Service\BlockLayout\ContactUsFactory::class,
        ],
    ],
    'form_elements' => [
        'factories' => [
            Form\ContactUsBlockForm::class => Service\Form\FormFactory::class,
            Form\ContactUsForm::class => Service\Form\FormFactory::class,
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'contactus' => [
        'block_settings' => [
            'contactUs' => [
                'antispam' => true,
                'questions' => [
                    'How many are zero plus 1 (in number)?' => '1',
                    'How many are one plus 1 (in number)?' => '2',
                    'How many are one plus 2 (in number)?' => '3',
                    'How many are one plus 3 (in number)?' => '4',
                    'How many are two plus 1 (in number)?' => '3',
                    'How many are two plus 2 (in number)?' => '4',
                    'How many are two plus 3 (in number)?' => '5',
                    'How many are three plus 1 (in number)?' => '4',
                    'How many are three plus 2 (in number)?' => '5',
                    'How many are three plus 3 (in number)?' => '6',
                ]
            ],
        ],
    ],
];
