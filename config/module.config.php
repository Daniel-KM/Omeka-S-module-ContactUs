<?php declare(strict_types=1);
namespace ContactUs;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'contactUs' => Service\ViewHelper\ContactUsFactory::class,
        ],
    ],
    'block_layouts' => [
        'invokables' => [
            'contactUs' => Site\BlockLayout\ContactUs::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ContactUsFieldset::class => Form\ContactUsFieldset::class,
            Form\ContactUsForm::class => Form\ContactUsForm::class,
            Form\SettingsFieldset::class => Form\SettingsFieldset::class ,
            Form\SiteSettingsFieldset::class => Form\SiteSettingsFieldset::class,
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
        'settings' => [
            'contactus_notify_recipients' => [],
        ],
        'site_settings' => [
            'contactus_notify_recipients' => [],
            'contactus_html' => '',
            'contactus_confirmation_enabled' => true,
            'contactus_confirmation_subject' => 'Confirmation contact', // @translate
            'contactus_confirmation_body' => 'Hi {name},

Thanks to contact us!

We will answer you soon.

Sincerely,

{main_title}
{main_url}

--

Your message:
Subject: {subject}

{message}', // @translate
            'contactus_antispam' => true,
            'contactus_questions' => [
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
            ],
        ],
        'block_settings' => [
            'contactUs' => [
                'heading' => null,
                'notify_recipients' => [],
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
Subject: {subject}

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
                ],
                'template' => '',
            ],
        ],
    ],
];
