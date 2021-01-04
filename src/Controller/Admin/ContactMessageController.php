<?php declare(strict_types=1);

namespace ContactUs\Controller\Admin;

use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Omeka\Form\ConfirmForm;

class ContactMessageController extends AbstractActionController
{
    public function browseAction()
    {
        $this->setBrowseDefaults('created');
        $response = $this->api()->search('contact_messages', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults());

        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete'], true));
        $formDeleteSelected->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');

        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete-all'], true));
        $formDeleteAll->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteAll->setAttribute('id', 'confirm-delete-all');
        $formDeleteAll->get('submit')->setAttribute('disabled', true);

        $messages = $response->getContent();
        return new ViewModel([
            'messages' => $messages,
            'resources' => $messages,
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
        $message = $response->getContent();

        $view = new ViewModel([
            'message' => $message,
            'resource' => $message,
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
        $form->setButtonLabel('Confirm delete'); // @translate
        $form->setAttribute('id', 'batch-delete-confirm');
        $form->setAttribute('class', $routeAction);

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
            return $this->jsonError('No contact messages submitted.', Response::STATUS_CODE_400); // @translate
        }

        $response = $this->api()
            ->batchUpdate('contact_messages', $resourceIds, $data, ['continueOnError' => true]);
        if (!$response) {
            return $this->jsonError('An internal error occurred.', Response::STATUS_CODE_500); // @translate
        }

        $value = reset($data);
        $property = key($data);

        $statuses = [
            'o-module-contact:is_read' => ['not-read', 'read'],
            'o-module-contact:is_spam' => ['not-spam', 'spam'],
        ];

        return new JsonModel([
            'content' => [
                'property' => $property,
                'value' => $value,
                'status' => $statuses[$property][(int) $value],
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

    protected function toggleProperty($property)
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new \Omeka\Mvc\Exception\NotFoundException;
        }

        $id = $this->params('id');
        /** @var \ContactUs\Api\Representation\MessageRepresentation $message */
        $message = $this->api()->read('contact_messages', $id)->getContent();

        switch ($property) {
            case 'o-module-contact:is_read':
                $value = !$message->isRead();
                break;
            case 'o-module-contact:is_spam':
                $value = !$message->isSpam();
                break;
            default:
                return $this->jsonError('Unknown key.', Response::STATUS_CODE_400); // @translate
        }

        $data = [];
        $data[$property] = $value;
        $response = $this->api()
            ->update('contact_messages', $id, $data, [], ['isPartial' => true]);
        if (!$response) {
            return $this->jsonError('An internal error occurred.', Response::STATUS_CODE_500); // @translate
        }

        $statuses = [
            'o-module-contact:is_read' => ['not-read', 'read'],
            'o-module-contact:is_spam' => ['not-spam', 'spam'],
        ];

        return new JsonModel([
            'content' => [
                'property' => $property,
                'value' => $value,
                'status' => $statuses[$property][(int) $value],
            ],
        ]);
    }

    /**
     * Return a message of error.
     *
     * @param string $message
     * @param int $statusCode
     * @param array $messages
     * @return \Laminas\View\Model\JsonModel
     */
    protected function jsonError($message, $statusCode = Response::STATUS_CODE_400, array $messages = [])
    {
        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        $output = ['error' => $message];
        if ($messages) {
            $output['messages'] = $messages;
        }
        return new JsonModel($output);
    }
}
