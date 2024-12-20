<?php declare(strict_types=1);

namespace ContactUs\View\Helper;

use Common\Stdlib\PsrMessage;
use ContactUs\Form\ContactUsForm;
use ContactUs\Form\NewsletterForm;
use Laminas\Form\FormElementManager;
use Laminas\Http\PhpEnvironment\RemoteAddress;
use Laminas\Session\Container;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Mailer;

/**
 * @see \Access\Site\BlockLayout\AccessRequest
 * @see \ContactUs\Site\BlockLayout\ContactUs
 */
class ContactUs extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/contact-us';

    /**
     * The partial view script for button.
     */
    const PARTIAL_NAME_BUTTON = 'common/contact-us-button';

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
     * @var Messenger
     */
    protected $messenger;

    /**
     * @var string
     */
    protected $errorMessage;

    /**
     * @var array
     */
    protected $currentOptions = [];

    public function __construct(
        FormElementManager $formElementManager,
        array $defaultOptions,
        Mailer $mailer,
        Api $api,
        Messenger $messenger
    ) {
        $this->formElementManager = $formElementManager;
        $this->defaultOptions = $defaultOptions + [
            'template' => null,
            'resource' => null,
            'heading' => null,
            'html' => null,
            // Fields may contain a list of resource ids with key "id".
            'fields' => [],
            'as_button' => false,
            'attach_file' => false,
            'consent_label' => null,
            'unsubscribe' => null,
            'unsubscribe_label' => null,
            'newsletter_only' => false,
            'newsletter_label' => null,
            'notify_recipients' => null,
            'contact' => 'us',
            'author_email' => null,
            'confirmation_enabled' => false,
            'form_display_user_email_hidden' => false,
            'form_display_user_name_hidden' => false,
        ];
        $this->mailer = $mailer;
        $this->api = $api;
        $this->messenger = $messenger;
    }

    /**
     * Display the contact us form.
     */
    public function __invoke(array $options = []): string
    {
        // When the contact form is set multiple times on a page, it may be
        // stored multiple times, so these flags avoid to duplicate messages.
        static $isPostStored = null;
        static $messageSent = null;

        $options += $this->defaultOptions;

        $view = $this->getView();

        $template = $options['template']
            ?: ($options['as_button'] ? self::PARTIAL_NAME_BUTTON : self::PARTIAL_NAME);

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
                        'asButton' => $options['as_button'],
                        'form' => null,
                        'message' => $this->errorMessage,
                        'status' => 'error',
                        'resource' => $options['resource'],
                        'contact' => 'author',
                    ]
                );
            }
        }

        $this->currentOptions = $options;

        $user = $view->identity();
        $translate = $view->plugin('translate');

        $sendWithUserEmail = (bool) $view->setting('contactus_send_with_user_email');

        // Manage list of resource ids automatically, if any.
        // "resource_ids" is used for standard forms and fields for complex
        // forms with multiple specific fields.
        // TODO Manage "resource_ids" in backend, not only in js. But useless: already via fields[id] anyway.

        $fields = empty($options['fields'])
            ? ['id' => ['type' => 'hidden']]
            : ($options['fields'] + ['id' => ['type' => 'hidden']]);
        $attachFile = !empty($options['attach_file']);
        $consentLabel = trim((string) $options['consent_label']);
        $unsubscribe = !empty($options['unsubscribe']);
        $unsubscribeLabel = trim((string) $options['unsubscribe_label']);
        $newsletterOnly = !empty($options['newsletter_only']);
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
            $formOptions = [
                'fields' => $fields,
                'attach_file' => $attachFile,
                'consent_label' => $consentLabel,
                'newsletter_label' => $newsletterLabel,
                'unsubscribe' => $unsubscribe,
                'unsubscribe_label' => $unsubscribeLabel,
                'question' => $question,
                'answer' => $answer,
                'check_answer' => $checkAnswer,
                'user' => $user,
                'contact' => $isContactAuthor ? 'author' : 'us',
                'form_display_user_email_hidden' => !empty($options['form_display_user_email_hidden']),
                'form_display_user_name_hidden' => !empty($options['form_display_user_name_hidden']),
                'recaptcha' => !empty($options['recaptcha']),
            ];
            $form = $newsletterOnly
                ? $this->getFormNewsletter($formOptions)
                : $this->getFormContactUs($formOptions);

            $postFields = [];
            if ($fields) {
                // Manage exception for list of ids and security, because fields
                // are not fully checked.
                $params['fields']['id'] = array_values(array_filter(array_map('intval', $params['fields']['id'] ?? [])));
                foreach (array_keys($fields) as $name) {
                    $params['fields[' . $name . ']'] = $params['fields'][$name] ?? null;
                    $postFields[$name] = $params['fields'][$name] ?? null;
                    unset($params['fields'][$name]);
                }
            }

            $form->setData($params);
            if ($hasEmail && $form->isValid()) {
                $submitted = $form->getData();
                if ($user) {
                    $submitted['from'] = $user->getEmail();
                    // TODO What is the purpose of removing user name only for contact, not newsletter?
                    $submitted['name'] = $newsletterOnly ? $user->getName() : null;
                }

                $fileData = $attachFile ? $view->params()->fromFiles() : [];

                // If spam, store the message and return a success message, but
                // don't send email.

                // Status is checked below.
                $status = 'success';
                if ($newsletterOnly) {
                    if ($unsubscribe) {
                        $message = new PsrMessage(
                            'The unsubscription for {email} is confirmed.', // @translate
                            ['email' => $submitted['from']]
                        );
                    } else {
                        $message = new PsrMessage(
                            'Thank you for subscribing to our newsletter, {name}.', // @translate
                            ['name' => $submitted['name'] ? sprintf('%s (%s)', $submitted['name'], $submitted['from']) : $submitted['from']]
                        );
                    }
                } else {
                    if ($isContactAuthor) {
                        $message = new PsrMessage(
                            'Thank you for your message, {name}. It will be sent to the author as soon as possible.', // @translate
                            ['name' => $submitted['name'] ? sprintf('%s (%s)', $submitted['name'], $submitted['from']) : $submitted['from']]
                        );
                    } else {
                        $message = new PsrMessage(
                            'Thank you for your message, {name}. We will answer you as soon as possible.', // @translate
                            ['name' => $submitted['name'] ? sprintf('%s (%s)', $submitted['name'], $submitted['from']) : $submitted['from']]
                        );
                    }
                }

                $site = $this->currentSite();

                // Manage the specific field for multiple ids and convert it
                // into a resource when possible.
                if (empty($postFields['id'])) {
                    unset($postFields['id']);
                } elseif (is_array($postFields['id']) && count($postFields['id']) === 1 && empty($options['resource'])) {
                    try {
                        $fieldResource = $this->api->__invoke()->read('resources', ['id' => (int) $postFields['id']])->getContent();
                        $options['resource'] = $fieldResource;
                        unset($postFields['id']);
                    } catch (\Exception $e) {
                        // Nothing to do.
                    }
                }

                // Store contact message in all cases. Security checks are done
                // in adapter.
                // Use the controller plugin: the view cannot create and the
                // main manager cannot check form.
                $data = [
                    'o:owner' => $user,
                    'o:email' => $submitted['from'],
                    'o:name' => $newsletterOnly ? null : $submitted['name'],
                    'o:resource' => !empty($options['resource']) ? ['o:id' => $options['resource']->id()] : null,
                    'o:site' => ['o:id' => $site->id()],
                    'o-module-contact:subject' => $newsletterOnly
                        ? $translate($formOptions['unsubscribe']
                            ? 'Unsubscribe newsletter' // @translate
                            : 'Subscribe newsletter') // @translate
                        : $submitted['subject'],
                    'o-module-contact:body' => $newsletterOnly
                        ? $translate($formOptions['unsubscribe'] ? 'Unsubscribe newsletter' : 'Subscribe newsletter')
                        : $submitted['message'],
                    'o-module-contact:fields' => $postFields,
                    'o-module-contact:newsletter' => $newsletterOnly
                        ? empty($formOptions['unsubscribe'])
                        : ($newsletterLabel ? $submitted['newsletter'] === 'yes' : null),
                    'o-module-contact:is_spam' => $isSpam,
                    'o-module-contact:to_author' => $isContactAuthor,
                ];
                $response = null;
                if ($isPostStored === null) {
                    $response = $this->api->__invoke($form)->create('contact_messages', $data, $fileData);
                    $isFirst = true;
                    $isPostStored = !empty($response);
                } else {
                    $isFirst = false;
                    $response = $isPostStored;
                }

                // The message is already sent. Just keep the response.
                if (!$isFirst) {
                    $message = $messageSent;
                } elseif (!$response) {
                    $formMessages = $form->getMessages();
                    $errorMessages = [];
                    foreach ($formMessages as $formKeyMessages) {
                        foreach ($formKeyMessages as $formKeyMessage) {
                            $errorMessages[] = is_array($formKeyMessage) ? reset($formKeyMessage) : $formKeyMessage;
                        }
                    }
                    // TODO Map errors key with form (keep original keys of the form).
                    $this->messenger->addFormErrors($form);
                    $status = 'error';
                    $message = new PsrMessage(
                        'There is an error: {errors}', // @translate
                        ['errors' => implode(", \n", $errorMessages)]
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
                        $message = new PsrMessage(
                            'Thank you for your message {name}. Check your confirmation mail. The author will receive it soon.', // @translate
                            $submitted['name']
                                ? ['name' => sprintf('%1$s (%2$s)', $submitted['name'], $submitted['from'])]
                                : ['name' => $submitted['from']]
                        );

                        $notifyRecipients = $this->getNotifyRecipients($options);

                        $mail = [];
                        if ($sendWithUserEmail) {
                            $mail['from'] = reset($notifyRecipients) ?: $view->setting('administrator_email');
                        }
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
                            $message = new PsrMessage(
                                'Sorry, we are not able to send the email to the author.' // @translate
                            );
                        }
                    }

                    // Message to administrators.
                    else {
                        // Send the notification message to administrators.
                        $mail = [];
                        if ($sendWithUserEmail) {
                            $mail['from'] = $contactMessage->email();
                            $mail['fromName'] = $contactMessage->name();
                        }
                        $mail['to'] = $this->getNotifyRecipients($options);
                        $mail['subject'] = $this->getMailSubject($options)
                            ?: sprintf($translate('[Contact] %s'), $this->mailer->getInstallationTitle());
                        $body = $view->siteSetting('contactus_notify_body')
                            ?: $translate($this->defaultOptions['notify_body']);
                        $mail['body'] = $this->fillMessage($body, $submitted);
                        $result = $this->sendEmail($mail);
                        if (!$result) {
                            $status = 'error';
                            $message = new PsrMessage(
                                'Sorry, the message is recorded, but we are not able to notify the admin at once. You may come back later if you don’t receive answer.' // @translate
                            );
                        }
                        // Send the confirmation message to the visitor.
                        elseif ($options['confirmation_enabled']) {
                            if ($newsletterOnly) {
                                $message = $view->siteSetting('contactus_confirmation_message_newsletter')
                                    ?: $translate($this->defaultOptions['confirmation_message_newsletter']);
                            } else {
                                $message = $view->siteSetting('contactus_confirmation_message')
                                    ?: $translate($this->defaultOptions['confirmation_message']);
                            }
                            $placeholders = [];
                            if (mb_strpos($message, '{email}') !== false) {
                                $placeholders['email'] = $submitted['from'];
                            }
                            if (mb_strpos($message, '{name}') !== false) {
                                $placeholders['name'] = $submitted['name']
                                    ? $submitted['name']
                                    : $submitted['from'];
                            }
                            $message = new PsrMessage($message, $placeholders);

                            $mail = [];
                            if ($sendWithUserEmail) {
                                $notifyRecipients = $this->getNotifyRecipients($options);
                                $mail['from'] = reset($notifyRecipients) ?: $view->setting('administrator_email');
                            }
                            $mail['to'] = $submitted['from'];
                            $mail['toName'] = $submitted['name'] ?: null;
                            if ($newsletterOnly) {
                                $subject = $options['confirmation_subject'] ?: $this->defaultOptions['confirmation_newsletter_subject'];
                                $body = $options['confirmation_body'] ?: $this->defaultOptions['confirmation_newsletter_body'];
                            } else {
                                $subject = $options['confirmation_subject'] ?: $this->defaultOptions['confirmation_subject'];
                                $body = $options['confirmation_body'] ?: $this->defaultOptions['confirmation_body'];
                            }
                            $mail['subject'] = $this->fillMessage($translate($subject), $submitted);
                            $mail['body'] = $this->fillMessage($translate($body), $submitted);

                            $result = $this->sendEmail($mail);
                            if (!$result) {
                                $status = 'error';
                                $message = new PsrMessage(
                                    'Sorry, we are not able to send the confirmation email.' // @translate
                                );
                            }
                        }
                    }
                }
            } else {
                $formMessages = $form->getMessages();
                $errorMessages = [];
                foreach ($formMessages as $formKeyMessages) {
                    foreach ($formKeyMessages as $formKeyMessage) {
                        $errorMessages[] = is_array($formKeyMessage) ? reset($formKeyMessage) : $formKeyMessage;
                    }
                }
                // TODO Map errors key with form (keep original keys of the form).
                $this->messenger->addFormErrors($form);
                $status = 'error';
                $message = count($errorMessages)
                    ? new PsrMessage(
                        'There is an error: {errors}', // @translate
                        ['errors' => implode(", \n", $errorMessages)]
                    )
                    : new PsrMessage(
                        'There is an error.' // @translate
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
            $formOptions = [
                'fields' => $fields,
                'attach_file' => $attachFile,
                'consent_label' => $consentLabel,
                'newsletter_label' => $newsletterLabel,
                'unsubscribe' => $unsubscribe,
                'unsubscribe_label' => $unsubscribeLabel,
                'question' => $question,
                'answer' => $answer,
                'check_answer' => $checkAnswer,
                'user' => $user,
                'contact' => $isContactAuthor ? 'author' : 'us',
                'form_display_user_email_hidden' => !empty($options['form_display_user_email_hidden']),
                'form_display_user_name_hidden' => !empty($options['form_display_user_name_hidden']),
                'recaptcha' => !empty($options['recaptcha']),
            ];
            $form = $newsletterOnly
                ? $this->getFormNewsletter($formOptions)
                : $this->getFormContactUs($formOptions);
        }

        if ($user) {
            $form->get('from')
                ->setValue($user->getEmail())
                ->setAttribute('disabled', 'disabled');
            if (!$newsletterOnly) {
                $form->get('name')
                    ->setValue($user->getName())
                    ->setAttribute('disabled', 'disabled');
            }
        }

        if ($options['resource']) {
            $answer = 'About resource %s (%s).'; // @translate
            $form->get('message')
                ->setAttribute('value', sprintf($answer, $options['resource']->displayTitle(), $options['resource']->siteUrl(null, true)) . "\n\n");
        }

        $form->init();
        $form->setName($newsletterOnly ? 'newsletter' : 'contact-us');

        $messageSent = $message;

        return $view->partial(
            $template,
            [
                'heading' => $options['heading'],
                'html' => $options['html'],
                'asButton' => $options['as_button'],
                'form' => $form,
                'message' => $message ? $message->setTranslator($view->translator()) : null,
                'status' => $status,
                'resource' => $options['resource'],
                'contact' => $isContactAuthor ? 'author' : 'us',
            ]
        );
    }

    protected function getFormContactUs(array $formOptions): ContactUsForm
    {
        /** @var \ContactUs\Form\ContactUsForm $form */
        $form = $this->formElementManager->get(ContactUsForm::class, $formOptions);
        return $form
            ->setFormOptions($formOptions)
            // Append specific fields, included resource ids, to the form.
            ->setFields($formOptions['fields'])
            ->setAttachFile($formOptions['attach_file'])
            ->setConsentLabel($formOptions['consent_label'])
            ->setNewsletterLabel($formOptions['newsletter_label'])
            ->setQuestion($formOptions['question'])
            ->setAnswer($formOptions['answer'])
            ->setCheckAnswer($formOptions['check_answer'])
            ->setUser($formOptions['user'])
            ->setIsContactAuthor($formOptions['contact'] === 'author')
        ;
    }

    protected function getFormNewsletter(array $formOptions): NewsletterForm
    {
        /** @var \ContactUs\Form\NewsletterForm $form */
        $form = $this->formElementManager->get(NewsletterForm::class, $formOptions);
        return $form
            ->setFormOptions($formOptions)
            ->setConsentLabel($formOptions['consent_label'])
            ->setUnsubscribe($formOptions['unsubscribe'])
            ->setUnsubscribeLabel($formOptions['unsubscribe_label'])
            ->setQuestion($formOptions['question'])
            ->setAnswer($formOptions['answer'])
            ->setCheckAnswer($formOptions['check_answer'])
            ->setUser($formOptions['user'])
        ;
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
        $replace = [];
        foreach ($placeholders as $placeholder => $value) {
            $replace['{' . $placeholder . '}'] = $value;
        }

        $plugins = $this->view->getHelperPluginManager();
        $url = $plugins->get('url');
        $site = $this->currentSite();
        $translate = $plugins->get('translate');

        // Placehoders are the submitted values: from, email, name, site_title,
        // site_url, subject, message, ip.

        $defaultPlaceholders = [
            '{ip}' => (new RemoteAddress())->getIpAddress(),
            '{main_title}' => $this->mailer->getInstallationTitle(),
            '{main_url}' => $url('top', [], ['force_canonical' => true]),
            '{site_title}' => $site->title(),
            '{site_url}' => $site->siteUrl(),
        ];
        $replace += $defaultPlaceholders;

        if (!empty($this->currentOptions['resource'])) {
            $replace['{resource_id}'] = $this->currentOptions['resource']->id();
            $replace['{resource_title}'] = $this->currentOptions['resource']->displayTitle();
            $replace['{resource_url}'] = $this->currentOptions['resource']->siteUrl();
            $resourceJson = json_decode(json_encode($this->currentOptions['resource']), true);
            foreach ($resourceJson as $term => $value) {
                if (!is_array($value) || empty($value) || !isset(reset($value)['type'])) {
                    continue;
                }
                $first = reset($value);
                if (!empty($first['@id'])) {
                    $replace['{' . $term . '}'] = $first['@id'];
                } elseif (!empty($first['value_resource_id'])) {
                    try {
                        $replace['{' . $term . '}'] = $this->api->read('resources', ['id' => $first['value_resource_id']], [], ['initialize' => false, 'finalize' => false])->getContent()->getTitle();
                    } catch (\Exception $e) {
                        $replace['{' . $term . '}'] = $translate('[Unknown resource]'); // @translate
                    }
                } elseif (isset($first['@value']) && strlen((string) $first['@value'])) {
                    $replace['{' . $term . '}'] = $first['@value'];
                }
            }
        }

        return str_replace(array_keys($replace), array_values($replace), $message);
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
            $list = array_filter($originalList, fn ($v) => filter_var($v, FILTER_VALIDATE_EMAIL));
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
            $view->logger()->err(
                'The message has no content to send.' // @translate
            );
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
        if (!empty($params['from'])) {
            $message
                ->setFrom($params['from'], $params['fromName']);
        }
        try {
            $this->mailer->send($message);
            return true;
        } catch (\Exception $e) {
            $view->logger()->err(
                'Error when sending email. Arguments:\n{json}', // @translate
                ['json' => json_encode($params, 448)]
            );
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
                [$key, $value] = array_map('trim', explode('=', $keyValue, 2));
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
