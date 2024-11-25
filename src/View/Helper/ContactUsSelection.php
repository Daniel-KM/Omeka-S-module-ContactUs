<?php declare(strict_types=1);

namespace ContactUs\View\Helper;

use Laminas\Session\Container;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Settings\UserSettings;

/**
 * @see \ContactUs\View\Helper\ContactUsSelection
 * @see \Selection\View\Helper\SelectionContainer
 */
class ContactUsSelection extends AbstractHelper
{
    /**
     * @var \Omeka\Settings\UserSettings
     */
    protected $userSettings;

    public function __construct(UserSettings $userSettings)
    {
        $this->userSettings = $userSettings;
    }

    /**
     * Update (toggle) selected resources of the current user or visitor.
     *
     * Set null to reset the selection.
     *
     * @var array|int|null|false $resourceIds List of resources to add/remove
     * from the selection. Set an empty array or null to get the current list.
     * Set false to reset the list.
     * @return array List all selected resources.
     */
    public function __invoke($resourceIds = null): array
    {
        $view = $this->getView();

        $user = $view->identity();

        $isReset = $resourceIds === false;
        if ($isReset) {
            $resourceIds = null;
        } else {
            $isMultiple = is_array($resourceIds);
            if (!$isMultiple) {
               $resourceIds = [$resourceIds];
            }
            $resourceIds = array_filter(array_map('intval', $resourceIds));
        }

        return $user
            ? $this->toggleDb($resourceIds)
            : $this->toggleSession($resourceIds);
    }

    /**
     * Select resource(s) to add or remove to the user selection for contact us.
     */
    protected function toggleDb(?array $resourceIds): array
    {
        if ($resourceIds === null) {
            $newSelecteds = [];
        } else {
            $alreadySelecteds = $this->userSettings->get('contactus_selected_resources') ?: [];
            $existings = array_intersect($resourceIds, $alreadySelecteds);
            $news = array_diff($resourceIds, $alreadySelecteds);
            $newSelecteds = array_merge(array_diff($alreadySelecteds, $existings), $news);
        }
        $this->userSettings->set('contactus_selected_resources', $newSelecteds);
        return $newSelecteds;
    }

    /**
     * Select resource(s) to add or remove to a local selection for contact us.
     */
    protected function toggleSession(?array $resourceIds): array
    {
        $container = new Container('ContactUsSelection');
        if ($resourceIds === null) {
            $newSelecteds = [];
        } else {
            $alreadySelecteds = $container->selected_resources ?? [];
            $existings = array_intersect($resourceIds, $alreadySelecteds);
            $news = array_diff($resourceIds, $alreadySelecteds);
            $newSelecteds = array_merge(array_diff($alreadySelecteds, $existings), $news);
        }
        $container->selected_resources = $newSelecteds;
        return $newSelecteds;
    }
}
