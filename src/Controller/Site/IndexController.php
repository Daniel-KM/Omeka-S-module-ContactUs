<?php declare(strict_types=1);

namespace ContactUs\Controller\Site;

use Common\Mvc\Controller\Plugin\JSend;
use Common\Stdlib\PsrMessage;
use ContactUs\Api\Adapter\MessageAdapter;
use Doctrine\ORM\EntityManager;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

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
        // Append data from module "Selection".
        if ($this->getPluginManager()->has('selectionContainer')
            && $siteSettings->get('contactus_selections_include_ids')
        ) {
            // Get and flat selected records.
            // TODO For now, only resources are managed, so no issues with "id".
            $selectionRecords = $this->selectionContainer()->records ?? [];
            $selectionRecords = array_column(array_merge(...array_values($selectionRecords)), 'id');
            $output['selections'] = array_values(array_intersect($newSelecteds, $selectionRecords));
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

        if (!hash_equals((string) $contactMessage->token(), (string) $token)) {
            throw new \Omeka\Mvc\Exception\NotFoundException('Resource does not exist.'); // @translate
        }

        if (!$contactMessage->resourceIds()) {
            throw new \Omeka\Mvc\Exception\RuntimeException('Resource has no file.'); // @translate
        }

        // The zip is built and streamed on the fly: nothing is stored on disk,
        // so there is no job to run nor old file to clean up. The files are
        // collected with full rights (token-authorized download) by the
        // representation, honouring the "include private" option.
        $files = $contactMessage->downloadableFiles();
        if (!$files) {
            throw new \Omeka\Mvc\Exception\NotFoundException('No file to zip.'); // @translate
        }

        return $this->streamZip($files, $id . '.zip');
    }


    /**
     * Stream a zip of the given files to the client without writing it to disk.
     *
     * @param array<int, array{filepath: string, source: string, mediatype:
     *   string, maintype: string}> $files
     */
    protected function streamZip(array $files, string $downloadName): Response
    {
        /** @var \Laminas\Http\PhpEnvironment\Response $response */
        $response = $this->getResponse();
        $response->getHeaders()
            ->addHeaderLine('Content-Type: application/zip')
            ->addHeaderLine(sprintf('Content-Disposition: attachment; filename="%s"', $downloadName))
            ->addHeaderLine('Content-Transfer-Encoding: binary')
            ->addHeaderLine('Cache-Control: no-cache, no-store, must-revalidate')
            ->addHeaderLine('Pragma: no-cache')
            ->addHeaderLine('Expires: 0');

        // Avoid a deprecation notice leaking into the binary stream.
        $errorReporting = error_reporting();
        error_reporting($errorReporting & ~E_DEPRECATED);
        $response->sendHeaders();
        error_reporting($errorReporting);

        $response->setContent('');
        while (ob_get_level()) {
            ob_end_clean();
        }

        // A large archive may stream for a long time over a slow connection.
        // So avoid kill by php execution time limit.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $zip = new ZipStream(
            outputName: $downloadName,
            sendHttpHeaders: false,
            defaultCompressionMethod: CompressionMethod::STORE,
        );

        // Media files (image, video, pdf…) are already compressed, so store
        // them; only lightly deflate text-like files.
        $usedNames = [];
        foreach ($files as $file) {
            $name = $this->uniqueFilename($file['source'], $usedNames);
            $usedNames[] = $name;
            $isText = $file['maintype'] === 'text'
                || $file['mediatype'] === 'application/json'
                || substr($file['mediatype'], -5) === '+json'
                || $file['mediatype'] === 'application/xml'
                || substr($file['mediatype'], -4) === '+xml';
            $zip->addFileFromPath(
                fileName: $name,
                path: $file['filepath'],
                compressionMethod: $isText ? CompressionMethod::DEFLATE : CompressionMethod::STORE,
            );
        }

        $zip->finish();

        // Prevent the MVC from appending anything to the streamed output.
        ini_set('display_errors', '0');

        return $response;
    }

    /**
     * Build a filename unique in the archive, keeping the source extension.
     *
     * @param string[] $used
     */
    protected function uniqueFilename(string $source, array $used): string
    {
        $base = pathinfo($source, PATHINFO_FILENAME);
        $extension = pathinfo($source, PATHINFO_EXTENSION);
        $i = 0;
        do {
            $name = $base . ($i ? '.' . $i : '') . (strlen($extension) ? '.' . $extension : '');
            ++$i;
        } while (in_array($name, $used, true));
        return $name;
    }

    /**
     * Get selected resources from the query and check them.
     */
    protected function requestedResourceIds(): array
    {
        $params = $this->params();
        $id = $params->fromQuery('id');

        $isMultiple = is_array($id);
        $ids = array_filter(array_map('intval', $isMultiple ? $id : [$id]));
        if (!$ids) {
            return [];
        }

        // Batch via the polymorphic resources adapter to keep a single query,
        // then preserve the original order.
        $existingIds = $this->api()
            ->search('resources', ['id' => $ids], ['returnScalar' => 'id'])
            ->getContent();
        $existingIds = array_map('intval', array_values($existingIds));

        return array_values(array_intersect($ids, $existingIds));
    }

    /**
     * List of resources as json-ld.
     */
    protected function listResources(array $resourceIds): array
    {
        if (!$resourceIds) {
            return [];
        }
        $result = array_fill_keys($resourceIds, null);
        $resources = $this->api()->search('resources', ['id' => $resourceIds])->getContent();
        foreach ($resources as $resource) {
            $result[$resource->id()] = $resource->jsonSerialize();
        }
        return $result;
    }
}
