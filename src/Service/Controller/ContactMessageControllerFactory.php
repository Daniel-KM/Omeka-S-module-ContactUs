<?php declare(strict_types=1);

namespace ContactUs\Service\Controller;

use ContactUs\Controller\Admin\ContactMessageController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ContactMessageControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');
        return new ContactMessageController(
            $services->get('Omeka\Connection'),
            $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files')
        );
    }
}
