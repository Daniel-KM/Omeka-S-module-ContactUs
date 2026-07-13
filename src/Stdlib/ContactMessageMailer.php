<?php declare(strict_types=1);

namespace ContactUs\Stdlib;

use Common\Mvc\Controller\Plugin\SendEmail;

/**
 * Send the three contact mails (to the author, to the administrators and the
 * confirmation to the visitor), each with its own sender/recipient/reply-to
 * envelope. The actual delivery is done by the Common SendEmail plugin.
 *
 * Subjects and bodies are expected already filled with placeholders by the
 * caller.
 */
class ContactMessageMailer
{
    /**
     * @var \Common\Mvc\Controller\Plugin\SendEmail
     */
    protected $sendEmail;

    public function __construct(SendEmail $sendEmail)
    {
        $this->sendEmail = $sendEmail;
    }

    /**
     * Send the message to the author.
     *
     * From the visitor (when sending with the user email) or the configured
     * sender; reply-to the visitor; optional bcc to the administrators.
     */
    public function toAuthor(
        string $subject,
        string $body,
        string $visitorEmail,
        string $visitorName,
        ?array $to,
        ?array $sender,
        ?array $bcc,
        bool $sendWithUserEmail
    ): bool {
        $from = $sendWithUserEmail
            ? [$visitorEmail => $visitorName]
            : $sender;
        $replyTo = $sendWithUserEmail
            ? null
            : [$visitorEmail => $visitorName];
        return (bool) $this->sendEmail->__invoke($body, $subject, $to, $from, null, $bcc, $replyTo);
    }

    /**
     * Notify the administrators.
     *
     * Always from the configured sender (to avoid delivery issues), with a
     * reply-to set to the visitor.
     */
    public function notifyAdmins(
        string $subject,
        string $body,
        string $visitorEmail,
        string $visitorName,
        ?array $to,
        ?array $sender
    ): bool {
        $replyTo = [$visitorEmail => $visitorName];
        return (bool) $this->sendEmail->__invoke($body, $subject, $to, $sender, null, null, $replyTo);
    }

    /**
     * Send the confirmation to the visitor.
     *
     * Always from the configured sender, with a reply-to on a monitored mailbox
     * (never a no-reply).
     */
    public function confirmToVisitor(
        string $subject,
        string $body,
        string $visitorEmail,
        string $visitorName,
        ?array $sender,
        ?array $replyTo
    ): bool {
        $to = [$visitorEmail => $visitorName];
        return (bool) $this->sendEmail->__invoke($body, $subject, $to, $sender, null, null, $replyTo);
    }
}
