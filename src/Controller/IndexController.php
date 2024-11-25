<?php declare(strict_types=1);

namespace ContactUs\Controller;

use ContactUs\Api\Adapter\MessageAdapter;
use Doctrine\ORM\EntityManager;
use Laminas\Mvc\Controller\AbstractActionController;

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
}
