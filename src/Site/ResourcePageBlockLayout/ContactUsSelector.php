<?php declare(strict_types=1);

namespace ContactUs\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

class ContactUsSelector implements ResourcePageBlockLayoutInterface
{
    public function getLabel() : string
    {
        return 'Contact Us: Selector'; // @translate
    }

    public function getCompatibleResourceNames() : array
    {
        return [
            'items',
            // 'media',
        ];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource) : string
    {
        return $view->partial('common/resource-page-block-layout/contact-us-selector', [
            'resource' => $resource,
        ]);
    }
}
