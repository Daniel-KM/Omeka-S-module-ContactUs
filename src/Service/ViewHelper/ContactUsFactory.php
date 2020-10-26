<?php
namespace ContactUs\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use ContactUs\View\Helper\ContactUs;
use Laminas\ServiceManager\Factory\FactoryInterface;

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
        $options = $serviceLocator->get('Config')['contactus']['site_settings'];
        $defaultOptions = [];
        foreach ($options as $key => $value) {
            $defaultOptions[substr($key, 10)] = $value;
        }
        return new ContactUs(
            $serviceLocator->get('FormElementManager'),
            $defaultOptions,
            $serviceLocator->get('Omeka\Mailer')
        );
    }
}
