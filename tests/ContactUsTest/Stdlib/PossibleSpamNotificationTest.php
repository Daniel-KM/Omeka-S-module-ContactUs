<?php declare(strict_types=1);

namespace ContactUsTest\Stdlib;

use Common\Mvc\Controller\Plugin\SendEmail;
use Common\Stdlib\EasyMeta;
use ContactUs\Api\Representation\MessageRepresentation;
use ContactUs\Stdlib\ContactSubmission;
use Laminas\Form\FormElementManager;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Manager as ApiManager;
use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Mailer;
use PHPUnit\Framework\TestCase;

/**
 * Tests the notification of a message that may be a false positive: only the
 * admins receive it, flagged and with its reasons.
 */
class PossibleSpamNotificationTest extends TestCase
{
    /**
     * @var array List of the mails sent: [body, subject, to, from, cc, bcc, replyTo].
     */
    protected $sent = [];

    protected function submission(): PossibleSpamProxy
    {
        $sendEmail = $this->createMock(SendEmail::class);
        $sendEmail->method('__invoke')->willReturnCallback(function (...$args) {
            $this->sent[] = $args;
            return true;
        });

        $mailer = $this->createMock(Mailer::class);
        $mailer->method('getInstallationTitle')->willReturn('Dante');

        $settings = ['administrator_email' => 'admin@example.org', 'contactus_author_only' => false];
        $view = $this->createMock(PhpRenderer::class);
        $view->method('plugin')->willReturnCallback(function ($name) use ($settings) {
            switch ($name) {
                case 'setting':
                    return fn ($key, $default = null) => $settings[$key] ?? $default;
                case 'siteSetting':
                    return fn ($key, $default = null) => $default;
                case 'prepareMessage':
                    return new class {
                        public function fillMessage($message, array $placeholders, array $context = []): string
                        {
                            $replace = [];
                            foreach ($placeholders as $key => $value) {
                                $replace['{' . $key . '}'] = is_scalar($value) ? (string) $value : '';
                            }
                            return strtr((string) $message, $replace);
                        }
                    };
                default:
                    return fn ($value) => $value;
            }
        });
        $view->method('__call')->willReturnCallback(fn ($name, $args) => $args[1] ?? null);

        return new PossibleSpamProxy(
            $this->createMock(Api::class),
            $this->createMock(ApiManager::class),
            $this->createMock(EasyMeta::class),
            $this->createMock(FormElementManager::class),
            $mailer,
            $this->createMock(Messenger::class),
            $sendEmail,
            ['notify_body' => 'Message from {email}: {message}'],
            null,
            $view
        );
    }

    protected function message(): MessageRepresentation
    {
        $site = $this->createMock(\Omeka\Api\Representation\SiteRepresentation::class);
        $site->method('title')->willReturn('Dante');
        $site->method('siteUrl')->willReturn('https://example.org/s/fr');
        $message = $this->createMock(MessageRepresentation::class);
        $message->method('email')->willReturn('visitor@example.org');
        $message->method('name')->willReturn('Visitor');
        $message->method('site')->willReturn($site);
        $message->method('subject')->willReturn('Question');
        $message->method('body')->willReturn('Hello, a question.');
        $message->method('ip')->willReturn('192.0.2.10');
        $message->method('zipUrl')->willReturn('');
        return $message;
    }

    protected function dispatch(bool $toAuthor, array $reasons): array
    {
        return $this->submission()->proxyDispatchMessages(
            $this->message(),
            [],
            ['subject' => '', 'notify_recipients' => [], 'sender_email' => '', 'sender_name' => '', 'author_email' => 'author@example.org', 'confirmation_enabled' => true],
            $toAuthor,
            false,
            '',
            false,
            $reasons
        );
    }

    public function testPossibleSpamIsSentOnlyToTheAdmins(): void
    {
        $result = $this->dispatch(false, ['tooFast', 'powChallenge']);
        $this->assertNull($result['status']);
        $this->assertCount(1, $this->sent, 'No confirmation must be sent to the visitor.');
        [$body, $subject, $to] = $this->sent[0];
        $this->assertSame(['admin@example.org' => ''], $to);
        $this->assertStringStartsWith('[Possible spam] ', $subject);
        $this->assertStringContainsString('Hello, a question.', $body);
        $this->assertStringContainsString('tooFast, powChallenge', $body);
    }

    /**
     * A message to the author of a resource is not relayed to him when it may
     * be a spam: only the admins receive it.
     */
    public function testPossibleSpamIsNeverSentToTheAuthor(): void
    {
        $this->dispatch(true, ['tooFast']);
        $this->assertCount(1, $this->sent);
        [, , $to, , , $bcc] = $this->sent[0];
        $this->assertSame(['admin@example.org' => ''], $to);
        $this->assertArrayNotHasKey('author@example.org', (array) $to);
        $this->assertArrayNotHasKey('author@example.org', (array) $bcc);
    }
}

class PossibleSpamProxy extends ContactSubmission
{
    public function proxyDispatchMessages(...$args): array
    {
        return $this->dispatchMessages(...$args);
    }

    /**
     * No view model in a unit test.
     */
    protected function currentSite(): ?\Omeka\Api\Representation\SiteRepresentation
    {
        return null;
    }
}
