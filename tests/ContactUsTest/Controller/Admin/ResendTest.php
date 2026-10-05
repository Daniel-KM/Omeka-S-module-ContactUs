<?php declare(strict_types=1);

namespace ContactUsTest\Controller\Admin;

use Common\Mvc\Controller\Plugin\SendEmail;
use ContactUsTest\ContactUsTestTrait;
use Laminas\Form\Element\Csrf;
use Laminas\Stdlib\Parameters;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests the resend of a message to the author by an admin, for example a false
 * positive, with a fake mailer, so no email is sent, and the batch job run
 * synchronously.
 *
 * @group integration
 */
class ResendTest extends AbstractHttpControllerTestCase
{
    use ContactUsTestTrait;

    /**
     * @var array Arguments of the emails sent.
     */
    public static $sent = [];

    protected $messageIds = [];

    protected $itemId;

    protected $authorId;

    protected $siteId;

    public function setUp(): void
    {
        parent::setUp();
        self::$sent = [];
        $this->loginAdmin();

        $services = $this->getServiceLocator();
        $services->get('Omeka\Settings')->set('contactus_author', 'owner');

        $em = $services->get('Omeka\EntityManager');
        $author = new \Omeka\Entity\User();
        $author->setEmail('author-' . uniqid() . '@example.org');
        $author->setName('Author');
        $author->setRole('editor');
        $author->setIsActive(true);
        $em->persist($author);
        $em->flush();
        $this->authorId = $author->getId();

        $this->siteId = $this->api()->create('sites', [
            'o:title' => 'Site of test',
            'o:slug' => 'site-resend-' . uniqid(),
            'o:theme' => 'default',
        ])->getContent()->id();

        $this->itemId = $this->api()->create('items', [])->getContent()->id();
        $item = $em->find(\Omeka\Entity\Item::class, $this->itemId);
        $item->setOwner($author);
        $em->flush();

        $services->setAllowOverride(true);
        $services->setService('Omeka\Job\DispatchStrategy', $services->get('Omeka\Job\DispatchStrategy\Synchronous'));
        $services->setAllowOverride(false);

        // Replace the mailer by a fake one, that records the emails.
        $plugins = $services->get('ControllerPluginManager');
        $plugins->setAllowOverride(true);
        $plugins->setService('sendEmail', new class($services->get('Omeka\Logger'), $services->get('Omeka\Mailer'), $services->get('Omeka\Settings')) extends SendEmail {
            public function __invoke(
                string $body,
                $subject = null,
                $to = null,
                $from = null,
                $cc = null,
                $bcc = null,
                $replyTo = null,
                bool $checkSpam = true
            ): bool {
                ResendTest::$sent[] = compact('body', 'subject', 'to', 'checkSpam');
                return true;
            }
        });
    }

    public function tearDown(): void
    {
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        foreach ($this->messageIds as $id) {
            if ($m = $em->find(\ContactUs\Entity\Message::class, $id)) {
                $em->remove($m);
            }
        }
        $em->flush();
        try {
            $this->api()->delete('items', $this->itemId);
            $this->api()->delete('users', $this->authorId);
            $this->api()->delete('sites', $this->siteId);
        } catch (\Exception $e) {
        }
        $this->logout();
        parent::tearDown();
    }

    protected function createMessage(bool $toAuthor, bool $isSpam, string $body = 'Hello, a question about the item.'): int
    {
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $message = new \ContactUs\Entity\Message();
        $message
            ->setEmail('visitor@example.org')
            ->setName('Visitor')
            ->setBody($body)
            ->setIp('192.0.2.10')
            ->setToAuthor($toAuthor)
            ->setIsSpam($isSpam)
            ->setSpamReason($isSpam ? 'tooFast' : null)
            ->setResource($em->find(\Omeka\Entity\Item::class, $this->itemId))
            ->setSite($em->find(\Omeka\Entity\Site::class, $this->siteId))
            ->setCreated(new \DateTime());
        $em->persist($message);
        $em->flush();
        $this->messageIds[] = $message->getId();
        return $message->getId();
    }

    protected function post(string $url, array $post = []): array
    {
        $this->getRequest()
            ->setMethod('POST')
            ->setPost(new Parameters($post))
            ->getHeaders()->addHeaderLine('X-Requested-With', 'XMLHttpRequest');
        $this->dispatch($url);
        $content = (string) $this->getResponse()->getContent();
        $exception = $this->getApplication()->getMvcEvent()->getParam('exception');
        if ($exception) {
            return ['debug' => get_class($exception) . ': ' . $exception->getMessage() . ' @ ' . $exception->getFile() . ':' . $exception->getLine()];
        }
        return json_decode($content, true)
            ?: ['debug' => $this->getResponse()->getStatusCode() . ' ' . mb_substr(strip_tags($content), 0, 300)];
    }

    protected function message(int $id): \ContactUs\Api\Representation\MessageRepresentation
    {
        $this->getServiceLocator()->get('Omeka\EntityManager')->clear();
        return $this->api()->read('contact_messages', $id)->getContent();
    }

