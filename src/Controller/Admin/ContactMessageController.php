<?php declare(strict_types=1);

namespace ContactUs\Controller\Admin;

use DateTime;
use Doctrine\DBAL\Connection;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Omeka\Form\ConfirmForm;
use Omeka\Stdlib\ErrorStore;

class ContactMessageController extends AbstractActionController
{
    /**
     * @var \Doctrine\DBAL\Connection $connection
     */
    protected $connection;

    /**
     * @param string
     */
    protected $basePath;

    public function __construct(Connection $connection, string $basePath)
    {
        $this->connection = $connection;
        $this->basePath = $basePath;
    }

    public function browseAction()
    {
        $this->deleteZips();

        $this->setBrowseDefaults('created');
        $response = $this->api()->search('contact_messages', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults());

        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete'], true));
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');
        $formDeleteSelected->setButtonLabel('Confirm Delete'); // @translate

        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete-all'], true));
        $formDeleteAll->setAttribute('id', 'confirm-delete-all');
        $formDeleteAll->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteAll->get('submit')->setAttribute('disabled', true);

        $contactMessages = $response->getContent();

        return new ViewModel([
            'messages' => $contactMessages,
            'resources' => $contactMessages,
            'formDeleteSelected' => $formDeleteSelected,
            'formDeleteAll' => $formDeleteAll,
        ]);
    }

    public function showDetailsAction()
    {
        $response = $this->api()->read('contact_messages', $this->params('id'));
        $contactMessage = $response->getContent();

        $view = new ViewModel([
            'resource' => $contactMessage,
        ]);
        return $view
            ->setTerminal(true);
    }

    public function deleteConfirmAction()
    {
        $response = $this->api()->read('contact_messages', $this->params('id'));
        $contactMessage = $response->getContent();

        $view = new ViewModel([
            'message' => $contactMessage,
            'resource' => $contactMessage,
            'resourceLabel' => 'contact message',
            'partialPath' => 'contact-us/admin/contact-message/show-details',
        ]);
        return $view
            ->setTemplate('common/delete-confirm-details')
            ->setTerminal(true);
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            /** @var \Omeka\Form\ConfirmForm $form */
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api($form)->delete('contact_messages', $this->params('id'));
                if ($response) {
                    $this->messenger()->addSuccess('Contact message successfully deleted.'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute('admin/contact-message', ['action' => 'browse'], true);
    }

    public function batchDeleteConfirmAction()
    {
        /** @var \Omeka\Form\ConfirmForm $form */
        $form = $this->getForm(ConfirmForm::class);
        $routeAction = $this->params()->fromQuery('all') ? 'batch-delete-all' : 'batch-delete';
        $form->setAttribute('action', $this->url()->fromRoute(null, ['action' => $routeAction], true));
        $form->setAttribute('id', 'batch-delete-confirm');
        $form->setAttribute('class', $routeAction);
        $form->setButtonLabel('Confirm delete'); // @translate

        $view = new ViewModel([
            'form' => $form,
        ]);
        return $view
            ->setTerminal(true);
    }

    public function batchDeleteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resourceIds = $this->params()->fromPost('resource_ids', []);
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one contact message to batch delete.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $response = $this->api($form)->batchDelete('contact_messages', $resourceIds, [], ['continueOnError' => true]);
            if ($response) {
                $this->messenger()->addSuccess('Contact messages successfully deleted.'); // @translate
            }
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    public function batchDeleteAllAction(): void
    {
        $this->messenger()->addError('Delete of all contact messages is not supported currently.'); // @translate
    }

    public function batchSetReadAction()
    {
        return $this->batchUpdateProperty(['o-module-contact:is_read' => true]);
    }

    public function batchSetNotReadAction()
    {
        return $this->batchUpdateProperty(['o-module-contact:is_read' => false]);
    }

    public function batchSetSpamAction()
    {
        return $this->batchUpdateProperty(['o-module-contact:is_spam' => true]);
    }

    public function batchSetNotSpamAction()
    {
        return $this->batchUpdateProperty(['o-module-contact:is_spam' => false]);
    }

    protected function batchUpdateProperty(array $data)
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new \Omeka\Mvc\Exception\NotFoundException;
        }

        $resourceIds = $this->params()->fromQuery('resource_ids', []);
        // Secure the input.
        $resourceIds = array_filter(array_map('intval', $resourceIds));
        if (empty($resourceIds)) {
            return $this->returnError('No contact messages submitted.', Response::STATUS_CODE_400); // @translate
        }

        $response = $this->api()
            ->batchUpdate('contact_messages', $resourceIds, $data, ['continueOnError' => true]);
        if (!$response) {
            return $this->returnError('An internal error occurred.', Response::STATUS_CODE_500); // @translate
        }

        $value = reset($data);
        $property = key($data);

        $statuses = [
            'o-module-contact:is_read' => ['not-read', 'read'],
            'o-module-contact:is_spam' => ['not-spam', 'spam'],
        ];

        return new JsonModel([
            'status' => 'success',
            'data' => [
                'action' => [
                    'property' => $property,
                    'value' => $value,
                    'status' => $statuses[$property][(int) $value],
                ],
            ],
        ]);
    }

