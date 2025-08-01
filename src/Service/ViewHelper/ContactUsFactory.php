<?php declare(strict_types=1);

namespace ContactUs\Service\ViewHelper;

use ContactUs\View\Helper\ContactUs;
use Interop\Container\ContainerInterface;
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
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        $siteSettings = $services->get('Omeka\Settings\Site');
        $defaultOptions = [];
        $config = $services->get('Config');
        $configSiteSettings = $config['contactus']['site_settings'] ?? [];
        foreach ($configSiteSettings as $key => $value) {
            $defaultOptions[substr($key, 10)] = $siteSettings->get($key, $value);
        }
        return new ContactUs(
            $plugins->get('api'),
            $services->get('Omeka\ApiManager'),
            $services->get('Common\EasyMeta'),
            $services->get('FormElementManager'),
            $services->get('Omeka\Mailer'),
            $plugins->get('messenger'),
            $plugins->get('sendEmail'),
            $defaultOptions
        );
    }
}
