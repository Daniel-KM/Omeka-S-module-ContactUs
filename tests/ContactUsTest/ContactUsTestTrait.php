<?php declare(strict_types=1);

namespace ContactUsTest;

/**
 * Shared test helpers for ContactUs module tests.
 */
trait ContactUsTestTrait
{
    /**
     * @var \Laminas\ServiceManager\ServiceManager
     */
    protected $services;

    protected $namespace = 'ContactUs';

    protected function getServiceLocator(): \Laminas\ServiceManager\ServiceManager
    {
        if ($this->services === null) {
            $this->services = $this->getApplication()->getServiceManager();
        }
        return $this->services;
    }

    protected function api(): \Omeka\Api\Manager
    {
        return $this->getServiceLocator()->get('Omeka\ApiManager');
    }

    protected function loginAdmin(): void
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    protected function loginUser($email, $password = 'password'): \Laminas\Authentication\Result
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity($email);
        $adapter->setCredential($password);
        return $auth->authenticate();
    }

    protected function logout(): void
    {
        $this->getServiceLocator()->get('Omeka\AuthenticationService')->clearIdentity();
    }

    protected function createUserAndLogin(
        $email,
        $role = 'guest',
        $name = 'username',
        $password = 'password'
    ): \Omeka\Entity\User {
        $user = new \Omeka\Entity\User;
        $user->setEmail($email);
        $user->setName($name);
        $user->setRole($role);
        $user->setPassword($password);
        $user->setIsActive(true);
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $entityManager->persist($user);
        $entityManager->flush();

        $this->logout();
        $this->loginUser($email, $password);
        return $user;
    }

    public function rolesProvider()
    {
        return [
            'global_admin' => ['global_admin@example.org', 'global_admin', true],
            'site_admin' => ['site_admin@example.org', 'site_admin', true],
            'editor' => ['editor@example.org', 'editor', false],
            'reviewer' => ['reviewer@example.org', 'reviewer', false],
            'author' => ['author@example.org', 'author', false],
            'researcher' => ['researcher@example.org', 'researcher', false],
            'guest' => ['guest@example.org', 'guest', false],
        ];
    }

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
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
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
