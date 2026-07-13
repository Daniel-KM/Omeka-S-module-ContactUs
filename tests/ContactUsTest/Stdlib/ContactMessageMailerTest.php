<?php declare(strict_types=1);

namespace ContactUsTest\Stdlib;

use Common\Mvc\Controller\Plugin\SendEmail;
use ContactUs\Stdlib\ContactMessageMailer;
use PHPUnit\Framework\TestCase;

class ContactMessageMailerTest extends TestCase
{
    private $captured;

    /**
     * @return array [body, subject, to, from, cc, bcc, replyTo]
     */
    private function mailerCapturing(): ContactMessageMailer
    {
        $this->captured = null;
        $sendEmail = $this->createMock(SendEmail::class);
        $sendEmail->method('__invoke')->willReturnCallback(function (...$args) {
            $this->captured = $args;
            return true;
        });
        return new ContactMessageMailer($sendEmail);
    }

    public function testToAuthorFromSenderReplyToVisitor(): void
    {
        $ok = $this->mailerCapturing()->toAuthor(
            'Subject',
            'Body',
            'visitor@example.org',
            'Visitor',
            ['author@example.org' => ''],
            ['sender@example.org' => 'Sender'],
            ['admin@example.org' => ''],
            false
        );
        $this->assertTrue($ok);
        [, , $to, $from, , $bcc, $replyTo] = $this->captured;
        $this->assertSame(['author@example.org' => ''], $to);
        $this->assertSame(['sender@example.org' => 'Sender'], $from);
        $this->assertSame(['admin@example.org' => ''], $bcc);
        $this->assertSame(['visitor@example.org' => 'Visitor'], $replyTo);
    }

    public function testToAuthorWithUserEmailFromVisitorNoReplyTo(): void
    {
        $this->mailerCapturing()->toAuthor(
            'Subject',
            'Body',
            'visitor@example.org',
            'Visitor',
            ['author@example.org' => ''],
            ['sender@example.org' => 'Sender'],
            null,
            true
        );
        [, , , $from, , , $replyTo] = $this->captured;
        $this->assertSame(['visitor@example.org' => 'Visitor'], $from);
        $this->assertNull($replyTo);
    }

    public function testNotifyAdminsFromSenderReplyToVisitor(): void
    {
        $this->mailerCapturing()->notifyAdmins(
            'Subject',
            'Body',
            'visitor@example.org',
            'Visitor',
            ['admin@example.org' => ''],
            ['sender@example.org' => 'Sender']
        );
        [, , $to, $from, , $bcc, $replyTo] = $this->captured;
        $this->assertSame(['admin@example.org' => ''], $to);
        $this->assertSame(['sender@example.org' => 'Sender'], $from);
        $this->assertNull($bcc);
        $this->assertSame(['visitor@example.org' => 'Visitor'], $replyTo);
    }

    public function testConfirmToVisitorToVisitorFromSender(): void
    {
        $this->mailerCapturing()->confirmToVisitor(
            'Subject',
            'Body',
            'visitor@example.org',
            'Visitor',
            ['sender@example.org' => 'Sender'],
            ['support@example.org' => '']
        );
        [, , $to, $from, , , $replyTo] = $this->captured;
        $this->assertSame(['visitor@example.org' => 'Visitor'], $to);
        $this->assertSame(['sender@example.org' => 'Sender'], $from);
        $this->assertSame(['support@example.org' => ''], $replyTo);
    }
}
