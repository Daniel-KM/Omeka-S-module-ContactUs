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
                'confirmation_enabled' => true,
                'confirmation_subject' => 'Confirmation contact', // @translate
                'confirmation_body' => 'Hi {name},

Thanks to contact us!

We will answer you soon.

Sincerely,

{main_title}
{main_url}

--

Your message:
Object: {object}

{message}', // @translate
                'antispam' => true,
                'questions' => [
                    'How many are zero plus 1 (in number)?' // @translate
                        => '1',
                    'How many are one plus 1 (in number)?' // @translate
                        => '2',
                    'How many are one plus 2 (in number)?' // @translate
                        => '3',
                    'How many are one plus 3 (in number)?' // @translate
                        => '4',
                    'How many are two plus 1 (in number)?' // @translate
                        => '3',
                    'How many are two plus 2 (in number)?' // @translate
                        => '4',
                    'How many are two plus 3 (in number)?' // @translate
                        => '5',
                    'How many are three plus 1 (in number)?' // @translate
                        => '4',
                    'How many are three plus 2 (in number)?' // @translate
                        => '5',
                    'How many are three plus 3 (in number)?' // @translate
                        => '6',
                ]
            ],
        ],
    ],
];
