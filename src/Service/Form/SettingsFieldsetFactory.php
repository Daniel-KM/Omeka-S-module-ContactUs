<?php declare(strict_types=1);

namespace ContactUs\Service\Form;

use ContactUs\Form\SettingsFieldset;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class SettingsFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $fieldset = new SettingsFieldset(null, ($options ?? []) + [
            'module_manager' => $services->get('Omeka\ModuleManager'),
        ]);
        return $fieldset;
    }
}
