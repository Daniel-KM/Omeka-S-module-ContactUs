<?php declare(strict_types=1);

namespace ContactUs\Service\ViewHelper;

use ContactUs\View\Helper\ContactUsSelection;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Service factory for the ContactUsSelection view helper.
 */
class ContactUsSelectionFactory implements FactoryInterface
{
    /**
     * Create and return the ContactUsSelection view helper.
     *
     * @return ContactUsSelection
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ContactUsSelection(
            $services->get('Omeka\Connection'),
            $services->get('Omeka\Settings\User')
        );
    }
}
