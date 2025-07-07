<?php declare(strict_types=1);

namespace ContactUs\Site\Navigation\Link;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\Navigation\Link\LinkInterface;
use Omeka\Stdlib\ErrorStore;

class Basket implements LinkInterface
{
    public function getName()
    {
        return 'Contact Us: Basket'; // @translate
    }

    public function getFormTemplate()
    {
        return 'common/navigation-link-form/label';
    }

    public function isValid(array $data, ErrorStore $errorStore)
    {
        return true;
    }

    public function getLabel(array $data, SiteRepresentation $site)
    {
        return isset($data['label']) && trim($data['label']) !== ''
            ? $data['label']
            : 'Basket'; // @translate
    }

    public function toZend(array $data, SiteRepresentation $site)
    {
        /**
         * @var \Omeka\Entity\User $user
         * @var \Omeka\Module\Manager $moduleManager
         */
        $services = $site->getServiceLocator();
        $user = $services->get('Omeka\AuthenticationService')->getIdentity();
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('Guest');
        $isGuestActive = $module
            && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;
        if ($user && $isGuestActive) {
            return [
                'label' => $data['label'],
                'route' => 'site/guest/contact-us',
                'class' => 'contact-us-link',
                'params' => [
                    'site-slug' => $site->slug(),
                ],
                'resource' => 'ContactUs\Controller\Site\Index',
            ];
        }
        return [
            'label' => $data['label'],
            'route' => 'site/contact-us',
            'class' => 'contact-us-link',
            'params' => [
                'site-slug' => $site->slug(),
                'action' => 'browse',
            ],
            'resource' => 'ContactUs\Controller\Site\Index',
        ];
    }

    public function toJstree(array $data, SiteRepresentation $site)
    {
        return [
            'label' => isset($data['label']) ? trim($data['label']) : '',
        ];
    }
}
