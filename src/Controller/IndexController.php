<?php declare(strict_types=1);

namespace ContactUs\Controller;

use ContactUs\Api\Adapter\MessageAdapter;
use Doctrine\ORM\EntityManager;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Session\Container;
use Laminas\View\Model\JsonModel;
use Omeka\Entity\User;

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

        $user = $this->identity();
        $resourcesData = $this->requestedResources();

        return $user
            ? $this->toggleDb($resourcesData['resource_ids'], $resourcesData['is_multiple'], $user)
            : $this->toggleSession($resourcesData['resource_ids'], $resourcesData['is_multiple']);
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
     * Get selected resources from the query and prepare them.
     */
    protected function requestedResources(): array
    {
        $params = $this->params();
        $id = $params->fromQuery('id');

        $isEmpty = !$id;
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

        return [
            'has_result' => !$isEmpty,
            'is_multiple' => $isMultiple,
            'resource_ids' => $resourceIds,
        ];
    }

    /**
     * Select resource(s) to add or remove to a selection for contact us.
     */
    protected function toggleDb(array $resourceIds, bool $isMultiple, User $user): array
    {
        $userSettings = $this->userSettings();
        $alreadySelecteds = $userSettings->get('contactus_selected_resources') ?: [];
        $newSelecteds = array_values(array_diff($resourceIds, $alreadySelecteds));
        $userSettings->set('contactus_selected_resources', $newSelecteds);
        return new JsonModel([
            'status' => 'success',
            'data' => [
                'selected_resources' => $newSelecteds,
            ],
        ]);
    }

    /**
     * Select resource(s) to add or remove to a local selection for contact us.
     */
    protected function toggleSession(array $resourceIds, bool $isMultiple)
    {
        $container = new Container('ContactUsSelection');
        $alreadySelecteds = $container->selected_resources ?? [];
        $newSelecteds = array_values(array_diff($resourceIds, $alreadySelecteds));
        $container->selected_resources = $newSelecteds;
        return new JsonModel([
            'status' => 'success',
            'data' => [
                'selected_resources' => $newSelecteds,
            ],
        ]);
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
