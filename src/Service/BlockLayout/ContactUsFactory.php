<?php
namespace ContactUs\Service\BlockLayout;

use ContactUs\Site\BlockLayout\ContactUs;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ContactUsFactory implements FactoryInterface
{
    /**
     * Create the Contact Us block layout service.
     *
     * @return ContactUs
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ContactUs(
            $services->get('FormElementManager'),
            $services->get('Config')['contactus']['block_settings']['contactUs'],
            $services->get('Omeka\Mailer'),
            $services->get('Omeka\AuthenticationService')->hasIdentity(),
            $services->get('Omeka\Logger')
        );
    }
}
