<?php declare(strict_types=1);

namespace ContactUs\Controller\Site;

use ContactUs\Api\Adapter\MessageAdapter;
use Doctrine\ORM\EntityManager;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    /**
     * @var \Doctrine\ORM\EntityManager;
     */
    protected $entityManager;

    /**
     * @var \ContactUs\Api\Adapter\MessageAdapter;
     */
    protected $messageAdapter;

    public function __construct(
        EntityManager $entityManager,
        MessageAdapter $messageAdapter
    ) {
        $this->entityManager = $entityManager;
        $this->messageAdapter = $messageAdapter;
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

        return new ViewModel([
            'site' => $this->currentSite(),
            'user' => $user,
            'resourceIds' => $resourceIds,
        ]);
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
            return $this->jsonErrorNotFound();
        }

        $requestedResourceIds = $this->requestedResourceIds();

        $newSelecteds = $this->viewHelpers()->get('contactUsSelection')($requestedResourceIds);

        return new JsonModel([
            'status' => 'success',
            'data' => [
                'selected_resources' => $newSelecteds,
            ],
        ]);
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

    protected function jsonErrorNotFound(): JsonModel
    {
        $response = $this->getResponse();
        $response->setStatusCode(Response::STATUS_CODE_404);
        return new JsonModel([
            'status' => 'error',
            'message' => $this->translate('Not found'), // @translate
        ]);
    }

    protected function jsonInternalError(): JsonModel
    {
        $response = $this->getResponse();
        $response->setStatusCode(Response::STATUS_CODE_500);
        return new JsonModel([
            'status' => 'error',
            'message' => $this->translate('An internal error occurred.'), // @translate
        ]);
    }
}
