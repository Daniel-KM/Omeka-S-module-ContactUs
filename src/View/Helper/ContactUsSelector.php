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
     *
     * @var array $options Managed options:
     * - template (string)
     * - label (string): default is "Add to the selection for contact".
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $options = []): string
    {
        /**
         * Some data are stored statically for performance reason.
         */
        static $partial = null;
        static $defaultLabel = null;
        static $maxResources = null;
        static $contactUsSelection = null;
        static $urlContactUsSelect = null;

        if (is_null($urlContactUsSelect)) {
            $plugins = $this->getView()->getHelperPluginManager();
            $url = $plugins->get('url');
            $partial = $plugins->get('partial');
            $translate = $plugins->get('translate');
            $siteSetting = $plugins->get('siteSetting');
            $contactUsSelection = $plugins->get('contactUsSelection');
            $urlContactUsSelect = $url('site/contact-us', ['action' => 'select'], true);
            $maxResources = (int) $siteSetting('contactus_selection_max');
            $defaultLabel = $translate('Add to the selection for contact'); // @translate
        }

        $options += [
            'template' => null,
            'label' => $defaultLabel,
        ];

        $selectedResourceIds = $contactUsSelection();

        $template = $options['template'] ?: self::PARTIAL_NAME;

        return $partial($template, [
            'resource' => $resource,
            'isSelected' => in_array($resource->id(), $selectedResourceIds),
            'maxResources' => $maxResources,
            'selectedResourceIds' => $selectedResourceIds,
            'urlContactUsSelect' => $urlContactUsSelect,
        ] + $options);
    }
}
