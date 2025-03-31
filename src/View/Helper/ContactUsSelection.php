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
     * Set false to reset the selection.
     *
     * Warning: if a max number of resources is set for the selection (25 by
     * default), some of them may be skipped silently, so a warning should be
     * done earlier via php or js.
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
     *
     * @param array $resourceIds If null, the selection list is reset.
     */
    protected function toggleDb(?array $resourceIds): array
    {
        if ($resourceIds === null) {
            $this->userSettings->set('contactus_selected_resources', []);
            return [];
        }

        $alreadySelecteds = $this->userSettings->get('contactus_selected_resources') ?: [];

        if (!count($resourceIds)) {
            return $alreadySelecteds;
        }

        $existings = array_intersect($resourceIds, $alreadySelecteds);
        $news = array_diff($resourceIds, $alreadySelecteds);
        $newsSelectedsWithoutDeleted = array_diff($alreadySelecteds, $existings);
        $newSelecteds = array_merge($newsSelectedsWithoutDeleted, $news);

        $max = (int) $this->getView()->siteSetting('contactus_selection_max');
        if ($max) {
            $newSelecteds = array_slice($newSelecteds, 0, $max);
        }

        $this->userSettings->set('contactus_selected_resources', $newSelecteds);
        return $newSelecteds;
    }

    /**
     * Select resource(s) to add or remove to a local selection for contact us.
     *
     * @param array $resourceIds If null, the selection list is reset.
     */
    protected function toggleSession(?array $resourceIds): array
    {
        $container = new Container('ContactUsSelection');
        if ($resourceIds === null) {
            $container->selected_resources = [];
            return [];
        }

        $alreadySelecteds = $container->selected_resources ?? [];

        if (!count($resourceIds)) {
            return $alreadySelecteds;
        }

        $existings = array_intersect($resourceIds, $alreadySelecteds);
        $news = array_diff($resourceIds, $alreadySelecteds);
        $newSelecteds = array_merge(array_diff($alreadySelecteds, $existings), $news);

        $max = (int) $this->getView()->siteSetting('contactus_selection_max');
        if ($max) {
            $newSelecteds = array_slice($newSelecteds, 0, $max);
        }

        $container->selected_resources = $newSelecteds;
        return $newSelecteds;
    }
}
