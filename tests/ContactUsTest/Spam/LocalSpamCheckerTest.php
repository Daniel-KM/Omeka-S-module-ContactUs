<?php declare(strict_types=1);

namespace ContactUsTest\Spam;

use ContactUs\Spam\LocalSpamChecker;
use PHPUnit\Framework\TestCase;

class LocalSpamCheckerTest extends TestCase
{
    private function checker(): LocalSpamChecker
    {
        return new LocalSpamChecker();
    }

    /**
     * A legitimate submission (aged form, no honeypot, pow skipped) has no
     * reason.
     */
    public function testCleanSubmissionHasNoReason(): void
    {
        $reasons = $this->checker()->check([
            'ip' => '1.2.3.4',
            'formLoadedAt' => time() - 10,
            'honeypot' => '',
            'powSkip' => true,
            'subject' => 'Hello',
            'body' => 'A normal message.',
            'prevSubmitAt' => 0,
        ]);
        $this->assertSame([], $reasons);
    }

    public function testHoneypotFilledIsSpam(): void
    {
        $reasons = $this->checker()->check([
            'formLoadedAt' => time() - 10,
            'honeypot' => 'http://bot.example',
            'powSkip' => true,
            'subject' => 'Hi',
            'body' => 'Message.',
            'prevSubmitAt' => 0,
        ]);
        $this->assertContains('honeypot', $reasons);
    }

    public function testTooFastSubmissionIsSpam(): void
    {
        $reasons = $this->checker()->check([
            'formLoadedAt' => time(),
            'honeypot' => '',
            'powSkip' => true,
            'subject' => 'Hi',
            'body' => 'Message.',
            'prevSubmitAt' => 0,
        ]);
        $this->assertContains('tooFast', $reasons);
    }

    public function testMissingFormLoadedAtIsNotTooFast(): void
    {
        // Aligned with the SpamGuard strategy: a missing load time is not
        // flagged as too fast here (a session-less submission is caught by the
        // proof-of-work instead).
        $reasons = $this->checker()->check([
            'formLoadedAt' => 0,
            'honeypot' => '',
            'powSkip' => true,
            'subject' => 'Hi',
            'body' => 'Message.',
            'prevSubmitAt' => 0,
        ]);
        $this->assertNotContains('tooFast', $reasons);
    }

    public function testTooFastRespectsConfiguredMinDelay(): void
    {
        $base = [
            'formLoadedAt' => time() - 2,
            'honeypot' => '',
            'powSkip' => true,
            'subject' => 'Hi',
            'body' => 'Message.',
            'prevSubmitAt' => 0,
        ];
        // Elapsed 2s: flagged when the required delay is larger, not otherwise.
        $this->assertContains('tooFast', $this->checker()->check(['minDelay' => 5] + $base));
        $this->assertNotContains('tooFast', $this->checker()->check(['minDelay' => 1] + $base));
    }

    public function testRateLimitedWhenSameIpTooSoon(): void
    {
        $reasons = $this->checker()->check([
            'ip' => '10.0.0.1',
            'formLoadedAt' => time() - 10,
            'honeypot' => '',
            'powSkip' => true,
            'subject' => 'Hi',
            'body' => 'Message.',
            'prevSubmitAt' => time() - 2,
            'prevSubmitIp' => '10.0.0.1',
        ]);
        $this->assertContains('rateLimit', $reasons);
    }

    public function testNotRateLimitedFromAnotherIp(): void
    {
        $reasons = $this->checker()->check([
            'ip' => '10.0.0.2',
            'formLoadedAt' => time() - 10,
            'honeypot' => '',
            'powSkip' => true,
            'subject' => 'Hi',
            'body' => 'Message.',
            'prevSubmitAt' => time() - 2,
            'prevSubmitIp' => '10.0.0.1',
        ]);
        $this->assertNotContains('rateLimit', $reasons);
    }

    public function testMissingProofOfWorkIsSpam(): void
    {
        $reasons = $this->checker()->check([
            'formLoadedAt' => time() - 10,
            'honeypot' => '',
            'powSkip' => false,
            'powSalt' => '',
            'powNonce' => '',
            'subject' => 'Hi',
            'body' => 'Message.',
            'prevSubmitAt' => 0,
        ]);
        $this->assertContains('powChallenge', $reasons);
    }

    public function testValidProofOfWorkPasses(): void
    {
        $salt = 'abcdef';
        // Find a nonce so sha256(salt:nonce) starts with 4 hex zeros.
        $nonce = 0;
        while (strncmp(hash('sha256', $salt . ':' . $nonce), '0000', 4) !== 0) {
            ++$nonce;
        }
        $reasons = $this->checker()->check([
            'formLoadedAt' => time() - 10,
            'honeypot' => '',
            'powSkip' => false,
            'powSalt' => $salt,
            'powNonce' => (string) $nonce,
            'powIssuedAt' => time() - 5,
            'subject' => 'Hi',
            'body' => 'Message.',
            'prevSubmitAt' => 0,
        ]);
        $this->assertNotContains('powChallenge', $reasons);
    }
}
