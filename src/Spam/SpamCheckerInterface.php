<?php declare(strict_types=1);

namespace ContactUs\Spam;

/**
 * Common abstraction for the spam checks applied to an anonymous submission.
 *
 * Two implementations exist: a local fallback that mirrors the shared
 * strategies, and an adapter delegating to the SpamGuard module when present.
 */
interface SpamCheckerInterface
{
    /**
     * Return the list of matched spam reasons for the given submission context.
     *
     * The context contains the posted values and the session snapshot taken
     * when the form was rendered: ip, userAgent, email, subject, body,
     * formLoadedAt, honeypot, powSalt, powNonce, powIssuedAt, prevSubmitAt,
     * prevSubmitIp, checkDnsMx (bool), powSkip (bool).
     *
     * @return string[] Reason keys, e.g. ['honeypot', 'tooFast'].
     */
    public function check(array $context): array;
}
