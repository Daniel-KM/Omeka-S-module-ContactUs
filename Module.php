<?php declare(strict_types=1);

namespace ContactUs;

// Common may be installed but not registered in autoloader, in particular
// during upgrade. So dynamically register all classes of the module.
if (!defined('COMMON_PSR4_FALLBACK')) {
    foreach ([
        OMEKA_PATH . '/modules/Common/src',
        OMEKA_PATH . '/composer-addons/modules/Common/src',
        dirname(__DIR__) . '/Common/src',
    ] as $commonSrc) {
        if (file_exists($commonSrc . '/TraitModule.php')) {
            define('COMMON_PSR4_FALLBACK', $commonSrc);
            spl_autoload_register(static function ($class): void {
                if (str_starts_with($class, 'Common\\')) {
                    $file = COMMON_PSR4_FALLBACK . '/' . strtr(substr($class, 7), '\\', '/') . '.php';
                    if (file_exists($file)) {
                        require_once $file;
                    }
                }
            });
            break;
        }
    }
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Module\AbstractModule;

/**
 * Contact Us
 *
 * @copyright Daniel Berthereau, 2018-2026
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    const STORE_PREFIX = 'contactus';

    public function init(ModuleManager $moduleManager): void
    {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        // Since Omeka 1.4, modules are ordered, so Guest comes after Access.
        // See \Guest\Module::onBootstrap(). Manage other roles too: contributor, etc.
        if (class_exists('Guest\Module', false)) {
            if (!$acl->hasRole('guest')) {
                $acl->addRole('guest');
            }
        }
        if (class_exists('GuestPrivate\Module', false)) {
            if (!$acl->hasRole('guest_private')) {
                $acl->addRole('guest_private');
            }
            if (!$acl->hasRole('guest_private_site')) {
                $acl->addRole('guest_private_site');
            }
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
            // TODO Remove acl for controller zip: tested manually inside controller for now.
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
        $translator = $services->get('MvcTranslator');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.90')) {
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.90'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }

        $this->checkPhpVersion();

        $errors = [];

        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        if (!$this->checkDestinationDir($basePath . '/' . self::STORE_PREFIX)) {
            $message = new PsrMessage(
                'The directory "{directory}" is not writeable.', // @translate
                ['directory' => $basePath . '/' . self::STORE_PREFIX]
            );
            $errors[] = (string) $message->setTranslator($translator);
        }

        if ($errors) {
            throw new \Omeka\Module\Exception\ModuleCannotInstallException(implode("\n", $errors));
        }

        $this->checkSpamGuardPresence($services);
    }

    /**
     * PHP 8.1 is required to stream the zip of files (enums and the ZipStream
     * library). Omeka >= 4.2 already enforces it, but the module still supports
     * Omeka 4.1, which may run on an older PHP.
     */
    protected function checkPhpVersion(): void
    {
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            $translator = $this->getServiceLocator()->get('MvcTranslator');
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('The module %1$s requires PHP %2$s or higher.'), // @translate
                'ContactUs',
                '8.1'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }
    }

    protected function checkSpamGuardPresence(ServiceLocatorInterface $services): void
    {
        $moduleManager = $services->get('Omeka\ModuleManager');
        $bg = $moduleManager->getModule('SpamGuard');
        if (!$bg || $bg->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
            $services->get('Omeka\Logger')->notice(
                'Module ContactUs : SpamGuard is missing, so only simple anti-spam countermeasures are available.' // @translate
            );
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
                'contactus_selection_label',
                'contactus_selection_label_guest_link',
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
        $services = $this->getServiceLocator();
        $currentTheme = $services->get('Omeka\Site\ThemeManager')->getCurrentTheme();
        if (method_exists($currentTheme, 'isConfigurableResourcePageBlocks') && $currentTheme->isConfigurableResourcePageBlocks()) {
            return;
        }
        $view = $event->getTarget();
        $resourceName = $view->resource->resourceName();
        $siteSettings = $services->get('Omeka\Settings\Site');
        $placements = $siteSettings->get('contactus_placement', []);
        $append = $siteSettings->get('contactus_append_resource_show', []);
        if (!in_array('after/' . $resourceName, $placements) && !in_array($resourceName, $append)) {
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
        $services = $this->getServiceLocator();
        $currentTheme = $services->get('Omeka\Site\ThemeManager')->getCurrentTheme();
        if (method_exists($currentTheme, 'isConfigurableResourcePageBlocks') && $currentTheme->isConfigurableResourcePageBlocks()) {
            return;
        }
        $siteSettings = $services->get('Omeka\Settings\Site');
        $placements = $siteSettings->get('contactus_placement', []);
        $append = $siteSettings->get('contactus_append_items_browse');
        if (!in_array('browse/items', $placements) && !$append) {
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
        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');
        if ($siteSettings->get('contactus_selection_guest_disable')) {
            return;
        }

        $widgets = $event->getParam('widgets');
        $plugins = $services->get('ViewHelperManager');
        $partial = $plugins->get('partial');
        $translate = $plugins->get('translate');

        $widget = [];
        $widget['label'] = $siteSettings->get('contactus_selection_label_guest_link', $translate('Contact us')); // @translate
        $widget['content'] = $partial('guest/site/guest/widget/contact-us');
        $widgets['contact-us'] = $widget;

        $event->setParam('widgets', $widgets);
    }
}