    public function toggleReadAction()
    {
        return $this->toggleProperty('o-module-contact:is_read');
    }

    public function toggleSpamAction()
    {
        return $this->toggleProperty('o-module-contact:is_spam');
    }

    protected function toggleProperty(string $property)
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new \Omeka\Mvc\Exception\NotFoundException;
        }

        $id = $this->params('id');
        /** @var \ContactUs\Api\Representation\MessageRepresentation $contactMessage */
        $contactMessage = $this->api()->read('contact_messages', $id)->getContent();

        switch ($property) {
            case 'o-module-contact:is_read':
                $value = !$contactMessage->isRead();
                break;
            case 'o-module-contact:is_spam':
                $value = !$contactMessage->isSpam();
                break;
            default:
                return $this->returnError('Unknown key.', Response::STATUS_CODE_400); // @translate
        }

        $data = [];
        $data[$property] = $value;
        $response = $this->api()
            ->update('contact_messages', $id, $data, [], ['isPartial' => true]);
        if (!$response) {
            return $this->returnError('An internal error occurred.', Response::STATUS_CODE_500); // @translate
        }

        $statuses = [
            'o-module-contact:is_read' => ['not-read', 'read'],
            'o-module-contact:is_spam' => ['not-spam', 'spam'],
        ];

        return new JsonModel([
            'status' => 'success',
            'data' => [
                'action' => [
                    'property' => $property,
                    'value' => $value,
                    'status' => $statuses[$property][(int) $value],
                ],
            ],
        ]);
    }

    public function toggleZipAction()
    {
        // ZIp is not managed by adapter, but by file system.

        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new \Omeka\Mvc\Exception\NotFoundException;
        }

        $id = $this->params('id');

        /** @var \ContactUs\Api\Representation\MessageRepresentation $contactMessage */
        $contactMessage = $this->api()->read('contact_messages', $id)->getContent();

        $hasZip = $contactMessage->hasZip();
        $message = null;
        if ($hasZip) {
            @unlink($contactMessage->zipFilepath());
            $status = 'success';
            $hasZip = false;
        } elseif ($contactMessage->resourceIds()) {
            $type = $this->settings()->get('contactus_create_zip', 'original');
            $this->jobDispatcher()->dispatch(\ContactUs\Job\ZipResources::class, [
                'id' => $contactMessage->resourceIds(),
                'filename' => $contactMessage->zipFilename(),
                'baseDir' => 'contactus',
                'baseUri' => 'contactus',
                'type' => $type,
            ]);
            $status = 'success';
            $hasZip = true;
            // Useless message: it is quick.
            // $message = $this->translate('A zip with the files is created in background.'); // @translate
        } else {
            $status = 'fail';
            $hasZip = false;
            $message = $this->translate('There is no resources or files.'); // @translate
        }

        $output = [
            'status' => $status,
            'data' => [
                'action' => [
                    'property' => 'o-module-contact:has_zip',
                    'value' => $hasZip,
                    'status' => $hasZip ? 'zip' : 'no-zip',
                ],
            ],
        ];

        if ($message) {
            $output['message'] = $message;
        }

        return new JsonModel($output);
    }

    protected function deleteZips(): void
    {
        $deleteZip = (int) $this->settings()->get('contactus_delete_zip');
        if (!$deleteZip) {
            return;
        }

        // Get all messages older than x days.
        $older = new DateTime('-' . $deleteZip . ' day');
        /*
        $contactMessageIds = $this->api()->search('contact_messages', [
            'modified_before' => $older->format('Y-m-d\TH:i:s'),
        ], ['returnScalar' => 'id'])->getContent();
        if (!count($contactMessageIds)) {
            return;
        }
        */

        // For performance purpose, use a direct sql query.
        $sql = <<<'SQL'
SELECT
    `id`,
    SUBSTRING(REPLACE(REPLACE(REPLACE(TO_BASE64(SHA2(CONCAT(id, '/', email, '/', ip, '/', user_agent, '/', created), 256)), '+', ''), '/', ''), '=', ''), 1, 12) AS "token"
FROM contact_message
WHERE modified < :older
;
SQL;
        $bind = [
            'older' => $older->format('Y-m-d H:i:s'),
        ];
        $types = [
            'older' => \Doctrine\DBAL\ParameterType::STRING,
        ];
        $tokens = $this->connection->executeQuery($sql, $bind, $types)->fetchAllKeyValue();

        /** @see \ContactUs\Api\Representation\MessageRepresentation::zipFilepath() */
        foreach ($tokens as $id => $token) {
            $filename = $id . '.' . $token . '.zip';
            $filepath = $this->basePath . '/contactus/' . $filename;
            $fileExists = file_exists($filepath) && is_writeable($filepath);
            if ($fileExists) {
                @unlink($filepath);
            }
        }
    }

    /**
     * Return a message of error.
     *
     * @see https://github.com/omniti-labs/jsend
     *
     * @param \Common\Stdlib\PsrMessage|string $message
     * @param int $statusCode
     * @param \Omeka\Stdlib\ErrorStore|array $messages
     * @return \Laminas\View\Model\JsonModel
     */
    protected function returnError($message, ?int $statusCode = Response::STATUS_CODE_400, $messages = null): JsonModel
    {
        $statusCode ??= Response::STATUS_CODE_400;

        $response = $this->getResponse();
        $response->setStatusCode($statusCode);

        $translator = $this->translator();

        if (is_array($messages) && count($messages)) {
            foreach ($messages as &$msg) {
                is_object($msg) ? $msg->setTranslator($translator) : $this->translate($msg);
            }
            unset($msg);
        } elseif (is_object($messages) && $messages instanceof ErrorStore && $messages->hasErrors()) {
            $msgs = [];
            foreach ($messages->getErrors() as $key => $msg) {
                $msgs[$key] = is_object($msg) ? $msg->setTranslator($translator) : $this->translate($msg);
            }
            $messages = $msgs;
        } else {
            $messages = [];
        }

        $status = $statusCode >= 500 ? 'error' : 'fail';

        $result = [];
        $result['status'] = $status;

        if (is_object($message)) {
            $message->setTranslator($translator);
        } elseif ($message) {
            $message = $this->translate($message);
        } elseif ($status === 'error') {
            // A message is required for error.
            if ($messages) {
                $message = reset($messages);
                if (count($messages) === 1) {
                    $messages = [];
                }
            } else {
                $message = $this->translate('An error occurred.'); // @translate;
            }
        }

        // Normally, only in error, not fail, but a main message may be useful
        // in any case.
        if ($message) {
            $result['message'] = $message;
        }

        // Normally, not in error.
        if (count($messages)) {
            $result['data'] = $messages;
        }

        return new JsonModel($result);
    }
}
