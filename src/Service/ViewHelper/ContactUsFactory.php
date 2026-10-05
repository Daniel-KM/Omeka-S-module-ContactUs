<?php declare(strict_types=1);

namespace ContactUs\Service\ViewHelper;

use ContactUs\View\Helper\ContactUs;
use Psr\Container\ContainerInterface;
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
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        $defaultOptions = self::siteOptions($services);
        return new ContactUs(
            $plugins->get('api'),
            $services->get('Omeka\ApiManager'),
            $services->get('Common\EasyMeta'),
            $services->get('FormElementManager'),
            $services->get('Omeka\Mailer'),
            $plugins->get('messenger'),
            $plugins->get('sendEmail'),
            $defaultOptions,
            $services
        );
    }

    /**
     * The options of the contact form, from the settings of a site.
     *
     * Without site id, the current site is used, as on a public page. With a
     * site id, the settings of this site are used, for example to resend a
     * message from the admin board, where there is no current site.
     */
    public static function siteOptions(ContainerInterface $services, ?int $siteId = null): array
    {
        $siteSettings = $services->get('Omeka\Settings\Site');
        if ($siteId) {
            $siteSettings->setTargetId($siteId);
        }
        $options = [];
        $config = $services->get('Config');
        foreach ($config['contactus']['site_settings'] ?? [] as $key => $value) {
            $options[substr($key, 10)] = $siteSettings->get($key, $value);
        }
        return $options;
    }
}
