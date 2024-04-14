<?php declare(strict_types=1);

namespace ContactUs\Service\Controller;

use ContactUs\Controller\ZipController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ZipControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ZipController(
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\ApiAdapterManager')->get('contact_messages')
        );
    }
}
