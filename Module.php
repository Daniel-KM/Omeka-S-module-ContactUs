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
use Omeka\Settings\SettingsInterface;

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
            \Omeka\Form\SettingForm::class,
            'form.add_input_filters',
            [$this, 'handleMainSettingsFilters']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_input_filters',
            [$this, 'handleSiteSettingsFilters']
        );
    }

    protected function initDataToPopulate(SettingsInterface $settings, $settingsType, $id = null, array $values = [])
    {
        if ($settingsType !== 'site_settings') {
            return parent::initDataToPopulate($settings, $settingsType, $id, $values);
        }

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

        // TODO Translate contact us questions.
        if ($settings->get('contactus_questions') === null) {
            $settings->set('contactus_questions', $config['contactus_questions']);
        }

        return parent::initDataToPopulate($settings, $settingsType, $id, $translatables);
    }

    protected function prepareDataToPopulate(SettingsInterface $settings, $settingsType)
    {
        $data = parent::prepareDataToPopulate($settings, $settingsType);
        if (in_array($settingsType, ['settings', 'site_settings'])) {
            if (isset($data['contactus_notify_recipients']) && is_array($data['contactus_notify_recipients'])) {
                $data['contactus_notify_recipients'] = implode("\n", $data['contactus_notify_recipients']);
            }
            if ($settingsType === 'site_settings'
                && isset($data['contactus_questions']) && is_array($data['contactus_questions'])
            ) {
                $questions = $data['contactus_questions'];
                $value = '';
                foreach ($questions as $question => $answer) {
                    $value .= $question . ' = ' . $answer . "\n";
                }
                $data['contactus_questions'] = $value;
            }
        }
        return $data;
    }

    public function handleMainSettingsFilters(Event $event): void
    {
        $event->getParam('inputFilter')
            ->get('contactus')
            ->add([
                'name' => 'contactus_notify_recipients',
                'required' => false,
                'filters' => [
                    [
                        'name' => \Laminas\Filter\Callback::class,
                        'options' => [
                            'callback' => [$this, 'stringToList'],
                        ],
                    ],
                ],
            ]);
    }

    public function handleSiteSettingsFilters(Event $event): void
    {
        $event->getParam('inputFilter')
            ->get('contactus')
            ->add([
                'name' => 'contactus_notify_recipients',
                'required' => false,
                'filters' => [
                    [
                        'name' => \Laminas\Filter\Callback::class,
                        'options' => [
                            'callback' => [$this, 'stringToList'],
                        ],
                    ],
                ],
            ])
            ->add([
                'name' => 'contactus_questions',
                'required' => false,
                'filters' => [
                    [
                        'name' => \Laminas\Filter\Callback::class,
                        'options' => [
                            'callback' => [$this, 'stringToKeyValues'],
                        ],
                    ],
                ],
            ])
        ;
    }

    public function stringToKeyValues($string)
    {
        $result = [];
        $questions = $this->stringToList($string);
        foreach ($questions as $questionAnswer) {
            list($question, $answer) = array_map('trim', explode('=', $questionAnswer, 2));
            if ($question !== '' && $answer !== '') {
                $result[$question] = $answer;
            }
        }
        return $result;
    }

    public function handleViewShowAfterResource(Event $event): void
    {
        $view = $event->getTarget();
        $view->partial('common/contact-us', ['resource' => $view->resource]);
    }
}
