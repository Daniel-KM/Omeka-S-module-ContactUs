<?php declare(strict_types=1);

namespace ContactUs\Service\Form;

use ContactUs\Form\SiteSettingsFieldset;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class SiteSettingsFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $fieldset = new SiteSettingsFieldset(null, ($options ?? []) + [
            'module_manager' => $services->get('Omeka\ModuleManager'),
        ]);
        return $fieldset;
    }
}
