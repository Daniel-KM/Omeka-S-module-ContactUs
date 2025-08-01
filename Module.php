<?php declare(strict_types=1);

namespace ContactUs;

if (!class_exists('Common\TraitModule', false)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Omeka\Module\AbstractModule;

/**
 * Contact Us
 *
 * @copyright Daniel Berthereau, 2018-2025
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    const STORE_PREFIX = 'contactus';

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        // Since Omeka 1.4, modules are ordered, so Guest come after Selection.
        if (!$acl->hasRole('guest')) {
            $acl->addRole('guest');
        }
        if (!$acl->hasRole('guest_private')) {
            $acl->addRole('guest_private');
        }
        $roles = $acl->getRoles();
        $adminRoles = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
        ];
        $userRoles = array_diff($roles, $adminRoles);
        // A check is done on attached files for anonymous people and guests.
        $acl
            // Any user or anonymous people can create a message.
            ->allow(
                null,
                [
                    Entity\Message::class,
                    Api\Adapter\MessageAdapter::class,
                ],
                ['create']
            )
            // Users can read their own messages but cannot delete them once
            // sent.
            ->allow(
                $userRoles,
                [Entity\Message::class],
                ['read'],
                new \Omeka\Permissions\Assertion\OwnsEntityAssertion
            )
            // The search is limited to own messages directly inside adapter.
            ->allow(
                $userRoles,
                [Api\Adapter\MessageAdapter::class],
                ['read', 'search']
            )
            // Anybody can browse, select, send email.
            // TODO Not zip.
            ->allow(
                null,
                ['ContactUs\Controller\Site\Index']
            );
            // Add possibility to list search own entities.
            // Admins can admin messages (browse, flag, delete, etc.).
            // This is automatic via acl factory.
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $translate = $plugins->get('translate');
        $translator = $services->get('MvcTranslator');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.70')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.70'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }

        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        if (!$this->checkDestinationDir($basePath . '/' . self::STORE_PREFIX)) {
            $message = new PsrMessage(
                'The directory "{directory}" is not writeable.', // @translate
                ['directory' => $basePath . '/' . self::STORE_PREFIX]
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message->setTranslator($translator));
        }
    }

    protected function isSettingTranslatable(string $settingsType, string $name): bool
    {
        $translatables = [
            'site_settings' => [
                'contactus_notify_subject',
                'contactus_notify_body',
                'contactus_confirmation_subject',
                'contactus_confirmation_body',
                'contactus_confirmation_newsletter_subject',
                'contactus_confirmation_newsletter_body',
                'contactus_to_author_subject',
                'contactus_to_author_body',
                'contactus_confirmation_message',
                'contactus_confirmation_message_newsletter',
                'contactus_consent_label',
                'contactus_label_selection',
                'contactus_label_guest_link',
                // 'contactus_questions',
            ],
            'block_settings' => [
                'confirmation_subject',
                'confirmation_body',
                'consent_label',
                'newsletter_label',
                'unsubscribe_label',
                // 'questions' => [],
            ],
        ];
        return isset($translatables[$settingsType])
            && in_array($name, $translatables[$settingsType]);
    }

    protected function postUninstall(): void
    {
        if (!empty($_POST['remove-contact-us'])) {
            $config = $this->getServiceLocator()->get('Config');
            $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
            $this->rmDir($basePath . '/' . self::STORE_PREFIX);
        }
    }

    public function warnUninstall(Event $event): void
    {
        $view = $event->getTarget();
        $module = $view->vars()->module;
        if ($module->getId() != __NAMESPACE__) {
            return;
        }

        $services = $this->getServiceLocator();
        $t = $services->get('MvcTranslator');
        $config = $this->getServiceLocator()->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        $html = '<p>';
        $html .= '<strong>';
        $html .= $t->translate('WARNING:'); // @translate
        $html .= '</strong>';
        $html .= '</p>';

        $html .= '<p>';
        $html .= new PsrMessage(
            'All contact messages and files will be removed (folder "{folder}").', // @translate
            ['folder' => $basePath . '/' . self::STORE_PREFIX]
        );
        $html .= '</p>';

        $html .= '<label><input name="remove-contact-us" type="checkbox" form="confirmform">';
        $html .= $t->translate('Remove Contact Us files'); // @translate
        $html .= '</label>';

        echo $html;
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // Deprecated. Use resource block layout instead.
        $sharedEventManager->attach(
            'Omeka\Controller\Site\ItemSet',
            'view.show.after',
            [$this, 'handleViewShowAfterResource']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Item',
            'view.show.after',
            [$this, 'handleViewShowAfterResource']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Media',
            'view.show.after',
            [$this, 'handleViewShowAfterResource']
        );

        // Display the contact form under item/browse.
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Item',
            'view.browse.after',
            [$this, 'handleViewBrowse']
        );

        // Guest integration.
        $sharedEventManager->attach(
            \Guest\Controller\Site\GuestController::class,
            'guest.widgets',
            [$this, 'handleGuestWidgets']
        );

        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );

        // Display a warn before uninstalling.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Module',
            'view.details',
            [$this, 'warnUninstall']
        );
    }

    public function handleViewShowAfterResource(Event $event): void
    {
        $view = $event->getTarget();
        $resourceName = $view->resource->resourceName();
        $append = $this->getServiceLocator()->get('Omeka\Settings\Site')->get('contactus_append_resource_show', []);
        if (!in_array($resourceName, $append)) {
            return;
        }
        /** @see \ContactUs\View\Helper\ContactUs */
        echo $view->contactUs([
            'resource' => $view->resource,
            'attach_file' => false,
            'newsletter_label' => '',
        ]);
    }

    public function handleViewBrowse(Event $event): void
    {
        $append = $this->getServiceLocator()->get('Omeka\Settings\Site')->get('contactus_append_items_browse');
        if (!$append) {
            return;
        }
        $view = $event->getTarget();
        /** @see \ContactUs\View\Helper\ContactUs */
        echo $view->contactUs([
            'attach_file' => false,
            'newsletter_label' => '',
        ]);
    }

    public function handleGuestWidgets(Event $event): void
    {
        $widgets = $event->getParam('widgets');
        $services = $this->getServiceLocator();
        $plugins = $services->get('ViewHelperManager');
        $partial = $plugins->get('partial');
        $translate = $plugins->get('translate');
        $siteSettings = $services->get('Omeka\Settings\Site');

        $widget = [];
        $widget['label'] = $siteSettings->get('contactus_label', $translate('Contact us')); // @translate
        $widget['content'] = $partial('guest/site/guest/widget/contact-us');
        $widgets['contact-us'] = $widget;

        $event->setParam('widgets', $widgets);
    }
}
