<?php declare(strict_types=1);

namespace ContactUs\Spam;

use Common\Mvc\Controller\Plugin\SendEmail;

/**
 * Local spam checks, used when the SpamGuard module is not available.
 *
 * Mirrors the shared SpamGuard strategies (honeypot, tooFast, rateLimit, dnsMx,
 * powChallenge, keyword) so submissions stay protected on a plain install.
 */
class LocalSpamChecker implements SpamCheckerInterface
{
    /**
     * @var array|null
     */
    protected static $spamKeywords;

    public function check(array $context): array
    {
        $reasons = [];

        $ip = (string) ($context['ip'] ?? '');
        $loadedAt = (int) ($context['formLoadedAt'] ?? 0);

        // Honeypot: a hidden field only bots fill.
        if (!empty($context['honeypot'])) {
            $reasons[] = 'honeypot';
        }

        // Too fast: bots usually submit in under a second.
        $delta = $loadedAt ? time() - $loadedAt : null;
        if ($delta === null || $delta < 3) {
            $reasons[] = 'tooFast';
        }

        // Rate limit per session, bound to the current client IP.
        $prevSubmitAt = (int) ($context['prevSubmitAt'] ?? 0);
        $prevSubmitIp = (string) ($context['prevSubmitIp'] ?? '');
        if ($prevSubmitAt && $prevSubmitIp === $ip && (time() - $prevSubmitAt) < 10) {
            $reasons[] = 'rateLimit';
        }

        // DNS MX check for the sender email domain.
        if (!empty($context['checkDnsMx'])) {
            $email = (string) ($context['email'] ?? '');
            if ($email !== '' && strpos($email, '@') !== false) {
                [, $domain] = explode('@', $email, 2);
                $domain = trim($domain);
                if ($domain !== '' && !checkdnsrr($domain, 'MX')) {
                    $reasons[] = 'dnsMx';
                }
            }
        }

        // Proof-of-work: the browser must have solved the sha256 hashcash
        // challenge issued when the form was rendered.
        if (empty($context['powSkip'])) {
            $powSalt = (string) ($context['powSalt'] ?? '');
            $nonce = (string) ($context['powNonce'] ?? '');
            $powIssuedAt = (int) ($context['powIssuedAt'] ?? 0);
            if ($powSalt === ''
                || $nonce === ''
                || !ctype_digit($nonce)
                || (time() - $powIssuedAt) > 3600
                || strncmp(hash('sha256', $powSalt . ':' . $nonce), '0000', 4) !== 0
            ) {
                $reasons[] = 'powChallenge';
            }
        }

        // Spam keyword match on the posted subject and body.
        $candidate = trim((string) ($context['subject'] ?? '') . "\n" . (string) ($context['body'] ?? ''));
        if ($candidate !== '' && $this->matchSpamKeyword($candidate) !== null) {
            $reasons[] = 'keyword';
        }

        return $reasons;
    }

    /**
     * Return the first spam keyword matched in the body, or null.
     *
     * Replicates Common\SendEmail::matchSpamKeyword (which is protected),
     * reading the shared read-only keyword list from the Common module, located
     * via reflection so the path holds wherever Common is installed.
     */
    protected function matchSpamKeyword(string $body): ?string
    {
        if (self::$spamKeywords === null) {
            $file = dirname((new \ReflectionClass(SendEmail::class))->getFileName(), 5)
                . '/data/mailer/spam_keywords.php';
            self::$spamKeywords = is_file($file) ? include $file : [];
        }
        foreach (self::$spamKeywords as $spamKeyword) {
            // Word boundaries avoid false positives on substrings, for example
            // "cialis" in "specialiste".
            if (preg_match('/\b' . preg_quote($spamKeyword, '/') . '\b/ui', $body)) {
                return $spamKeyword;
            }
        }
        return null;
    }
}
