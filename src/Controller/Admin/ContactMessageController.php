<?php declare(strict_types=1);

namespace ContactUs\Controller\Admin;

use Doctrine\DBAL\Connection;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Common\Stdlib\PsrMessage;
use ContactUs\Form\QuickSearchForm;
use ContactUs\Form\SendMessageForm;
use ContactUs\Job\ResendToAuthor;
use ContactUs\Stdlib\MessageResender;
use Omeka\Form\ConfirmForm;
use Omeka\Stdlib\ErrorStore;

class ContactMessageController extends AbstractActionController
{
    /**
     * @var \Doctrine\DBAL\Connection $connection
     */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function browseAction()
    {
        $this->setBrowseDefaults('created');

        $query = $this->params()->fromQuery();

        // Hide spam by default. The sidebar quick-search form can override with
        // "1" (only spam) or "any" (show both).
        if (!isset($query['is_spam'])) {
            $query['is_spam'] = '0';
        }
        $apiQuery = $query;
        if ((string) $apiQuery['is_spam'] === 'any') {
            unset($apiQuery['is_spam']);
        }

        $response = $this->api()->search('contact_messages', $apiQuery);
        $this->paginator($response->getTotalResults());

        $formSearch = $this->getForm(QuickSearchForm::class);
        $formSearch->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'browse'], true));
        $formSearch->setData($query);

        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete'], true));
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');
        $formDeleteSelected->setButtonLabel('Confirm Delete'); // @translate

        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete-all'], true));
        $formDeleteAll->setAttribute('id', 'confirm-delete-all');
        $formDeleteAll->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteAll->get('submit')->setAttribute('disabled', true);

        $formResendSelected = $this->getForm(ConfirmForm::class);
        $formResendSelected->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-resend'], true));
        $formResendSelected->setAttribute('id', 'confirm-resend-selected');
        $formResendSelected->setButtonLabel('Confirm resend'); // @translate

        $countResendable = $this->api()
            ->search('contact_messages', ['resendable' => '1', 'page' => 1, 'per_page' => 1] + $apiQuery)
            ->getTotalResults();

        $formResendAll = $this->getForm(ConfirmForm::class);
        $formResendAll->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-resend-all'], true));
        $formResendAll->setAttribute('id', 'confirm-resend-all');
        $formResendAll->setButtonLabel('Confirm resend'); // @translate
        $formResendAll->get('submit')->setAttribute('disabled', !$countResendable);
        $formResendAll->add([
            'name' => 'query',
            'type' => \Laminas\Form\Element\Hidden::class,
            'attributes' => [
                'value' => json_encode($apiQuery, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ]);

        $contactMessages = $response->getContent();

        $settings = $this->settings();
        $formSendMessage = $this->getForm(SendMessageForm::class);
        $formSendMessage->get('subject')->setValue((string) $settings->get('contactus_reply_subject'));
        $formSendMessage->get('body')->setValue((string) $settings->get('contactus_reply_body'));
        // When a support reply-to is set, the answering admin is no longer the
        // reply-to, so default to a discreet copy (bcc); else the admin is the
        // reply-to and needs no copy.
        $formSendMessage->get('myself')->setValue($settings->get('contactus_reply_to_email') ? 'bcc' : '');

        return new ViewModel([
            'messages' => $contactMessages,
            'resources' => $contactMessages,
            'formSearch' => $formSearch,
            'formDeleteSelected' => $formDeleteSelected,
            'formDeleteAll' => $formDeleteAll,
            'formResendSelected' => $formResendSelected,
            'formResendAll' => $formResendAll,
            'countResendable' => $countResendable,
            'formSendMessage' => $formSendMessage,
        ]);
    }

    public function sendMessageAction()
    {
        if (!$this->getRequest()->isPost()) {
            throw new \Omeka\Mvc\Exception\NotFoundException;
        }

        $id = $this->params('id');
        /** @var \ContactUs\Api\Representation\MessageRepresentation $contactMessage */
        $contactMessage = $this->api()->read('contact_messages', $id)->getContent();

        $toEmail = $contactMessage->email();
        if (!$toEmail) {
            return $this->jSend()->fail(null, $this->translate(
                'No email defined for this contact message.' // @translate
            ));
        }

        $params = $this->params();

        $body = trim((string) $params->fromPost('body'));
        $bodyCheck = $this->prepareMessage()->validateBody($body);
        if (!$bodyCheck['valid']) {
            return $this->jSend()->fail(null, $this->translate($bodyCheck['error']));
        }

        $subject = trim((string) $params->fromPost('subject'));
        if (!strlen($subject)) {
            $subject = $this->settings()->get('contactus_reply_subject')
                ?: $this->translate('Re: {subject}'); // @translate
        }

        $placeholders = [
            'name' => (string) $contactMessage->name(),
            'email' => (string) $contactMessage->email(),
            'subject' => (string) $contactMessage->subject(),
            'message' => (string) $contactMessage->body(),
        ];
        $subject = $this->prepareMessage()->fillMessage($subject, $placeholders);
        $body = $this->prepareMessage()->fillMessage($body, $placeholders);

        $subjectCheck = $this->prepareMessage()->validateSubject($subject, 190);
        if (!$subjectCheck['valid']) {
            return $this->jSend()->fail(null, $this->translate($subjectCheck['error']));
        }

        $to = [$toEmail => (string) $contactMessage->name()];
        $replyTo = $this->replyToAddress();

        // The from stays the unique installation sender; copies to the
        // answering admin are optional, exclusive (cc or bcc), via the form
        // radio.
        $cc = null;
        $bcc = null;
        $myself = $params->fromPost('myself');
        $user = $this->identity();
        if ($user && $myself === 'cc') {
            $cc = [$user->getEmail() => (string) $user->getName()];
        } elseif ($user && $myself === 'bcc') {
            $bcc = [$user->getEmail() => (string) $user->getName()];
        }

        /** @see \Common\Mvc\Controller\Plugin\SendEmail */
        $result = $this->sendEmail($body, $subject, $to, null, $cc, $bcc, $replyTo);
        if (!$result) {
            return $this->jSend()->error(null, $this->translate(
                'Sorry, the message cannot be sent. Contact the administrator.' // @translate
            ));
        }

        // Mark the message as read once it has been answered.
        if (!$contactMessage->isRead()) {
            $this->api()->update('contact_messages', $id, ['o-module-contact:is_read' => true], [], ['isPartial' => true]);
        }

        $message = new PsrMessage(
            'Message successfully sent to {email}.', // @translate
            ['email' => $toEmail]
        );
        return $this->jSend()->success([
            'contact_message' => $id,
        ], $message->setTranslator($this->translator()));
    }

    /**
     * Resolve the reply-to address: the configured support address, else the
     * connected admin. The sender (from) is the unique installation address.
     */
    protected function replyToAddress(): ?array
    {
        $email = $this->settings()->get('contactus_reply_to_email');
        if ($email) {
            return [$email => ''];
        }
        $user = $this->identity();
        if ($user) {
            return [$user->getEmail() => (string) $user->getName()];
        }
        return null;
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

    public function resendAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest() || !$this->getRequest()->isPost()) {
            throw new \Omeka\Mvc\Exception\NotFoundException;
        }

        $services = $this->getEvent()->getApplication()->getServiceManager();
        $result = (new MessageResender($services))([(int) $this->params('id')]);

        $message = new PsrMessage(
            '{count_sent} message(s) resent to the author, {count_skipped} skipped (already sent, already resent or to us), {count_errors} error(s).', // @translate
            ['count_sent' => count($result['sent']), 'count_skipped' => count($result['skipped']), 'count_errors' => count($result['errors'])]
        );
        $translator = $this->translator();
        return new JsonModel([
            'status' => $result['errors'] && !$result['sent'] ? 'fail' : 'success',
            'message' => $message->setTranslator($translator)->translate(),
            'data' => [
                'sent' => $result['sent'],
                'skipped' => $result['skipped'],
                'errors' => array_map(fn (PsrMessage $error) => $error->setTranslator($translator)->translate(), $result['errors']),
            ],
        ]);
    }

    /**
     * Resend the selected messages to the author in a background job.
     */
    public function batchResendAction()
    {
        $resourceIds = array_filter(array_map('intval', (array) $this->params()->fromPost('resource_ids', [])));
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one contact message to resend.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }
        return $this->dispatchResend(['ids' => $resourceIds]);
    }

    /**
     * Resend all the messages of the current query to the author in a job.
     */
    public function batchResendAllAction()
    {
        $query = json_decode((string) $this->params()->fromPost('query'), true);
        return $this->dispatchResend(['query' => is_array($query) ? $query : []]);
    }

    protected function dispatchResend(array $args)
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if (!$form->isValid()) {
            $this->messenger()->addFormErrors($form);
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $job = $this->jobDispatcher()->dispatch(ResendToAuthor::class, $args);
        $message = new PsrMessage(
            'Resending messages to the author in background ({link}job #{job_id}{link_end}, {link_log}logs{link_end}).', // @translate
            [
                'link' => sprintf('<a href="%s">', htmlspecialchars($this->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))),
                'job_id' => $job->getId(),
                'link_end' => '</a>',
                'link_log' => class_exists('Log\Module', false)
                    ? sprintf('<a href="%1$s">', htmlspecialchars($this->url()->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]])))
                    : sprintf('<a href="%1$s" target="_blank" rel="noopener noreferrer">', htmlspecialchars($this->url()->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()]))),
            ]
        );
        $message->setEscapeHtml(false);
        $this->messenger()->addSuccess($message);
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
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
