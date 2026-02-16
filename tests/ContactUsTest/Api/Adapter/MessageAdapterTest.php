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
     */
    public function apiCreateContactMessageNoUserNoEmail(): void
    {
        $this->expectException(\Omeka\Api\Exception\ValidationException::class);
        $this->logout();
        $this->api()->create('contact_messages', [
            'o-module-contact:subject' => 'subject',
            'o-module-contact:body' => 'body',
        ]);
    }

    /**
     * Roles that can read their own messages (admin roles + user roles with
     * OwnsEntityAssertion).
     *
     * Note: editor/reviewer are in adminRoles in Module ACL, so they don't have
     * the "userRoles" OwnsEntity permission. Guest role requires Guest module.
     */
    public function readableRolesProvider()
    {
        return [
            'global_admin' => ['global_admin@example.org', 'global_admin', true],
            'site_admin' => ['site_admin@example.org', 'site_admin', true],
            'author' => ['author@example.org', 'author', false],
            'researcher' => ['researcher@example.org', 'researcher', false],
        ];
    }

    /**
     * Admin roles that have full access to all messages.
     */
    public function adminRolesProvider()
    {
        return [
            'global_admin' => ['global_admin@example.org', 'global_admin'],
            'site_admin' => ['site_admin@example.org', 'site_admin'],
        ];
    }

    /**
     * @test
     * @depends apiCreateContactMessage
     * @dataProvider readableRolesProvider
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
     * @dataProvider readableRolesProvider
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
     */
    public function apiReadContactMessageForbiddenForAnonymous(): void
    {
        $this->expectException(\Omeka\Api\Exception\PermissionDeniedException::class);
        $this->logout();
        $this->api()->read('contact_messages', 1)->getContent();
    }

    /**
     * @test
     * @depends apiReadContactMessageOtherUser
     */
    public function apiSearchContactMessage(): void
    {
        $response = $this->api()->search('contact_messages', ['email' => 'user@example.org'], ['responseContent' => 'resource']);
        $this->assertEquals(1, $response->getTotalResults());

        $response = $this->api()->search('contact_messages', [], ['responseContent' => 'resource']);
        // 1 (apiCreate) + 4 readableRoles × 1 (apiReadContactMessage) = 5
        $this->assertGreaterThanOrEqual(5, $response->getTotalResults());
    }

    /**
     * @test
     * @depends apiSearchContactMessage
     * @dataProvider readableRolesProvider
     */
    public function apiSearchContactMessageOwnAndAll($email, $role, $isAdmin): void
    {
        $this->loginUser($email);
        $response = $this->api()->search('contact_messages', ['email' => $email], ['responseContent' => 'resource']);
        $totalByEmail = $response->getTotalResults();
        $this->assertEquals(1, $totalByEmail);

        $response = $this->api()->search('contact_messages', [], ['responseContent' => 'resource']);
        $total = $response->getTotalResults();
        if ($isAdmin) {
            $this->assertGreaterThanOrEqual(5, $total);
        } else {
            $this->assertEquals(1, $total);
        }
    }

    /**
     * @test
     * @depends apiReadContactMessage
     */
    public function apiSearchContactMessageForbiddenForAnonymous(): void
    {
        $this->expectException(\Omeka\Api\Exception\PermissionDeniedException::class);
        $this->logout();
        $this->api()->search('contact_messages')->getContent();
    }

    /** @test */
    public function apiSearchSortByCreatedAsc(): void
    {
        // Create two messages with distinct timestamps.
        $message1 = $this->createContactMessage(null, 'first@example.org');
        sleep(1);
        $message2 = $this->createContactMessage(null, 'second@example.org');

        $response = $this->api()->search('contact_messages', [
            'sort_by' => 'created',
            'sort_order' => 'asc',
        ]);
        $results = $response->getContent();
        $this->assertGreaterThanOrEqual(2, count($results));
        // The first created should come before the second in ascending order.
        $ids = array_map(fn($r) => $r->id(), $results);
        $pos1 = array_search($message1->getId(), $ids);
        $pos2 = array_search($message2->getId(), $ids);
        $this->assertLessThan($pos2, $pos1);
    }

    /** @test */
    public function apiSearchSortByCreatedDesc(): void
    {
        $message1 = $this->createContactMessage(null, 'old@example.org');
        sleep(1);
        $message2 = $this->createContactMessage(null, 'new@example.org');

        $response = $this->api()->search('contact_messages', [
            'sort_by' => 'created',
            'sort_order' => 'desc',
        ]);
        $results = $response->getContent();
        $this->assertGreaterThanOrEqual(2, count($results));
        $ids = array_map(fn($r) => $r->id(), $results);
        $pos1 = array_search($message1->getId(), $ids);
        $pos2 = array_search($message2->getId(), $ids);
        // The most recent should come first in descending order.
        $this->assertGreaterThan($pos2, $pos1);
    }

    /** @test */
    public function apiSearchSortByEmail(): void
    {
        $messageA = $this->createContactMessage(null, 'aaa@example.org');
        $messageZ = $this->createContactMessage(null, 'zzz@example.org');

        $response = $this->api()->search('contact_messages', [
            'sort_by' => 'email',
            'sort_order' => 'asc',
        ]);
        $results = $response->getContent();
        $ids = array_map(fn($r) => $r->id(), $results);
        $posA = array_search($messageA->getId(), $ids);
        $posZ = array_search($messageZ->getId(), $ids);
        $this->assertLessThan($posZ, $posA);
    }

    /** @test */
    public function apiSearchSortByName(): void
    {
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');

        $messageA = $this->createContactMessage(null, 'name-a@example.org');
        $messageA->setName('Alice');
        $em->flush();

        $messageZ = $this->createContactMessage(null, 'name-z@example.org');
        $messageZ->setName('Zara');
        $em->flush();

        $response = $this->api()->search('contact_messages', [
            'sort_by' => 'name',
            'sort_order' => 'asc',
        ]);
        $results = $response->getContent();
        $ids = array_map(fn($r) => $r->id(), $results);
        $posA = array_search($messageA->getId(), $ids);
        $posZ = array_search($messageZ->getId(), $ids);
        $this->assertLessThan($posZ, $posA);
    }
}
