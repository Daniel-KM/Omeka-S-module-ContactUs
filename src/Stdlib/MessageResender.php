<?php declare(strict_types=1);

namespace ContactUs\Stdlib;

use Common\Stdlib\PsrMessage;
use ContactUs\Service\ViewHelper\ContactUsFactory;
use Psr\Container\ContainerInterface;

/**
 * Resend messages to the author of their resource, on the decision of an admin.
 *
 * Only a message blocked at submission is resent, so a message already sent,
 * already resent or to us is skipped. A message resent is not a spam.
 */
class MessageResender
{
    /**
     * @var \Psr\Container\ContainerInterface
     */
    protected $services;

    /**
     * @var \ContactUs\Stdlib\ContactSubmission[]
     */
    protected $submissions = [];

    public function __construct(ContainerInterface $services)
    {
        $this->services = $services;
    }

    /**
     * @return array Ids of messages "sent" and "skipped", and "errors" as
     * PsrMessage.
     */
    public function __invoke(array $ids): array
    {
        /** @var \Omeka\Api\Manager $api */
        $api = $this->services->get('Omeka\ApiManager');
        $defaultSiteId = (int) $this->services->get('Omeka\Settings')->get('default_site');

        $result = [
            'sent' => [],
            'skipped' => [],
            'errors' => [],
        ];
        foreach ($ids as $id) {
            $id = (int) $id;
            try {
                /** @var \ContactUs\Api\Representation\MessageRepresentation $message */
                $message = $api->read('contact_messages', $id)->getContent();
            } catch (\Exception $e) {
                $result['errors'][] = new PsrMessage('The message #{message_id} does not exist.', ['message_id' => $id]); // @translate
                continue;
            }
            if (!$message->userIsAllowed('update')
                || !$message->isToAuthor()
                || !$message->isBlocked()
                || $message->resent()
            ) {
                $result['skipped'][] = $id;
                continue;
            }

            // The mail uses the settings of the site of the message, else the
            // ones of the default site.
            $site = $message->site();
            $siteId = $site ? (int) $site->id() : $defaultSiteId;
            if (!$siteId) {
                $result['errors'][] = new PsrMessage('The message #{message_id} has no site and there is no default site.', ['message_id' => $id]); // @translate
                continue;
            }

            $error = $this->submission($siteId)->resendToAuthor($message);
            if ($error) {
                $result['errors'][] = $error;
                continue;
            }

            $api->update('contact_messages', $id, [
                'o-module-contact:is_spam' => false,
                'o-module-contact:resent' => true,
            ], [], ['isPartial' => true]);
            $result['sent'][] = $id;
        }

        return $result;
    }

    protected function submission(int $siteId): ContactSubmission
    {
        if (!isset($this->submissions[$siteId])) {
            $plugins = $this->services->get('ControllerPluginManager');
            $this->submissions[$siteId] = new ContactSubmission(
                $plugins->get('api'),
                $this->services->get('Omeka\ApiManager'),
                $this->services->get('Common\EasyMeta'),
                $this->services->get('FormElementManager'),
                $this->services->get('Omeka\Mailer'),
                $plugins->get('messenger'),
                $plugins->get('sendEmail'),
                ContactUsFactory::siteOptions($this->services, $siteId),
                $this->services,
                $this->services->get('ViewRenderer')
            );
        }
        return $this->submissions[$siteId];
    }
}
