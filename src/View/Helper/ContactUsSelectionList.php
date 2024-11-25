<?php declare(strict_types=1);

namespace ContactUs\View\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * @see \ContactUs\View\Helper\ContactUsSelectionList
 * @see \Selection\View\Helper\SelectionList
 */
class ContactUsSelectionList extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/contact-us-selection-list';

    /**
     * @var bool
     */
    protected $isGuestActive = false;

    public function __construct(bool $isGuestActive)
    {
        $this->isGuestActive = $isGuestActive;
    }

    /**
     * Display a contact us selector, checked or not.
     */
    public function __invoke(array $options = []): string
    {
        $view = $this->getView();

        $options += [
            'template' => null,
        ];

        $user = $view->identity();
        $selectedResourceIds = $view->contactUsSelection();

        $template = $options['template'] ?: self::PARTIAL_NAME;

        return $view->partial($template, [
            'site' => $view->currentSite(),
            'user' => $user,
            'resourceIds' => $selectedResourceIds,
            'isGuestActive' => $this->isGuestActive,
            'isSession' => !$user,
            'isPost' => $view->params()->fromPost(),
        ] + $options);
    }
}
