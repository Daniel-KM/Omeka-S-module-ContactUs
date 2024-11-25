<?php declare(strict_types=1);

namespace ContactUsTest;

trait ContactUsTestTrait
{
    use \Generic\TesterTrait;

    protected $namespace = 'ContactUs';

    protected function createContactMessage($owner = null, $email = 'user@example.org'): \ContactUs\Entity\Message
    {
        $contactMessage = new \ContactUs\Entity\Message();
        $contactMessage
            ->setOwner($owner)
            ->setEmail($owner ? $owner->getEmail() : $email)
            ->setBody('test message body')
            ->setIp('127.0.0.1')
            ->setCreated(new \DateTime)
        ;
        $entityManager = $this->services->get('Omeka\EntityManager');
        $entityManager->persist($contactMessage);
        $entityManager->flush();
        return $contactMessage;
    }

    protected function getContactMessage($id): ?\ContactUs\Api\Representation\MessageRepresentation
    {
        try {
            return $this->api()->read('contact_messages', $id)->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return null;
        }
    }
}
