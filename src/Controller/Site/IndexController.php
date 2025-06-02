<?php declare(strict_types=1);

namespace ContactUs\Controller\Site;

use Common\Mvc\Controller\Plugin\JSend;
use Common\Stdlib\PsrMessage;
use ContactUs\Api\Adapter\MessageAdapter;
use Doctrine\ORM\EntityManager;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \ContactUs\Api\Adapter\MessageAdapter
     */
    protected $messageAdapter;

    /**
     * @var bool
     */
    protected $isGuestActive = false;

    public function __construct(
        EntityManager $entityManager,
        MessageAdapter $messageAdapter,
        bool $isGuestActive
    ) {
        $this->entityManager = $entityManager;
        $this->messageAdapter = $messageAdapter;
        $this->isGuestActive = $isGuestActive;
    }

    public function indexAction()
    {
        $params = $this->params()->fromRoute();
        $params['action'] = 'browse';
        return $this->forward()->dispatch('ContactUs\Controller\Site\Index', $params);
    }

    public function browseAction()
    {
        $user = $this->identity();

        $resourceIds = $this->viewHelpers()->get('contactUsSelection')();

        $view = new ViewModel([
            'site' => $this->currentSite(),
            'user' => $user,
            'fields' => $this->fallbackSettings()->get('contactus_fields', ['site', 'global']) ?: [],
            'resourceIds' => $resourceIds,
            'isGuestActive' => $this->isGuestActive,
            'isSession' => !$user,
            'isPost' => $this->getRequest()->isPost(),
        ]);

        $route = $this->status()->getRouteMatch()->getMatchedRouteName();
        if ($route === 'site/guest/contact-us') {
            $view
                ->setTemplate('guest/site/guest/contact-us-browse');
        }

        return $view;
    }

    /**
     * Update (toggle) selected resources of the current user or visitor.
     *
     * The resources to toggle are set in the query with the key id or id[].
     *
     * @return \Laminas\View\Model\JsonModel Indicate success/error and list all
     * selected resources.
     */
    public function selectAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jSend(JSend::FAIL, [
                'message' => $this->translate('Not an ajax request'), // @translate
            ], null, Response::STATUS_CODE_412);
        }

        $requestedResourceIds = $this->requestedResourceIds();

        // TODO Factorize with view helper ContactUsSelector?

        /** @var \ContactUs\View\Helper\ContactUsSelection $contactUsSelection */
        $contactUsSelection = $this->viewHelpers()->get('contactUsSelection');

        // Manage the case where there the max number is set.
        $siteSettings = $this->siteSettings();
        $max = (int) $siteSettings->get('contactus_selection_max');
        $isFail = false;
        if ($max) {
            $alreadySelecteds = $contactUsSelection();
            $existings = array_intersect($requestedResourceIds, $alreadySelecteds);
            $news = array_diff($requestedResourceIds, $alreadySelecteds);
            $newsSelectedsWithoutDeleted = array_diff($alreadySelecteds, $existings);
            $newSelecteds = array_merge($newsSelectedsWithoutDeleted, $news);
            $countNewSelecteds = count($newSelecteds);
            $isFail = $max && $countNewSelecteds > $max;
        }

        // Here, the max is already applied if needed.
        $newSelecteds = $contactUsSelection($requestedResourceIds);

        $output = [
            'selected_resources' => $newSelecteds,
        ];
        if ($siteSettings->get('contactus_selection_include_resources')) {
            $output['resources'] = $this->listResources($newSelecteds);
        }

        if ($isFail) {
            return $this->jSend(JSend::FAIL, $output, (string) (new PsrMessage(
                $this->siteSettings()->get('contactus_warn_limit', 'Warning: It is not possible to select more than {total} resources.'), // @translate
                ['total' => $max]
            ))->setTranslator($this->translator()));
        }

        return $this->jSend(JSend::SUCCESS, $output);
    }

    public function sendMailAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jSend(JSend::FAIL, [
                'message' => $this->translate('Not an ajax request'), // @translate
            ], null, Response::STATUS_CODE_412);
        }

        // Data are checked inside contact us.
        $contactUs = $this->viewHelpers()->get('contactUs');

        $data = $this->params()->fromPost();
        $data['as_button'] = true;
        // $data['is_ajax'] => true;

        $result = $contactUs($data);
        if (!is_array($result)) {
            throw new \Omeka\Mvc\Exception\RuntimeException('Not ajax.'); // @translate
        }

        $message = (string) $result['message'];
        if ($result['status'] === JSend::SUCCESS) {
            $data = ['msg' => true];
        } elseif ($result['status'] === JSend::FAIL) {
            $data = ['msg' => $message];
            $message = null;
        } else {
            $data = [];
        }

        return $this->jSend($result['status'], $data, $message);
    }

    public function zipAction()
    {
        // Here, the id is the id with the token of the message.
        $id = $this->params('id');
        if (!$id) {
            throw new \Omeka\Mvc\Exception\NotFoundException('No resource set.'); // @translate
        }

        // Don't use api to skip check of rights.

        $id = strtok($id, '.');
        $token = strtok('.');
        if (!$id || !$token) {
            throw new \Omeka\Mvc\Exception\NotFoundException('Resource is invalid.'); // @translate
        }

        /** @var \ContactUs\Entity\Message $contactMessageEntity */
        $contactMessageEntity = $this->entityManager->find(\ContactUs\Entity\Message::class, $id);
        if (!$contactMessageEntity) {
            throw new \Omeka\Mvc\Exception\NotFoundException('No message found.'); // @translate
        }

        /** @var \ContactUs\Api\Representation\MessageRepresentation $contactMessage */
        $contactMessage = $this->messageAdapter->getRepresentation($contactMessageEntity);

        if ($token !== $contactMessage->token()) {
            throw new \Omeka\Mvc\Exception\NotFoundException('Resource does not exist.'); // @translate
        }

        if (!$contactMessage->resourceIds()) {
            throw new \Omeka\Mvc\Exception\RuntimeException('Resource has no file.'); // @translate
        }

        $filepath = $contactMessage->zipFilepath();

        $deleteZip = (int) $this->settings()->get('contactus_delete_zip');
        if ($deleteZip
            && $contactMessage->modified() < new \DateTime('-' . $deleteZip . ' day')
        ) {
            if (file_exists($filepath) && is_writeable($filepath)) {
                @unlink($filepath);
            }
            throw new \Omeka\Mvc\Exception\NotFoundException('No zip found: too much old.'); // @translate
        }

        // Check if the zip exists: it is prepared early.
        if (!file_exists($filepath) || !is_readable($filepath)) {
            throw new \Omeka\Mvc\Exception\NotFoundException('No zip found.'); // @translate
        }

        return $this->sendFile($filepath, [
            'content_type' => 'application/zip',
            'filename' => $id . '.zip',
            'disposition_mode' => 'attachment',
            'cache' => true,
        ]);
    }

    /**
     * Get selected resources from the query and check them.
     */
    protected function requestedResourceIds(): array
    {
        $params = $this->params();
        $id = $params->fromQuery('id');

        $isMultiple = is_array($id);
        $ids = $isMultiple ? $id : array_filter([$id]);

        $api = $this->api();

        // Check resources.
        // Search resources is currently not possible in Omeka S v
        $resourceIds = [];
        foreach ($ids as $id) {
            try {
                $api->read('resources', ['id' => $id])->getContent();
                $resourceIds[] = $id;
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                continue;
            }
        }

        return $resourceIds;
    }

    /**
     * List of resources as json-ld.
     */
    protected function listResources(array $resourceIds): array
    {
        if (!$resourceIds) {
            return [];
        }
        $api = $this->api();
        $result = array_fill_keys($resourceIds, null);
        foreach ($resourceIds as $id) {
            try {
                $result[$id] = $api->read('resources', ['id' => $id])->getContent()->jsonSerialize();
            } catch (\Exception $e) {
                // Skip. Normally, the list is already checked.
            }
        }
        return $result;
    }
}
