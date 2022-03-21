<?php declare(strict_types=1);

namespace ContactUs;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;

class Module extends AbstractModule
{
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
            // Add possibility to list search own entities.
            // Admins can admin messages (browse, flag, delete, etc.).
            // This is automatic via acl factory.
        ;
    }

    protected function postInstall(): void
    {
        $services = $this->getServiceLocator();
        // Prepare all translations one time.
        $translatables = [
            'contactus_confirmation_subject',
            'contactus_confirmation_body',
            'contactus_to_author_subject',
            'contactus_to_author_body',
            // 'contactus_questions',
        ];
        $config = $this->getConfig()['contactus']['site_settings'];
        $translate = $services->get('ControllerPluginManager')->get('translate');
        $translatables = array_filter(array_map(function ($v) use ($translate, $config) {
            return !empty($config[$v]) ? $translate($config[$v]) : null;
        }, array_combine($translatables, $translatables)));

        $this->manageSiteSettings('update', $translatables);

        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        if (!$this->checkDestinationDir($basePath . '/' . self::STORE_PREFIX)) {
            $message = new \Omeka\Stdlib\Message(
                'The directory "%s" is not writeable.', // @translate
                $basePath
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }
    }

    protected function preUninstall(): void
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

        $serviceLocator = $this->getServiceLocator();
        $t = $serviceLocator->get('MvcTranslator');
        $config = $this->getServiceLocator()->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        $html = '<p>';
        $html .= '<strong>';
        $html .= $t->translate('WARNING:'); // @translate
        $html .= '</strong>';
        $html .= '</p>';

        $html .= '<p>';
        $html .= sprintf(
            $t->translate('All contact messages and files will be removed (folder "{folder}").'), // @translate
            $basePath . '/' . self::STORE_PREFIX
        );
        $html .= '</p>';

        $html .= '<label><input name="remove-contact-us" type="checkbox" form="confirmform">';
        $html .= $t->translate('Remove Contact Us files'); // @translate
        $html .= '</label>';

        echo $html;
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
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
        echo $view->contactUs([
            'resource' => $view->resource,
            'attach_file' => false,
            'newsletter_label' => '',
        ]);
    }

    /**
     * Check or create the destination folder.
     *
     * @param string $dirPath Absolute path.
     * @return string|null
     */
    protected function checkDestinationDir($dirPath)
    {
        if (file_exists($dirPath)) {
            if (!is_dir($dirPath) || !is_readable($dirPath) || !is_writable($dirPath)) {
                $this->getServiceLocator()->get('Omeka\Logger')->err(
                    'The directory "{path}" is not writeable.', // @translate
                    ['path' => $dirPath]
                );
                return null;
            }
            return $dirPath;
        }

        $result = @mkdir($dirPath, 0775, true);
        if (!$result) {
            $this->getServiceLocator()->get('Omeka\Logger')->err(
                'The directory "{path}" is not writeable: {error}.', // @translate
                ['path' => $dirPath, 'error' => error_get_last()['message']]
            );
            return null;
        }
        return $dirPath;
    }

    /**
     * Remove a dir from filesystem.
     *
     * @param string $dirpath Absolute path.
     * @return bool
     */
    private function rmDir($dirPath)
    {
        if (!file_exists($dirPath)) {
            return true;
        }
        if (strpos($dirPath, '/..') !== false || substr($dirPath, 0, 1) !== '/') {
            return false;
        }
        $files = array_diff(scandir($dirPath), ['.', '..']);
        foreach ($files as $file) {
            $path = $dirPath . '/' . $file;
            if (is_dir($path)) {
                $this->rmDir($path);
            } else {
                unlink($path);
            }
        }
        return rmdir($dirPath);
    }
}
