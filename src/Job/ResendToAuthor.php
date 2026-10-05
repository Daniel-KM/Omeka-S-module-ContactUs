<?php declare(strict_types=1);

namespace ContactUs\Job;

use ContactUs\Stdlib\MessageResender;
use Omeka\Job\AbstractJob;

/**
 * Resend messages to the author of their resource, without any check of spam.
 *
 * The messages are the ids of the argument "ids", else the results of the api
 * query of the argument "query".
 */
class ResendToAuthor extends AbstractJob
{
    const CHUNK = 100;

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');

        $ids = $this->getArg('ids');
        if ($ids === null) {
            $query = (array) $this->getArg('query', []);
            unset($query['page'], $query['per_page'], $query['offset'], $query['limit']);
            $query['resendable'] = '1';
            $ids = $services->get('Omeka\ApiManager')
                ->search('contact_messages', $query, ['returnScalar' => 'id'])
                ->getContent();
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
        if (!$ids) {
            $logger->notice('No message to resend to the author.'); // @translate
            return;
        }

        $resender = new MessageResender($services);
        $sent = $skipped = $errors = 0;
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            if ($this->shouldStop()) {
                $logger->warn('The job was stopped.'); // @translate
                break;
            }
            $result = $resender($chunk);
            $sent += count($result['sent']);
            $skipped += count($result['skipped']);
            $errors += count($result['errors']);
            foreach ($result['errors'] as $error) {
                $logger->err($error->getMessage(), $error->getContext());
            }
        }

        $logger->notice(
            '{count_sent} message(s) resent to the author, {count_skipped} skipped (already sent, already resent or to us), {count_errors} error(s).', // @translate
            ['count_sent' => $sent, 'count_skipped' => $skipped, 'count_errors' => $errors]
        );
    }
}
