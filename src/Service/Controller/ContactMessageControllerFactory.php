<?php declare(strict_types=1);

namespace ContactUs\Service\Controller;

use ContactUs\Controller\Admin\ContactMessageController;
use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ContactMessageControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ContactMessageController(
            $services->get('Omeka\Connection')
        );
    }
}
