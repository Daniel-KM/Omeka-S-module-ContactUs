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

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    protected function postInstall(): void
    {
        // Prepare all translations one time.
        $translatables = [
            'contactus_confirmation_subject',
            'contactus_confirmation_body',
            // 'contactus_questions',
        ];
        $config = $this->getConfig()['contactus']['site_settings'];
        $translate = $this->getServiceLocator()->get('ControllerPluginManager')->get('translate');
        $translatables = array_filter(array_map(function ($v) use ($translate, $config) {
            return !empty($config[$v]) ? $translate($config[$v]) : null;
        }, array_combine($translatables, $translatables)));

        $this->manageSiteSettings('update', $translatables);
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
    }

    public function handleViewShowAfterResource(Event $event): void
    {
        $view = $event->getTarget();
        $view->partial('common/contact-us', ['resource' => $view->resource]);
    }
}
