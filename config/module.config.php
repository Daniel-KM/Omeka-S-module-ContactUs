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
        'invokables' => [
            'contactUsSelector' => View\Helper\ContactUsSelector::class,
        ],
        'factories' => [
            'contactUs' => Service\ViewHelper\ContactUsFactory::class,
            'contactUsSelection' => Service\ViewHelper\ContactUsSelectionFactory::class,
            'contactUsSelectionList' => Service\ViewHelper\ContactUsSelectionListFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ContactUsFieldset::class => Form\ContactUsFieldset::class,
            Form\NewsletterFieldset::class => Form\NewsletterFieldset::class,
            Form\SettingsFieldset::class => Form\SettingsFieldset::class ,
            Form\SiteSettingsFieldset::class => Form\SiteSettingsFieldset::class,
        ],
        'factories' => [
            Form\ContactUsForm::class => Service\Form\FormFactory::class,
            Form\NewsletterForm::class => Service\Form\FormFactory::class,
        ],
    ],
    'block_layouts' => [
        'invokables' => [
            'contactUs' => Site\BlockLayout\ContactUs::class,
            'newsletter' => Site\BlockLayout\Newsletter::class,
        ],
    ],
    'resource_page_block_layouts' => [
        'invokables' => [
            'contactUs' => Site\ResourcePageBlockLayout\ContactUs::class,
            'contactUsButton' => Site\ResourcePageBlockLayout\ContactUsButton::class,
            'contactUsSelector' => Site\ResourcePageBlockLayout\ContactUsSelector::class,
        ],
    ],
    'navigation_links' => [
        'invokables' => [
            'contactUsBasket' => Site\Navigation\Link\Basket::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            'ContactUs\Controller\Site\Index' => Service\Controller\IndexControllerFactory::class,
            'ContactUs\Controller\Admin\ContactMessage' => Service\Controller\ContactMessageControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'contact-us' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/contact-us[/:action]',
                            'constraints' => [
                                'action' => 'browse|select',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'ContactUs\Controller\Site',
                                'controller' => 'Index',
                                'action' => 'browse',
                            ],
                        ],
                    ],
                    'guest' => [
                        // The default values for the guest user route are kept
                        // to avoid issues for visitors when an upgrade of
                        // module Guest occurs or when it is disabled.
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/guest',
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'contact-us' => [
                                'type' => \Laminas\Router\Http\Literal::class,
                                'options' => [
                                    'route' => '/contact-us',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'ContactUs\Controller\Site',
                                        'controller' => 'Index',
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
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
            'contact-us' => [
                'type' => \Laminas\Router\Http\Segment::class,
                'options' => [
                    'route' => '/contact-us/:action[/:id]',
                    'constraints' => [
                        'id' => '\d+\.[a-zA-Z0-9]+',
                        'action' => 'zip',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'ContactUs\Controller\Site',
                        'controller' => 'Index',
                        'action' => 'zip',
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            'contact-us' => [
                'label' => 'Contact messages', // @translate
                'class' => 'o-icon- contact-messages fa-envelope',
                'route' => 'admin/contact-message',
                'resource' => 'ContactUs\Controller\Admin\ContactMessage',
                'privilege' => 'browse',
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
    'js_translate_strings' => [
        'The contact message doesn’t exist.', // @translate
    ],
    'contactus' => [
        // Main settings.
        'settings' => [
            'contactus_notify_recipients' => [],
            'contactus_author' => 'disabled',
            'contactus_author_only' => false,
            'contactus_send_with_user_email' => false,
            'contactus_create_zip' => 'original',
            'contactus_delete_zip' => 30,
        ],
        // Site settings.
        'site_settings' => [
            'contactus_notify_recipients' => [],
            'contactus_notify_subject' => '',
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

We will answer you as soon as possible.

Sincerely,

{main_title}
{main_url}

--

{newsletter}
Your message:
Subject: {subject}

{message}', // @translate
            'contactus_confirmation_newsletter_subject' => 'Subscription to newsletter of {main_title}', // @translate
            'contactus_confirmation_newsletter_body' => 'Hi,

Thank you for subscribing to our newsletter.

Sincerely,', // @translate
            'contactus_to_author_subject' => 'Message to the author', // @translate
            'contactus_to_author_body' => 'Hi {user_name},

The visitor {name} ({email} made the following request about a resource on {main_title}:

Thanks to reply directly to the email above and do not use "reply".

Sincerely,

--

From: {name} <{email}>
Subject: {subject}

{message}', // @translate
            'contactus_confirmation_message' => 'Thank you for your message. Check your confirmation email sent to {email}. We will answer you soon.', // @translate
            'contactus_confirmation_message_newsletter' => 'Thank you for subscribing to our newsletter. Check the confirmation email sent to {email}.', // @translate
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
            'contactus_append_resource_show' => [],
            'contactus_append_items_browse' => false,
            'contactus_append_items_browse_individual' => false,
            'contactus_label_selection' => 'Selection for contact', // @translate
            'contactus_label_guest_link' => 'My selection for contact', // @translate
            'contactus_selection_max' => 25,
        ],
        // User settings.
        'user_settings' => [
            'contactus_selected_resources' => [],
        ],
        // Block settings.
        'block_settings' => [
            'contactUs' => [
                'confirmation_enabled' => true,
                'confirmation_subject' => '',
                'confirmation_body' => '',
                'consent_label' => 'I allow the site owner to store my name and my email to answer to this message.', // @translate
                'newsletter' => false,
                'newsletter_label' => 'Subscribe to the newsletter', // @translate
                'fields' => [],
                'attach_file' => false,
                'antispam' => true,
                'questions' => [],
                'recaptcha' => false,
            ],
            'newsletter' => [
                'confirmation_enabled' => true,
                'confirmation_subject' => '',
                'confirmation_body' => '',
                'consent_label' => 'I allow the site owner to store my name and my email to answer to this message.', // @translate
                'unsubscribe' => false,
                'unsubscribe_label' => 'Unsubscribe', // @translate
                'antispam' => true,
                'questions' => [],
                'recaptcha' => false,
            ],
        ],
    ],
];
