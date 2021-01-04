<?php declare(strict_types=1);

namespace ContactUsTest\Api\Adapter;

use ContactUsTest\ContactUsTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * DBTestCase mocks main services, so it cannot be used simply directly for
 * common tests here.
 *
 * @see \ContactUs\Api\Adapter\MessageAdapter
 */
class MessageAdapterTest extends AbstractHttpControllerTestCase
{
    use ContactUsTestTrait;

    protected $adapter;

    public function setUp(): void
    {
        parent::setUp();
        $this->services = $this->getApplication()->getServiceManager();
        $this->loginAdmin();
        $this->adapter = $this->services->get('Omeka\ApiAdapterManager')->get('contact_messages');
    }

    public function tearDown(): void
    {
        $this->logout();
        $this->services = null;
    }

    /** @test */
    public function apiAdapterExists(): void
    {
        $this->assertNotNull($this->adapter);
    }

    /** @test */
    public function getResourceName(): void
    {
        $this->assertEquals('contact_messages', $this->adapter->getResourceName());
    }

    /** @test */
    public function getRepresentationClass(): void
    {
        $this->assertEquals(\ContactUs\Api\Representation\MessageRepresentation::class, $this->adapter->getRepresentationClass());
    }

    /** @test */
    public function getEntityClass(): void
    {
        $this->assertEquals(\ContactUs\Entity\Message::class, $this->adapter->getEntityClass());
    }

    public function buildQuery(): void
    {
    }

    public function hydrate(): void
    {
        // Checked via the api below.
    }

    /** @test */
    public function validateEntity(): void
    {
        $entity = new \ContactUs\Entity\Message;
        $errorStore = new \Omeka\Stdlib\ErrorStore();
        $this->adapter->validateEntity($entity, $errorStore);
        $this->assertTrue($errorStore->hasErrors());

        $entity
            ->setEmail('test@example.com')
            ->setBody('message body')
            ->setIp('127.0.0.1');
        $errorStore = new \Omeka\Stdlib\ErrorStore();
        $this->adapter->validateEntity($entity, $errorStore);
        $this->assertFalse($errorStore->hasErrors());

        $entity
            ->setEmail('test-example.com')
            ->setBody('message body')
            ->setIp('127.0.0.1');
        $errorStore = new \Omeka\Stdlib\ErrorStore();
        $this->adapter->validateEntity($entity, $errorStore);
        $this->assertTrue($errorStore->hasErrors());

        $entity
            ->setEmail('test@example.com')
            ->setBody('  ')
            ->setIp('127.0.0.1');
        $errorStore = new \Omeka\Stdlib\ErrorStore();
        $this->adapter->validateEntity($entity, $errorStore);
        $this->assertTrue($errorStore->hasErrors());

        $entity
            ->setEmail('test@example.com')
            ->setBody('message body')
            ->setIp('');
        $errorStore = new \Omeka\Stdlib\ErrorStore();
        $this->adapter->validateEntity($entity, $errorStore);
        $this->assertTrue($errorStore->hasErrors());
    }

    /** @test */
    public function apiCreateContactMessage(): void
    {
        $response = $this->api()->create('contact_messages', [
            'o:email' => 'user@example.org',
            'o-module-contact:body' => 'test contact message',
        ]);
        $message = $response->getContent();
        $this->assertEquals(
            \ContactUs\Api\Representation\MessageRepresentation::class,
            get_class($message)
        );

        $this->assertEquals(
            'admin@example.com',
            $message->email()
        );
    }

    /**
     * @test
     * @depends apiCreateContactMessage
     */
    public function apiCreateContactMessageNoUser(): void
    {
        $this->logout();
        $response = $this->api()->create('contact_messages', [
            'o:email' => 'user@example.org',
            'o-module-contact:body' => 'body',
        ]);
        $message = $response->getContent();
        $this->assertNotEmpty($message);
    }

    /**
     * @test
     * @depends apiCreateContactMessage
     * @expectedException \Omeka\Api\Exception\ValidationException
     */
    public function apiCreateContactMessageNoUserNoEmail(): void
    {
        $this->logout();
        $response = $this->api()->create('contact_messages', [
            'o-module-contact:subject' => 'subject',
            'o-module-contact:body' => 'body',
        ]);
        $message = $response->getContent();
        $this->assertEmpty($message);
    }

    /**
     * @test
     * @depends apiCreateContactMessage
     * @dataProvider rolesProvider
     */
    public function apiReadContactMessage($email, $role): void
    {
        $user = $this->createUserAndLogin($email, $role);
        $message = $this->createContactMessage($user);
        $messageId = $message->getId();
        $message = $this->api()->read('contact_messages', $messageId)->getContent();
        $this->assertEquals($messageId, $message->id());
    }

    /**
     * @test
     * @depends apiReadContactMessage
     * @dataProvider rolesProvider
     */
    public function apiReadContactMessageOtherUser($email, $role, $isAdmin): void
    {
        if (!$isAdmin) {
            $this->expectException(\Omeka\Api\Exception\PermissionDeniedException::class);
        }
        $this->loginUser($email);
        $message = $this->api()->read('contact_messages', 1)->getContent();
        $isAdmin
            ? $this->assertNotEmpty($message)
            : $this->assertEmpty($message);
    }

    /**
     * @test
     * @depends apiReadContactMessage
     * @expectedException \Omeka\Api\Exception\PermissionDeniedException
     */
    public function apiReadContactMessageForbiddenForAnonymous(): void
    {
        $this->logout();
        $this->api()->read('contact_messages', 1)->getContent();
    }

    /**
     * @test
     * @depends apiReadContactMessageOtherUser
     */
    public function apiSearchContactMessage(): void
    {
        // Results from the previous test are not deleted and dependant.
        $response = $this->api()->search('contact_messages', ['email' => 'user@example.org'], ['responseContent' => 'resource']);
        $this->assertEquals(1, $response->getTotalResults());

        $response = $this->api()->search('contact_messages', [], ['responseContent' => 'resource']);
        $this->assertEquals(9, $response->getTotalResults());

        /*
        $totalByOwner = [];
        foreach ($response->getContent() as $message) {
            $email = $message->getEmail();
            empty($totalByOwner[$email]) ? $totalByOwner[$email] = 1 : ++$totalByOwner[$email];
        }
        return $totalByOwner;
        */
    }

    /**
     * @test
     * @depends apiSearchContactMessage
     * @dataProvider rolesProvider
     */
    public function apiSearchContactMessageOwnAndAll($email, $role, $isAdmin): void
    {
        $this->loginUser($email);
        $response = $this->api()->search('contact_messages', ['email' => $email], ['responseContent' => 'resource']);
        $totalByEmail = $response->getTotalResults();
        $this->assertEquals(1, $totalByEmail);

        $response = $this->api()->search('contact_messages', [], ['responseContent' => 'resource']);
        $expected = $isAdmin ? 9 : 1;
        $total = $response->getTotalResults();
        $this->assertEquals($expected, $total);
    }

    /**
     * @test
     * @depends apiReadContactMessage
     * @expectedException \Omeka\Api\Exception\PermissionDeniedException
     */
    public function apiSearchContactMessageForbiddenForAnonymous(): void
    {
        $this->logout();
        $this->api()->search('contact_messages')->getContent();
    }
}
