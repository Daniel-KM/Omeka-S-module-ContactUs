<?php
namespace ContactUs\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use ContactUs\View\Helper\ContactUs;
use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Service factory for the ContactUs view helper.
 */
class ContactUsFactory implements FactoryInterface
{
    /**
     * Create and return the ContactUs view helper
     *
     * @return ContactUs
     */
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        return new ContactUs(
            $serviceLocator->get('FormElementManager'),
            $serviceLocator->get('Config')['contactus']['block_settings']['contactUs'],
            $serviceLocator->get('Omeka\Mailer')
        );
    }
}
