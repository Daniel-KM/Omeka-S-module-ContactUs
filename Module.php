<?php
namespace ContactUs;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Omeka\Settings\SettingsInterface;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Item',
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

    public function handleMainSettings(Event $event)
    {
        parent::handleMainSettings($event);

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $fieldset = $event
            ->getTarget()
            ->get('contactus');

        $recipients = $settings->get('contactus_notify_recipients') ?: [];
        $value = is_array($recipients) ? implode("\n", $recipients) : $recipients;
        $fieldset
            ->get('contactus_notify_recipients')
            ->setValue($value);
    }

    public function handleMainSettingsFilters(Event $event)
    {
        $event->getParam('inputFilter')
            ->get('contactus')
            ->add([
                'name' => 'contactus_notify_recipients',
                'required' => false,
                'filters' => [
                    [
                        'name' => \Zend\Filter\Callback::class,
                        'options' => [
                            'callback' => [$this, 'stringToList'],
                        ],
                    ],
                ],
            ]);
    }

    public function handleSiteSettings(Event $event)
    {
        parent::handleSiteSettings($event);

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings\Site');

        $fieldset = $event
            ->getTarget()
            ->get('contactus');

        $recipients = $settings->get('contactus_notify_recipients') ?: [];
        $value = is_array($recipients) ? implode("\n", $recipients) : $recipients;
        $fieldset
            ->get('contactus_notify_recipients')
            ->setValue($value);

        $questions = $settings->get('contactus_questions') ?: [];
        $value = '';
        foreach ($questions as $question => $answer) {
            $value .= $question . ' = ' . $answer . "\n";
        }
        $fieldset
            ->get('contactus_questions')
            ->setValue($value);
    }

    public function handleSiteSettingsFilters(Event $event)
    {
        $event->getParam('inputFilter')
            ->get('contactus')
            ->add([
                'name' => 'contactus_notify_recipients',
                'required' => false,
                'filters' => [
                    [
                        'name' => \Zend\Filter\Callback::class,
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
                        'name' => \Zend\Filter\Callback::class,
                        'options' => [
                            'callback' => [$this, 'stringToKeyValues'],
                        ],
                    ],
                ],
            ])
        ;
    }

    protected function initDataToPopulate(SettingsInterface $settings, $settingsType, $id = null)
    {
        // This method is not in the interface, but is set for config, site and
        // user settings.
        if (!method_exists($settings, 'getTableName')) {
            return;
        }

        $config = $this->getConfig();
        $space = strtolower(static::NAMESPACE);
        if (empty($config[$space][$settingsType])) {
            return;
        }

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        if ($id) {
            if (!method_exists($settings, 'getTargetIdColumnName')) {
                return;
            }
            $sql = sprintf('SELECT id, value FROM %s WHERE %s = :target_id', $settings->getTableName(), $settings->getTargetIdColumnName());
            $stmt = $connection->executeQuery($sql, ['target_id' => $id]);
        } else {
            $sql = sprintf('SELECT id, value FROM %s', $settings->getTableName());
            $stmt = $connection->query($sql);
        }

        $currentSettings = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        $defaultSettings = $config[$space][$settingsType];
        // Skip settings that are arrays, because the fields "multi-checkbox"
        // and "multi-select" are removed when no value are selected, so it's
        // not possible to determine if it's a new setting or an old empty
        // setting currently. So fill them via upgrade in that case.
        // TODO Find a way to save empty multi-checkboxes and multi-selects (core fix).

        // In this form there is currently no select or radio.

        // $defaultSettings = array_filter($defaultSettings, function ($v) {
        //     return !is_array($v);
        // });
        $missingSettings = array_diff_key($defaultSettings, $currentSettings);

        foreach ($missingSettings as $name => $value) {
            $settings->set($name, $value);
        }
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

    public function handleViewShowAfterResource(Event $event)
    {
        $view = $event->getTarget();
        echo $view->contactUs(['resource' => $view->resource]);
    }
}
