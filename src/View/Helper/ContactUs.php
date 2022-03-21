<?php declare(strict_types=1);

namespace ContactUs\View\Helper;

use ContactUs\Form\ContactUsForm;
use Laminas\Form\FormElementManager\FormElementManagerV3Polyfill as FormElementManager;
use Laminas\Http\PhpEnvironment\RemoteAddress;
use Laminas\Session\Container;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Mailer;
use Omeka\Stdlib\Message;

class ContactUs extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/contact-us';

    /**
     * @var FormElementManager
     */
    protected $formElementManager;

    /**
     * @var array
     */
    protected $defaultOptions;

    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var Api
     */
    protected $api;

    /**
     * @var string
     */
    protected $errorMessage;

    public function __construct(
        FormElementManager $formElementManager,
        array $defaultOptions,
        Mailer $mailer,
        Api $api
    ) {
        $this->formElementManager = $formElementManager;
        $this->defaultOptions = $defaultOptions + [
            'template' => null,
            'resource' => null,
            'heading' => null,
            'html' => null,
            'attach_file' => null,
            'consent_label' => null,
            'newsletter_label' => null,
            'notify_recipients' => null,
            'contact' => 'us',
            'author_email' => null,
        ];
        $this->mailer = $mailer;
        $this->api = $api;
    }

    /**
     * Display the contact us form.
     */
    public function __invoke(array $options = []): string
    {
        $options += $this->defaultOptions;

        $view = $this->getView();

        $template = $options['template'] ?: self::PARTIAL_NAME;

        $isContactAuthor = $options['contact'] === 'author';
        if ($isContactAuthor) {
            // Remove useless options.
            $options['attach_file'] = false;
            $options['consent_label'] = '';
            $options['newsletter_label'] = '';
            $options['author_email'] = $this->authorEmail($options);
            // Early return when there is no author email.
            if (empty($options['author_email'])) {
                return $view->partial(
                    $template,
                    [
                        'heading' => $options['heading'],
                        'html' => $options['html'],
                        'form' => null,
                        'message' => $this->errorMessage,
                        'status' => 'error',
                        'resource' => $options['resource'],
                        'contact' => 'author',
                    ]
                );
            }
        }

        $user = $view->identity();
        $translate = $view->plugin('translate');

        $attachFile = !empty($options['attach_file']);
        $consentLabel = trim((string) $options['consent_label']);
        $newsletterLabel = trim((string) $options['newsletter_label']);

        $antispam = empty($user)
            && !empty($options['antispam'])
            && !empty($options['questions']);
        $isSpam = false;
        $message = null;
        $status = null;
        $defaultForm = true;

        $question = '';
        $answer = '';
        $checkAnswer = '';

        // Sometime, questions/answers are not converted into array in form.
        // Fix https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/issues/10.
        // This is probably related to an old config that wasn't updated. So,
        // waiting the admin to check an issue in the page and to resave it.
        // TODO Remove this check and associated code during upgrade.
        if ($antispam) {
            $options['questions'] = $this->checkAntispamOptions($options['questions']);
        }

        $params = $view->params()->fromPost();
        if ($params) {
            if ($antispam) {
                $isSpam = $this->checkSpam($options, $params);
                if (!$isSpam) {
                    $question = (new Container('ContactUs'))->question;
                    $answer = $params['answer'] ?? false;
                    $checkAnswer = $options['questions'][$question];
                }
            }

            $params += ['from' => null, 'name' => null];
            $hasEmail = $params['from'] || $user;

            /** @var \ContactUs\Form\ContactUsForm $form */
            $form = $this->formElementManager->get(ContactUsForm::class, [
                'attach_file' => $attachFile,
                'consent_label' => $consentLabel,
                'newsletter_label' => $newsletterLabel,
                'question' => $question,
                'answer' => $answer,
                'check_answer' => $checkAnswer,
                'user' => $user,
                'contact' => $isContactAuthor ? 'author' : 'us',
            ]);
            $form
                ->setAttachFile($attachFile)
                ->setConsentLabel($consentLabel)
                ->setNewsletterLabel($newsletterLabel)
                ->setQuestion($question)
                ->setAnswer($answer)
                ->setCheckAnswer($checkAnswer)
                ->setUser($user);

            $form->setData($params);
            if ($hasEmail && $form->isValid()) {
                $submitted = $form->getData();
                if ($user) {
                    $submitted['from'] = $user->getEmail();
                    $submitted['name'] = $user->getName();
                }

                $fileData = $attachFile ? $view->params()->fromFiles() : [];

                // If spam, return a success message, but don't send email.
                // Status is checked below.
                $status = 'success';
                $message = new Message(
                    $isContactAuthor
                        ? $translate('Thank you for your message %s. It will be sent to the author as soon as possible.') // @translate
                        : $translate('Thank you for your message %s. We will answer you as soon as possible.'), // @translate
                    $submitted['name']
                        ? sprintf('%s (%s)', $submitted['name'], $submitted['from'])
                        : sprintf('(%s)', $submitted['from'])
                );

                $site = $this->currentSite();

                // Store contact message in all cases. Security checks are done
                // in adapter.
                // Use the controller plugin: the view cannot create and the
                // main manager cannot check form.
                $response = $this->api->__invoke($form)->create('contact_messages', [
                    'o:owner' => $user,
                    'o:email' => $submitted['from'],
                    'o:name' => $submitted['name'],
                    'o:site' => ['o:id' => $site->id()],
                    'o-module-contact:subject' => $submitted['subject'],
                    'o-module-contact:body' => $submitted['message'],
                    'o-module-contact:newsletter' => $newsletterLabel ? $submitted['newsletter'] === 'yes' : null,
                    'o-module-contact:is_spam' => $isSpam,
                    'o-module-contact:to_author' => $isContactAuthor,
                ], $fileData);

                if (!$response) {
                    $formMessages = $form->getMessages();
                    $errorMessages = [];
                    foreach ($formMessages as $formKeyMessages) {
                        foreach ($formKeyMessages as $formKeyMessage) {
                            $errorMessages[] = is_array($formKeyMessage) ? reset($formKeyMessage) : $formKeyMessage;
                        }
                    }
                    // TODO Map errors key with form (keep original keys of the form).
                    $messenger = new Messenger();
                    $messenger->addFormErrors($form);
                    $status = 'error';
                    $message = new Message(
                        $translate('There is an error: %s'), // @translate
                        implode(", \n", $errorMessages)
                    );
                    $defaultForm = false;
                }

                // Send non-spam message to administrators and author.
                elseif (!$isSpam) {
                    // Use contact message and not form, because it is filtered.
                    // Add some keys for placeholders too.
                    /** @var \ContactUs\Api\Representation\MessageRepresentation $contactMessage */
                    $contactMessage = $response->getContent();
                    $submitted['from'] = $contactMessage->email();
                    $submitted['email'] = $contactMessage->email();
                    $submitted['name'] = $contactMessage->name();
                    $submitted['site_title'] = $contactMessage->site()->title();
                    $submitted['site_url'] = $contactMessage->site()->siteUrl();
                    $submitted['subject'] = $contactMessage->subject()
                        ?: sprintf($translate('[Contact] %s'), $this->mailer->getInstallationTitle());
                    $submitted['message'] = $contactMessage->body();
                    $submitted['ip'] = $contactMessage->ip();

                    if ($newsletterLabel) {
                        $submitted['newsletter'] = sprintf(
                            $translate('newsletter: %s'), // @translate
                            $contactMessage->newsletter()
                                ? $translate('yes') // @translate
                                : $translate('no') // @translate
                        ) . "\n";
                    } else {
                        $submitted['newsletter'] = '';
                    }

                    // Message to author (with copy to administrators if set).
                    if ($isContactAuthor) {
                        $message = new Message(
                            $translate('Thank you for your message %s. Check your confirmation mail. The author will receive it soon.'), // @translate
                            $submitted['name']
                                ? sprintf('%1$s (%2$s)', $submitted['name'], $submitted['from'])
                                : sprintf('(%s)', $submitted['from'])
                        );

                        $notifyRecipients = $this->getNotifyRecipients($options);

                        $mail = [];
                        $mail['from'] = reset($notifyRecipients) ?: $view->setting('administrator_email');
                        $mail['to'] = $options['author_email'];
                        $mail['toName'] = null;
                        $mail['reply-to'] = $submitted['email'];
                        $subject = $options['to_author_subject'] ?: $this->defaultOptions['to_author_subject'];
                        $body = $options['to_author_body'] ?: $this->defaultOptions['to_author_body'];
                        // Avoid issue with bad config.
                        if (strpos($body, '{email}') === false) {
                            $body .= "\n\nFrom {email}";
                        }
                        if (strpos($body, '{message}') === false) {
                            $body .= "\n\n{message}";
                        }
                        $mail['subject'] = $this->fillMessage($translate($subject), $submitted);
                        $mail['body'] = $this->fillMessage($translate($body), $submitted);

                        if (!$view->setting('contactus_author_only')) {
                            $mail['bcc'] = $notifyRecipients ?: $view->setting('administrator_email');
                        }

                        $result = $this->sendEmail($mail);
                        if (!$result) {
                            $status = 'error';
                            $message = new Message(
                                $translate('Sorry, we are not able to send the email to the author.') // @translate
                            );
                        }
                    }

                    // Message to administrators.
                    else {
                        // Send the notification message to administrators.
                        $mail = [];
                        $mail['from'] = $contactMessage->email();
                        $mail['fromName'] = $contactMessage->name();
                        $mail['to'] = $this->getNotifyRecipients($options);
                        $mail['subject'] = $this->getMailSubject($options)
                            ?: sprintf($translate('[Contact] %s'), $this->mailer->getInstallationTitle());
                        $body = $view->siteSetting('contactus_notify_body')
                            ?: $translate($this->defaultOptions['notify_body']);
                        $mail['body'] = $this->fillMessage($body, $submitted);
                        $result = $this->sendEmail($mail);
                        if (!$result) {
                            $status = 'error';
                            $message = new Message(
                                $translate('Sorry, the message is recorded, but we are not able to notify the admin at once. You may come back later if you don’t receive answer.') // @translate
                            );
                        }
                        // Send the confirmation message to the visitor.
                        elseif ($options['confirmation_enabled']) {
                            $message = new Message(
                                $translate('Thank you for your message %s. Check your confirmation mail. We will answer you soon.'), // @translate
                                $submitted['name']
                                    ? sprintf('%1$s (%2$s)', $submitted['name'], $submitted['from'])
                                    : sprintf('(%s)', $submitted['from'])
                            );

                            $notifyRecipients = $this->getNotifyRecipients($options);

                            $mail = [];
                            $mail['from'] = reset($notifyRecipients) ?: $view->setting('administrator_email');
                            $mail['to'] = $submitted['from'];
                            $mail['toName'] = $submitted['name'] ?: null;
                            $subject = $options['confirmation_subject'] ?: $this->defaultOptions['confirmation_subject'];
                            $body = $options['confirmation_body'] ?: $this->defaultOptions['confirmation_body'];
                            $mail['subject'] = $this->fillMessage($translate($subject), $submitted);
                            $mail['body'] = $this->fillMessage($translate($body), $submitted);

                            $result = $this->sendEmail($mail);
                            if (!$result) {
                                $status = 'error';
                                $message = new Message(
                                    $translate('Sorry, we are not able to send the confirmation email.') // @translate
                                );
                            }
                        }
                    }
                }
            } else {
                $status = 'error';
                $message = new Message(
                    $translate('There is an error.') // @translate
                );
                $defaultForm = false;
            }
        }

        if ($defaultForm) {
            if ($antispam) {
                $question = array_rand($options['questions']);
                $answer = $options['questions'][$question];
                $session = new Container('ContactUs');
                $session->question = $question;
            } else {
                $question = '';
                $answer = '';
                $checkAnswer = '';
            }
            $form = $this->formElementManager->get(ContactUsForm::class, [
                'attach_file' => $attachFile,
                'consent_label' => $consentLabel,
                'newsletter_label' => $newsletterLabel,
                'question' => $question,
                'answer' => $answer,
                'check_answer' => $checkAnswer,
                'user' => $user,
                'contact' => $isContactAuthor ? 'author' : 'us',
            ]);
            $form
                ->setAttachFile($attachFile)
                ->setConsentLabel($consentLabel)
                ->setNewsletterLabel($newsletterLabel)
                ->setQuestion($question)
                ->setAnswer($answer)
                ->setCheckAnswer($checkAnswer)
                ->setUser($user);
        }

        if ($user):
            $form->get('from')
                ->setValue($user->getEmail())
                ->setAttribute('disabled', 'disabled');
            $form->get('name')
                ->setValue($user->getName())
                ->setAttribute('disabled', 'disabled');
        endif;

        if ($options['resource']):
            $answer = 'About resource %s (%s).'; // @translate
            $form->get('message')
                    ->setAttribute('value', sprintf($answer, $options['resource']->displayTitle(), $options['resource']->siteUrl(null, true)) . "\n\n");
        endif;

        $form->init();
        $form->setName('contact-us');

        return $view->partial(
            $template,
            [
                'heading' => $options['heading'],
                'html' => $options['html'],
                'form' => $form,
                'message' => $message,
                'status' => $status,
                'resource' => $options['resource'],
                'contact' => $isContactAuthor ? 'author' : 'us',
            ]
        );
    }

    /**
     * Get the author email of a resource.
     */
    protected function authorEmail(array $options): ?string
    {
        if (empty($options['resource'])) {
            $this->errorMessage = 'You must select a resource to contact the author.'; // @translate
            return null;
        }

        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
        $resource = $options['resource'];

        $view = $this->getView();

        // Check if author email is available first.
        $propertyEmail = $view->setting('contactus_author');
        if ($propertyEmail === 'owner') {
            $owner = $resource->owner()
                ?? ($resource instanceof \Omeka\Api\Representation\MediaRepresentation ? $resource->item()->owner() : null);
            if (!$owner) {
                $this->errorMessage = 'This resource has no author to contact. Contact administor for more information.'; // @translate
                return null;
            }
            $email = $owner->email();
        } elseif (strpos($propertyEmail, ':')) {
            // The email should be an hidden field for security, so it is not
            // possible to get the value directly, so use a direct query.
            $propertyId = (int) $this->api->searchOne('properties', ['term' => $propertyEmail], ['initialize' => false, 'returnScalar' => 'id'])->getContent();
            $connection = $resource->getServiceLocator()->get('Omeka\Connection');
            $sql = <<<'SQL'
SELECT `value`
FROM `value`
WHERE `resource_id` = :resource_id
    AND `property_id` = :property_id
    AND `value` IS NOT NULL
    AND `value` != ""
LIMIT 1;
SQL;
            $email = $connection->executeQuery($sql, ['resource_id' => (int) $resource->id(), 'property_id' => $propertyId], ['resource_id' => \Doctrine\DBAL\ParameterType::INTEGER, 'property_id' => \Doctrine\DBAL\ParameterType::INTEGER])->fetchOne();
        } else {
            // Disabled.
            $this->errorMessage = 'Contact administor for more information.'; // @translate
            return null;
        }

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errorMessage = 'This resource has no author to contact. Contact administor for more information.'; // @translate
            return null;
        }

        return $email;
    }

    /**
     * Check if a post is a spam.
     *
     * @param array $options
     * @param array $params Post data.
     */
    protected function checkSpam(array $options, array $params): bool
    {
        $session = new Container('ContactUs');
        $question = isset($session->question) ? $session->question : null;
        return empty($question)
            || !isset($options['questions'][$question])
            || empty($params['check'])
            || substr(md5($question), 0, 16) !== $params['check'];
    }

    /**
     * Fill a message with placeholders (moustache style).
     */
    protected function fillMessage($message, array $placeholders): string
    {
        $holders = [];
        foreach ($placeholders as $placeholder => $value) {
            $holders['{' . $placeholder . '}'] = $value;
        }

        $defaultPlaceholders = [
            '{ip}' => (new RemoteAddress())->getIpAddress(),
            '{main_title}' => $this->mailer->getInstallationTitle(),
            '{main_url}' => $this->mailer->getSiteUrl(),
        ];
        $holders += $defaultPlaceholders;

        return str_replace(array_keys($holders), array_values($holders), $message);
    }

    protected function getNotifyRecipients(array $options): array
    {
        $view = $this->getView();

        $list = $options['notify_recipients'] ?? [];
        if (!$list) {
            $list = $view->siteSetting('contactus_notify_recipients') ?: $view->setting('contactus_notify_recipients');
        }

        // Check emails.
        if ($list) {
            $originalList = array_filter($list);
            $list = array_filter($originalList, function ($v) {
                return filter_var($v, FILTER_VALIDATE_EMAIL);
            });
            if (count($originalList) !== count($list)) {
                $view->logger()->err('Contact Us: Some notification emails for module are invalid.'); // @translate
            }
        }

        if (!count($list)) {
            $site = $this->currentSite();
            $owner = $site->owner();
            $list = $owner ? [$owner->email()] : [$view->setting('administrator_email')];
        }

        $isContactAuthor = $options['contact'] === 'author';
        if ($isContactAuthor) {
            if ($view->setting('contactus_author_only', false)) {
                // The author email should be checked.
                $list = [$options['author_email']];
            } else {
                array_unshift($list, $options['author_email']);
            }
        }

        return $list;
    }

    /**
     * Send an email.
     *
     * @param array $params The params are already checked (from, to, subject,
     * body).
     */
    protected function sendEmail(array $params): bool
    {
        $view = $this->getView();
        $defaultParams = [
            'fromName' => null,
            'toName' => null,
            'subject' => sprintf($view->translate('[Contact] %s'), $this->mailer->getInstallationTitle()),
            'body' => null,
            'to' => [],
            'cc' => [],
            'bcc' => [],
            'reply-to' => [],
        ];
        $params += $defaultParams;
        if (empty($params['body'])) {
            $view->logger()->err(new Message(
                'The message has no content to send.' // @translate
            ));
            return false;
        }

        $message = $this->mailer->createMessage();
        $message
            ->setSubject($params['subject'])
            ->setBody($params['body']);
        $to = is_array($params['to']) ? $params['to'] : [$params['to']];
        foreach ($to as $t) {
            $message->addTo($t);
        }
        $cc = is_array($params['cc']) ? $params['cc'] : [$params['cc']];
        foreach ($cc as $c) {
            $message->addCc($c);
        }
        $bcc = is_array($params['bcc']) ? $params['bcc'] : [$params['bcc']];
        foreach ($bcc as $b) {
            $message->addBcc($b);
        }
        $replyTo = is_array($params['reply-to']) ? $params['reply-to'] : [$params['reply-to']];
        foreach ($replyTo as $r) {
            $message->addReplyTo($r);
        }
        if ($params['from']) {
            $message
                ->setFrom($params['from'], $params['fromName']);
        }
        try {
            $this->mailer->send($message);
            return true;
        } catch (\Exception $e) {
            $view->logger()->err(new Message(
                'Error when sending email. Arguments:\n%s', // @translate
                json_encode($params, 448)
            ));
            return false;
        }
    }

    protected function currentSite(): ?\Omeka\Api\Representation\SiteRepresentation
    {
        return $this->view->site ?? $this->view->site = $this->view
            ->getHelperPluginManager()
            ->get('Laminas\View\Helper\ViewModel')
            ->getRoot()
            ->getVariable('site');
    }

    protected function getMailSubject(array $options = []): string
    {
        if (!empty($options['subject'])) {
            return (string) $options['subject'];
        }

        $view = $this->getView();
        $default = sprintf($view->translate('[Contact] %s'), $this->mailer->getInstallationTitle());

        return (string) $view->siteSetting('contactus_notify_subject', $default);
    }

    protected function checkAntispamOptions($options): array
    {
        if (is_array($options)) {
            return $options;
        }
        $string = $options;
        $result = [];
        foreach ($this->stringToList($string) as $keyValue) {
            if (strpos($keyValue, '=') === false) {
                $result[trim($keyValue)] = '';
            } else {
                list($key, $value) = array_map('trim', explode('=', $keyValue, 2));
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Get each line of a string separately as a list.
     */
    protected function stringToList($string): array
    {
        return array_filter(array_map('trim', explode("\n", $this->fixEndOfLine($string))), 'strlen');
    }

    /**
     * Clean the text area from end of lines.
     *
     * This method fixes Windows and Apple copy/paste from a textarea input.
     */
    protected function fixEndOfLine($string): string
    {
        return str_replace(["\r\n", "\n\r", "\r"], ["\n", "\n", "\n"], (string) $string);
    }
}
