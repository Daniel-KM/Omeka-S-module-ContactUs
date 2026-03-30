<?php

/**
 * Resend emails blocked as false positive spam by Common's SendEmail.
 *
 * The Log module stores the message template and context separately.
 * The context JSON contains: keyword, to (JSON-encoded), from
 * (JSON-encoded), body.
 *
 * Usage:
 *   php resend-blocked-emails.php          # dry-run (list only)
 *   php resend-blocked-emails.php --send   # actually send
 *
 * Run from the OmekaS root on the target server.
 */

namespace Omeka;

require '../../bootstrap.php';
$app = Mvc\Application::init(require 'application/config/application.config.php');
$services = $app->getServiceManager();
$mailer = $services->get('Omeka\Mailer');

$ini = parse_ini_file('config/database.ini');
$dsn = 'mysql:host=' . $ini['host']
    . ';dbname=' . $ini['dbname']
    . ';charset=utf8mb4';
$pdo = new \PDO($dsn, $ini['user'], $ini['password']);
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

$dryRun = !in_array('--send', $argv ?? []);

// The message template is stored in English (translated at display).
// Exclude real spam (tinyurl, dollar, viagra, levitra in context).
$sql = <<<'SQL'
SELECT id, created, message, context
FROM log
WHERE message LIKE 'Email not sent: this is a spam%'
  AND context NOT LIKE '%tinyurl%'
  AND context NOT LIKE '%dollar%'
  AND context NOT LIKE '%viagra%'
  AND context NOT LIKE '%levitra%'
ORDER BY created
SQL;

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

if (!$rows) {
    echo "No blocked emails found.\n";
    exit(0);
}

echo count($rows) . " emails blocked found.\n";
if ($dryRun) {
    echo "Mode dry-run. Append --send to send.\n\n";
}

$sent = 0;
$errors = 0;

foreach ($rows as $i => $row) {
    $ctx = json_decode($row['context'], true);

    // Fallback: parse the interpolated message if context is empty.
    if (empty($ctx) || empty($ctx['body'])) {
        // Try to parse: ...({keyword}" (To: {to}; From: {from}): {body}
        if (preg_match(
            '/\(To:\s*(.+?);\s*From:\s*(.+?)\):\s*(.*)/s',
            $row['message'],
            $m
        )) {
            $toDecoded = json_decode($m[1], true);
            $ctx = [
                'to' => $m[1],
                'from' => $m[2],
                'body' => $m[3],
            ];
        } else {
            echo "#{$row['id']} : unable to parse, skipped.\n";
            ++$errors;
            continue;
        }
    }

    $body = trim($ctx['body']);
    if (!$body) {
        echo "#{$row['id']} : empty body, skipped.\n";
        ++$errors;
        continue;
    }

    // The "to" value is JSON-encoded in the context.
    $toRaw = $ctx['to'] ?? null;
    if (is_string($toRaw)) {
        $to = json_decode($toRaw, true) ?: $toRaw;
    } else {
        $to = $toRaw;
    }

    if (!$to) {
        echo "#{$row['id']} : no recipient, skipped.\n";
        ++$errors;
        continue;
    }

    // Extract email address.
    if (is_array($to)) {
        $toEmail = key($to);
        $toName = reset($to) ?: '';
        if (is_int($toEmail)) {
            $toEmail = $toName;
            $toName = '';
        }
    } else {
        $toEmail = (string) $to;
        $toName = '';
    }

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        echo "#{$row['id']} : email invalid ({$toEmail}), skipped.\n";
        ++$errors;
        continue;
    }

    // Try to extract subject from body.
    $subject = null;
    if (preg_match('/^Subject:\s*(.+)$/mi', $body, $m)) {
        $subject = trim($m[1]);
    }
    if (!$subject) {
        $subject = '[Contact] ' . $mailer->getInstallationTitle();
    }

    $num = $i + 1;
    $bodyPreview = mb_substr(
        preg_replace('/\s+/', ' ', $body), 0, 80
    );
    echo "{$num}. #{$row['id']} ({$row['created']})\n"
        . "   To: {$toEmail} | Subject: {$subject}\n"
        . "   Body: {$bodyPreview}…\n";

    if ($dryRun) {
        echo "\n";
        continue;
    }

    try {
        $message = $mailer->createMessage();
        $message->addTo($toEmail, $toName);
        $message->setSubject($subject);
        $message->setBody($body);
        $mailer->send($message);
        echo "   => Sent.\n\n";
        ++$sent;
    } catch (\Throwable $e) {
        echo "   => Error: " . $e->getMessage() . "\n\n";
        ++$errors;
    }
}

echo "\nResult: {$sent} sent, {$errors} errors.\n";
