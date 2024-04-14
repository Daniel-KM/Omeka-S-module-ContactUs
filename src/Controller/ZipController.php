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

        $deleteZip = (int) $this->settings()->get('contactus_delete_zip');
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

        return $this->sendFile($filepath, [
            'content_type' => 'application/zip',
            'filename' => $id . '.zip',
            'disposition_mode' => 'attachment',
            'cache' => true,
        ]);
    }
}
