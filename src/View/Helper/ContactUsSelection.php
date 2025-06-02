<?php declare(strict_types=1);

namespace ContactUs\View\Helper;

use Doctrine\DBAL\Connection;
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
     * @var \Doctrine\DBAL\Connection;
     */
    protected $connection;

    /**
     * @var \Omeka\Settings\UserSettings
     */
    protected $userSettings;

    public function __construct(
        Connection $connection,
        UserSettings $userSettings
    ) {
        $this->connection = $connection;
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
        $alreadySelecteds = $this->checkResourceIds($alreadySelecteds);

        if (!count($resourceIds)) {
            $this->userSettings->set('contactus_selected_resources', $alreadySelecteds);
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
        $alreadySelecteds = $this->checkResourceIds($alreadySelecteds);

        if (!count($resourceIds)) {
            $container->selected_resources = $alreadySelecteds;
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

    /**
     * Check ids that may have been removed or made private.
     *
     * @todo Remove private resource from the list of selected resources via api search resources.
     */
    protected function checkResourceIds(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        // Ideally, the check should take visibility in account, but it is not
        // possible with one query currently.
        // TODO Use api search resources when possible.
        // $result = $this->api-search('resources', ['id' => $ids], ['returnScalar' => 'id')->getContent();
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('id')
            ->from('resource', 'resource')
            ->where('resource.id IN (:ids)')
            ->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY)
        ;
        $result = $qb->execute()->fetchFirstColumn();

        // Keep original order.
        return array_values(array_intersect($ids, $result));
    }
}
