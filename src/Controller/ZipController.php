<?php declare(strict_types=1);

namespace ContactUs\Controller;

use Doctrine\ORM\EntityManager;
use Laminas\Mvc\Controller\AbstractActionController;
use ContactUs\Api\Adapter\MessageAdapter;

class ZipController extends AbstractActionController
{
    /**
     * @var \Doctrine\ORM\EntityManager;
     */
    protected $entityManager;

    /**
     * @var \ContactUs\Api\Adapter\MessageAdapter;
     */
    protected $adapter;

    /**
     * @var string
     */
    protected $basePath;

    public function __construct(EntityManager $entityManager, MessageAdapter $adapter, string $basePath)
    {
        $this->entityManager = $entityManager;
        $this->adapter = $adapter;
        $this->basePath = $basePath;
    }

    public function indexAction()
    {
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

        /** @var \ContactUs\Entity\Message $messageEntity */
        $messageEntity = $this->entityManager->find(\ContactUs\Entity\Message::class, $id);
        if (!$messageEntity) {
            throw new \Omeka\Mvc\Exception\NotFoundException('No message found.'); // @translate
        }

        /** @var \ContactUs\Api\Representation\MessageRepresentation $message */
        $message = $this->adapter->getRepresentation($messageEntity);

        if ($token !== $message->token()) {
            throw new \Omeka\Mvc\Exception\NotFoundException('Resource does not exist.'); // @translate
        }

        if (!$message->resourceIds()) {
            throw new \Omeka\Mvc\Exception\RuntimeException('Resource has no file.'); // @translate
        }

        $filepath = $this->basePath . '/contactus/' . $id . '.' . $token . '.zip';

        $deleteZip = (int) $this->settings->get('contactus_delete_zip');
        if ($deleteZip
            && $message->modified() < new \DateTime('-' . $deleteZip . ' day')
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

        $this->sendFile($filepath, 'application/zip', $id . '.zip', 'attachment', true);
    }

    /**
     * This is the 'file' action that is invoked when a user wants to download
     * the given file.
     *
     * @see \AccessResource\Controller\AccessResourceController::sendFile()
     * @see \DerivativeMedia\Controller\IndexController::sendFile()
     * @see \Statistics\Controller\DownloadController::sendFile()
     */
    protected function sendFile(
        string $filepath,
        string $mediaType,
        ?string $filename = null,
        // "inline" or "attachment".
        // It is recommended to set attribute "download" to link tag "<a>".
        ?string $dispositionMode = 'inline',
        ?bool $cache = false
    ): \Laminas\Http\PhpEnvironment\Response {
        $filename = $filename ?: basename($filepath);
        $filesize = (int) filesize($filepath);

        /** @var \Laminas\Http\PhpEnvironment\Response $response */
        $response = $this->getResponse();

        // Write headers.
        $headers = $response->getHeaders()
            ->addHeaderLine(sprintf('Content-Type: %s', $mediaType))
            ->addHeaderLine(sprintf('Content-Disposition: %s; filename="%s"', $dispositionMode, $filename))
            ->addHeaderLine(sprintf('Content-Length: %s', $filesize))
            ->addHeaderLine('Content-Transfer-Encoding: binary');
        if ($cache) {
            // Use this to open files directly.
            // Cache for 30 days.
            $headers
                ->addHeaderLine('Cache-Control: private, max-age=2592000, post-check=2592000, pre-check=2592000')
                ->addHeaderLine(sprintf('Expires: %s', gmdate('D, d M Y H:i:s', time() + (30 * 24 * 60 * 60)) . ' GMT'));
        }

        // Send headers separately to handle large files.
        $response->sendHeaders();

        // TODO Use Laminas stream response.

        // Clears all active output buffers to avoid memory overflow.
        $response->setContent('');
        while (ob_get_level()) {
            ob_end_clean();
        }
        readfile($filepath);

        // TODO Fix issue with session. See readme of module XmlViewer.
        ini_set('display_errors', '0');

        // Return response to avoid default view rendering and to manage events.
        return $response;
    }
}
