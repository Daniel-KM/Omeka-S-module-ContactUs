<?php declare(strict_types=1);

namespace ContactUs\Controller\Site;

use Common\Stdlib\PsrMessage;
use ContactUs\Api\Adapter\MessageAdapter;
use Doctrine\ORM\EntityManager;
use Laminas\Http\Response;
use Laminas\I18n\Translator\TranslatorAwareInterface;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Mvc\Exception\RuntimeException;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    const ERROR = 'error';
    const FAIL = 'fail';
    const SUCCESS = 'success';

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
            return $this->jSend(self::FAIL, [
                'message' => $this->translate('Not an ajax request'), // @translate
            ], null, Response::STATUS_CODE_412);
        }

        $requestedResourceIds = $this->requestedResourceIds();

        // TODO Factorize with view helper ContactUsSelector?

        $contactUsSelection = $this->viewHelpers()->get('contactUsSelection');

        // Manage the case where there the max number is set.
        $max = (int) $this->siteSettings()->get('contactus_selection_max');
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

        if ($isFail) {
            return $this->jSend(self::FAIL, [
                'selected_resources' => $newSelecteds,
            ], (string) (new PsrMessage(
                'It is not possible to select more than {total} resources.', // @translate
                ['total' => $max]
            ))->setTranslator($this->translator()));
        }

        return $this->jSend(self::SUCCESS, ['selected_resources' => $newSelecteds]);
    }

    public function sendMailAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jSend(self::FAIL, [
                'message' => $this->translate('Not an ajax request') // @translate
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
        if ($result['status'] === self::SUCCESS) {
            $data = ['msg' => true];
        } elseif ($result['status'] === self::FAIL) {
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
     * Send output via json according to jSend.
     *
     * Notes:
     * - Unlike jSend, any status can have a main message and a code.
     * - For statuses fail and error, the error messages are taken from
     *   messenger messages when not set.
     *
     * @see https://github.com/omniti-labs/jsend
     *
     * @throws \Laminas\Mvc\Exception\RuntimeException
     * @deprecated Use \Common\Mvc\Controller\Plugin\JSend (since Common version 3.4.65).
     */
    protected function jSend(
        string $status,
        ?array $data = null,
        ?string $message = null,
        ?int $httpStatusCode = null,
        ?int $code = null
    ) {
        switch ($status) {
            case self::SUCCESS:
                $json = [
                    'status' => self::SUCCESS,
                    'data' => $data,
                ];
                if (isset($message) && strlen($message)) {
                    $json['message'] = $message;
                }
                if (isset($code)) {
                    $json['code'] = $code;
                }
                break;

            case self::FAIL:
                if (!$data) {
                    $message = $message
                        ?: $this->translatedMessages('error')
                        ?: $this->translate('Check your input for invalid data.'); // @translate
                    $data = ['fail' => $message];
                }
                $json = [
                    'status' => self::FAIL,
                    'data' => $data,
                ];
                if (isset($message) && strlen($message)) {
                    $json['message'] = $message;
                }
                if (isset($code)) {
                    $json['code'] = $code;
                }
                $httpStatusCode ??= Response::STATUS_CODE_400;
                break;

            case self::ERROR:
                $message = $message
                    ?: $this->translatedMessages('error')
                    ?: $this->translate('An internal error has occurred.'); // @translate
                $json = [
                    'status' => self::ERROR,
                    'message' => $message,
                ];
                if ($data) {
                    $json['data'] = $data;
                }
                if (isset($code)) {
                    $json['code'] = $code;
                }
                $httpStatusCode ??= Response::STATUS_CODE_500;
                break;

            default:
                throw new RuntimeException(sprintf('The status "%s" is not supported by jSend.', $status)); // @translate
        }

        if ($httpStatusCode) {
            /** @var \Laminas\Http\Response $response */
            $response = $this->getResponse();
            $response->setStatusCode($httpStatusCode);
        }

        return new JsonModel($json);
    }

    /**
     * @deprecated Use $this->viewHelpers()->get('messages')->getTranslatedMessages() (since Common version 3.4.65).
     */
    protected function translatedMessages(string $type, bool $asArray = false)
    {
        /** @var \Common\View\Helper\Messages $messages */
        $messages = $this->viewHelpers()->get('messages');
        if (method_exists($messages, 'getTranslatedMessages')) {
            $msgs = $messages->getTranslatedMessages();
        } else {
            $translate = $this->translate();
            $translator = $translate->getTranslator();
            $msgs = array_map(
                fn ($msg) => $msg instanceof TranslatorAwareInterface
                    ? $msg->setTranslator($translator)->translate()
                    : $translate($msg),
                $messages->get()
            );
        }

        $msgs = $msgs[$type] ?? [];
        return $asArray
            ? $msgs
            : implode("\n", $msgs);
    }
}
