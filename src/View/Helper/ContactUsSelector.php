<?php declare(strict_types=1);

namespace ContactUs\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * @see \ContactUs\View\Helper\ContactUsSelector
 * @see \Selection\View\Helper\SelectionButton
 */
class ContactUsSelector extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/contact-us-selector';

    /**
     * Display a contact us selector, checked or not.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $options = []): string
    {
        /**
         * The contact us selector url is stored statically for performance reason.
         */
        static $urlContactUsSelect = null;

        $view = $this->getView();

        if (is_null($urlContactUsSelect)) {
            $urlContactUsSelect = $view->url('site/contact-us', ['action' => 'select'], true);
        }

        $options += [
            'template' => null,
        ];

        $selectedResourceIds = $view->contactUsSelection();

        $template = $options['template'] ?: self::PARTIAL_NAME;

        return $view->partial($template, [
            'resource' => $resource,
            'isSelected' => in_array($resource->id(), $selectedResourceIds),
            'selectedResourceIds' => $selectedResourceIds,
            'urlContactUsSelect' => $urlContactUsSelect,
        ] + $options);
    }
}