    public function testFalsePositiveIsResentToTheAuthorWithoutSpamCheck(): void
    {
        // A keyword of spam in a legitimate message must not block the resend.
        $id = $this->createMessage(true, true, 'I am a specialist of cialis research in the archives.');
        $result = $this->post('/admin/contact-message/' . $id . '/resend');

        $this->assertSame([$id], $result['data']['sent'] ?? null, json_encode($result));
        $this->assertCount(1, self::$sent);
        $author = $this->getServiceLocator()->get('Omeka\EntityManager')->find(\Omeka\Entity\User::class, $this->authorId);
        $this->assertSame([$author->getEmail() => ''], self::$sent[0]['to']);
        $this->assertFalse(self::$sent[0]['checkSpam']);
        $this->assertStringContainsString('cialis research', self::$sent[0]['body']);

        $message = $this->message($id);
        $this->assertFalse($message->isSpam());
        $this->assertSame('admin', $message->spamReason());
        $this->assertNotNull($message->resent());
    }

    public function testMessageAlreadyResentIsSkipped(): void
    {
        $id = $this->createMessage(true, true);
        $this->post('/admin/contact-message/' . $id . '/resend');
        $this->reset();
        self::$sent = [];
        $this->loginAdmin();
        $result = $this->post('/admin/contact-message/' . $id . '/resend');
        $this->assertSame([$id], $result['data']['skipped'] ?? null, json_encode($result));
        $this->assertSame([], self::$sent);
    }

    public function testMessageToUsIsNotResent(): void
    {
        $id = $this->createMessage(false, true);
        $result = $this->post('/admin/contact-message/' . $id . '/resend');
        $this->assertSame([$id], $result['data']['skipped'] ?? null, json_encode($result));
        $this->assertSame([], self::$sent);
        $this->assertTrue($this->message($id)->isSpam());
    }

    protected function postConfirm(string $url, array $post = []): void
    {
        $post['confirmform_csrf'] = (new Csrf('confirmform_csrf'))->getValue();
        $this->dispatch($url, 'POST', $post);
        $this->assertResponseStatusCode(302);
    }

    public function testBatchResendsOnlyTheSelectedMessagesToTheAuthor(): void
    {
        $toAuthor = $this->createMessage(true, true);
        $toUs = $this->createMessage(false, true);
        $legitimate = $this->createMessage(true, false);
        $notSelected = $this->createMessage(true, true);
        $this->postConfirm('/admin/contact-message/batch-resend', [
            'resource_ids' => [$toAuthor, $toUs, $legitimate],
        ]);

        $this->assertCount(1, self::$sent);
        $this->assertNotNull($this->message($toAuthor)->resent());
        $this->assertNull($this->message($legitimate)->resent());
        $this->assertNull($this->message($toUs)->resent());
        $this->assertTrue($this->message($toUs)->isSpam());
        $this->assertNull($this->message($notSelected)->resent());
    }

    public function testBatchResendAllUsesTheQuery(): void
    {
        $falsePositive = $this->createMessage(true, true);
        $legitimate = $this->createMessage(true, false);
        $toUs = $this->createMessage(false, true);
        $this->postConfirm('/admin/contact-message/batch-resend-all', [
            'query' => json_encode(['is_spam' => '1', 'resource_id' => $this->itemId]),
        ]);

        $this->assertCount(1, self::$sent);
        $this->assertNotNull($this->message($falsePositive)->resent());
        $this->assertFalse($this->message($falsePositive)->isSpam());
        $this->assertNull($this->message($legitimate)->resent());
        $this->assertNull($this->message($toUs)->resent());
    }

    public function testBatchResendAllKeepsTheFilterOnMessagesToUs(): void
    {
        $toAuthor = $this->createMessage(true, true);
        $this->createMessage(false, true);
        $this->postConfirm('/admin/contact-message/batch-resend-all', [
            'query' => json_encode(['is_spam' => '1', 'to_author' => '0', 'resource_id' => $this->itemId]),
        ]);
        $this->assertSame([], self::$sent);
        $this->assertNull($this->message($toAuthor)->resent());
    }

    public function testMessageAlreadySentIsNotResent(): void
    {
        $id = $this->createMessage(true, false);
        $result = $this->post('/admin/contact-message/' . $id . '/resend');
        $this->assertSame([$id], $result['data']['skipped'] ?? null, json_encode($result));
        $this->assertSame([], self::$sent);
    }

    public function testFalsePositiveSetAsNotSpamByAnAdminIsResent(): void
    {
        $id = $this->createMessage(true, true);
        $this->api()->update('contact_messages', $id, ['o-module-contact:is_spam' => false], [], ['isPartial' => true]);
        $this->assertSame('admin', $this->message($id)->spamReason());
        // The entity manager was cleared, so the identity is reloaded.
        $this->loginAdmin();

        $this->postConfirm('/admin/contact-message/batch-resend-all', [
            'query' => json_encode(['is_spam' => '0', 'resource_id' => $this->itemId]),
        ]);
        $this->assertCount(1, self::$sent);
        $this->assertNotNull($this->message($id)->resent());
    }

    public function testBatchResendRequiresTheConfirmation(): void
    {
        $id = $this->createMessage(true, true);
        $this->dispatch('/admin/contact-message/batch-resend', 'POST', ['resource_ids' => [$id]]);
        $this->assertResponseStatusCode(302);
        $this->assertSame([], self::$sent);
        $this->assertNull($this->message($id)->resent());
    }

    public function testGetRequestIsRejected(): void
    {
        $id = $this->createMessage(true, true);
        $this->getRequest()->getHeaders()->addHeaderLine('X-Requested-With', 'XMLHttpRequest');
        $this->dispatch('/admin/contact-message/' . $id . '/resend');
        $this->assertResponseStatusCode(404);
        $this->assertSame([], self::$sent);
    }
}
