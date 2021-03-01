<?php declare(strict_types=1);

namespace ContactUs;

return [
    'api_adapters' => [
        'invokables' => [
            'contact_messages' => Api\Adapter\MessageAdapter::class,
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
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
            Form\SettingsFieldset::class => Form\SettingsFieldset::class ,
            Form\SiteSettingsFieldset::class => Form\SiteSettingsFieldset::class,
        ],
        'factories' => [
            Form\ContactUsForm::class => Service\Form\FormFactory::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            'ContactUs\Controller\Admin\ContactMessage' => Controller\Admin\ContactMessageController::class,
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            'contact-us' => [
                'label' => 'Contact messages', // @translate
                'class' => 'contact-messages far fa-envelope',
                'route' => 'admin/contact-message',
                'resource' => 'ContactUs\Controller\Admin\ContactMessage',
                'privilege' => 'browse',
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'contact-message' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/contact-message',
                            'defaults' => [
                                '__NAMESPACE__' => 'ContactUs\Controller\Admin',
                                'controller' => 'ContactMessage',
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '[/:action]',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                            'id' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:id[/:action]',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
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
            'contactus_subject' => '',
            'contactus_notify_body' => 'A user has contacted you.

email: {email}
name: {name}
ip: {ip}

{newsletter}
subject: {subject}
message:

{message}', // @translate
            'contactus_confirmation_enabled' => true,
            'contactus_confirmation_subject' => 'Confirmation contact', // @translate
            'contactus_confirmation_body' => 'Hi {name},

Thanks to contact us!

We will answer you soon.

Sincerely,

{main_title}
{main_url}

--

{newsletter}
Your message:
Subject: {subject}

{message}', // @translate
            'contactus_newsletter' => false,
            'contactus_newsletter_label' => 'Subscribe to the newsletter', // @translate
            'contactus_attach_file' => false,
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
                'newsletter' => false,
                'newsletter_label' => 'Subscribe to the newsletter', // @translate
                'attach_file' => false,
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
