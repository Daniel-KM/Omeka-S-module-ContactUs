<?php declare(strict_types=1);

namespace ContactUs\View\Helper;

use Common\Mvc\Controller\Plugin\SendEmail;
use Common\Stdlib\EasyMeta;
use Common\Stdlib\PsrMessage;
use ContactUs\Form\ContactUsForm;
use ContactUs\Form\NewsletterForm;
use Laminas\Form\FormElementManager;
use Laminas\Http\PhpEnvironment\RemoteAddress;
use Laminas\Session\Container;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Manager as ApiManager;
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
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Omeka\Mvc\Controller\Plugin\Api
     */
    protected $apiPlugin;

    /**
     * @var \Common\Stdlib\EasyMeta
     */
    protected $easyMeta;

    /**
     * @var FormElementManager
     */
    protected $formElementManager;

    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var Messenger
     */
    protected $messenger;

    /**
     * @var SendEmail
     */
    protected $sendEmail;

    /**
     * @var array
     */
    protected $defaultOptions;

    /**
     * @var array
     */
    protected $currentOptions = [];

    /**
     * @var string
     */
    protected $errorMessage;

    public function __construct(
        Api $api,
        ApiManager $apiManager,
        EasyMeta $easyMeta,
        FormElementManager $formElementManager,
        Mailer $mailer,
        Messenger $messenger,
        SendEmail $sendEmail,
        array $defaultOptions
    ) {
        $this->api = $apiManager;
        $this->apiPlugin = $api;
        $this->easyMeta = $easyMeta;
        $this->formElementManager = $formElementManager;
        $this->mailer = $mailer;
        $this->messenger = $messenger;
        $this->sendEmail = $sendEmail;
        $this->defaultOptions = $defaultOptions + [
            'template' => null,
            'resource' => null,
            'heading' => null,
            'html' => null,
            'fields' => [],
            'as_button' => false,
            'attach_file' => false,
            'consent_label' => null,
            'unsubscribe' => false,
            'unsubscribe_label' => null,
            'newsletter_only' => false,
            'newsletter_label' => null,
            'sender_email' => null,
            'sender_name' => null,
            'notify_recipients' => [],
            'contact' => 'us',
            'author_email' => null,
            'confirmation_enabled' => false,
            'form_display_user_email_hidden' => false,
            'form_display_user_name_hidden' => false,
            'to_author_subject' => '',
            'to_author_body' => '',
            'notify_body' => '',
            'confirmation_newsletter_subject' => '',
            'confirmation_newsletter_body' => '',
            'confirmation_subject' => '',
            'confirmation_body' => '',
        ];
    }

    /**
     * Display the contact us form or get posted data.
     *
     * @param array $options Managed and passed options.
     * - template (string)
     * - resource (AbstractEntityResourceRepresentation)
     * - heading (string)
     * - html (string)
     * - fields (array): Fields are the elements to add to the contact form.
     *   Exception: Fields may contain a list of resource ids on key "id".
     * - as_button (bool)
     * - attach_file (bool)
     * - consent_label (string)
     * - unsubscribe (bool)
     * - unsubscribe_label (string)
     * - newsletter_only (bool)
     * - newsletter_label (string)
     * - sender_email (string)
     * - sender_name (string)
     * - notify_recipients (array)
     * - contact (string): "us" or "author".
     * - author_email (string)
     * - confirmation_enabled (bool)
     * - form_display_user_email_hidden (false)
     * - form_display_user_name_hidden (false)
     * - to_author_subject (string)
     * - to_author_body (string)
     * - notify_body (string)
     * - confirmation_newsletter_subject (string)
     * - confirmation_newsletter_body (string)
     * - confirmation_subject (string)
     * - confirmation_body (string)
     *
     * @return string|array Array is used only to return data after a post
     * submitted via a dialog.
     */
    public function __invoke(array $options = [])
    {
        // When the contact form is set multiple times on a page, it may be
        // stored multiple times, so these flags avoid to duplicate messages.
        static $isPostStored = null;
        static $messageSent = null;

        $options += $this->defaultOptions;

        $view = $this->getView();

        $params = $view->params()->fromPost();
        $isPost = !empty($params);

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
                $args = [
                    'heading' => $options['heading'],
                    'html' => $options['html'],
                    'asButton' => $options['as_button'],
                    'form' => null,
                    'resource' => $options['resource'],
                    'contact' => 'author',
                    'status' => 'error',
                    'message' => $this->errorMessage,
                ];
                return $isPost
                    // Only status and message are really needed.
                    ? $args
                    : $view->partial($template, $args);
            }
        }

        $this->currentOptions = $options;

        $user = $view->identity();
        $setting = $view->plugin('setting');
        $siteSetting = $view->plugin('siteSetting');
        $translate = $view->plugin('translate');

        $site = $this->currentSite();

        $sendWithUserEmail = (bool) $setting('contactus_send_with_user_email');

        // Manage list of resource ids automatically, if any.
        // "resource_ids" is used for standard forms and fields for complex
        // forms with multiple specific fields.
        // TODO Manage "resource_ids" in backend, not only in js. But useless: already via fields[id] anyway. So "resource_ids" should be deprecated.

        // The field "id" should be an array.
        // When hidden, the value may or may not be converted.
        if (empty($options['fields']['id']) || $options['fields']['id'] === '[]') {
            $options['fields']['id'] = [];
        } elseif (is_string($options['fields']['id'])
            && (
                (substr($options['fields']['id'], 0, 1) === '[' && substr($options['fields']['id'], -1) === ']')
                || (substr($options['fields']['id'], 0, 1) === '{' && substr($options['fields']['id'], -1) === '}')
            )
        ) {
            $options['fields']['id'] = json_decode($options['fields']['id'], true);
        } elseif (!is_array($options['fields']['id'])) {
            $options['fields']['id'] = [$options['fields']['id']];
        }

        // For fields, append the resource early.
        if ($options['resource']) {
            $options['fields']['id'][] = $options['resource']->id();
        }

        // The fields id should be integer and unique.
        $options['fields']['id'] = isset($options['fields']['id']['value'])
            ? array_values(array_unique(array_filter(array_map('intval', $options['fields']['id']['value']))))
            : array_values(array_unique(array_filter(array_map('intval', $options['fields']['id']))));

        // The option fields are all specific fields set via the theme.
        // They are added in the form. The list of ids is added automatically.
        // For form, the fields id should be hidden.
        $fieldsForForm = $options['fields'];
        $fieldsForForm['id'] = [
            'type' => 'hidden',
            'value' => $fieldsForForm['id'],
        ];

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

        if ($isPost) {
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
                'fields' => $fieldsForForm,
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

            // TODO Remove this normalization of posted data. For old themes.
            // Add the options fields to the posted fields.
            $postedFields = [];
            if ($fieldsForForm) {
                // Manage exception for list of ids and security, because hidden
                // fields are not fully checked.
                $fieldIds = ($params['fields']['id'] ?? []) ?: [];
                if (!empty($fieldIds) && !is_array($fieldIds)) {
                    $fieldIdsJson = json_decode($fieldIds, true);
                    $fieldIds = is_array($fieldIdsJson) ? $fieldIdsJson : [$fieldIds];
                }
                $params['fields']['id'] = array_values(array_unique(array_filter(array_map('intval', $fieldIds))));
                foreach (array_keys($fieldsForForm) as $name) {
                    $params['fields'][$name] ??= null;
                    $postedFields[$name] = $params['fields'][$name];
                }
            }

            /**
             * @fixme There is a warning on php 8 on date and time validator that is not fixed in version 2.25, the last version supporting 7.4.
             * @see \Laminas\Validator\DateStep::convertString() ligne 207: output may be false.
             */
            $errorReporting = error_reporting();
            error_reporting($errorReporting & ~E_WARNING);

            $form->setData($params);
            if ($hasEmail && $form->isValid()) {
                $submitted = $form->getData();
                error_reporting($errorReporting);
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

                // Manage the specific field for multiple ids and convert it
                // into a resource when possible.
                if (empty($postedFields['id'])) {
                    unset($postedFields['id']);
                } elseif (is_array($postedFields['id']) && count($postedFields['id']) === 1 && empty($options['resource'])) {
                    try {
                        $options['resource'] = $this->api->read('resources', ['id' => (int) reset($postedFields['id'])])->getContent();
                        unset($postedFields['id']);
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
                    'o-module-contact:fields' => $postedFields,
                    'o-module-contact:newsletter' => $newsletterOnly
                        ? empty($formOptions['unsubscribe'])
                        : ($newsletterLabel ? $submitted['newsletter'] === 'yes' : null),
                    'o-module-contact:is_spam' => $isSpam,
                    'o-module-contact:to_author' => $isContactAuthor,
                ];
                $response = null;
                if ($isPostStored === null) {
                    $response = $this->apiPlugin->__invoke($form)->create('contact_messages', $data, $fileData);
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
                    $submitted['name'] = $contactMessage->name();
                    $submitted['site_title'] = $contactMessage->site()->title();
                    $submitted['site_url'] = $contactMessage->site()->siteUrl();
                    $submitted['subject'] = $contactMessage->subject()
                        ?: sprintf($translate('[Contact] %s'), $this->mailer->getInstallationTitle());
                    $submitted['message'] = $contactMessage->body();
                    $submitted['ip'] = $contactMessage->ip();
                    $submitted['zip_url'] = $contactMessage->zipUrl();

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

                    /** @see \Common\Mvc\Controller\Plugin\SendEmail */

                    // To set the name of email as empty string and not null
                    // avoid to parse email for name.

                    $sender = $options['sender_email']
                        ? [$options['sender_email'] => (string) $options['sender_name']]
                        : ($siteSetting('contactus_sender_email')
                            ? [$siteSetting('contactus_sender_email') => (string) $siteSetting('contactus_sender_name')]
                            : ($setting('contactus_sender_email')
                                ? [$setting('contactus_sender_email') => (string) $setting('contactus_sender_name')]
                                : null));

                    $notifyRecipients = $options['notify_recipients']
                        ?: $siteSetting('contactus_notify_recipients')
                        ?: $setting('contactus_notify_recipients')
                        ?: [];

                    // Message to author (with copy to administrators if set).
                    if ($isContactAuthor) {
                        $message = new PsrMessage(
                            'Thank you for your message {name}. Check your confirmation mail. The author will receive it soon.', // @translate
                            $submitted['name']
                                ? ['name' => sprintf('%1$s (%2$s)', $submitted['name'], $submitted['from'])]
                                : ['name' => $submitted['from']]
                        );

                        $subject = $options['to_author_subject'] ?: $this->defaultOptions['to_author_subject'];
                        $body = $options['to_author_body'] ?: $this->defaultOptions['to_author_body'];
                        // Avoid issue with bad config.
                        if (strpos($body, '{email}') === false) {
                            $body .= "\n\nFrom {email}";
                        }
                        if (strpos($body, '{message}') === false) {
                            $body .= "\n\n{message}";
                        }
                        $subject = $this->fillMessage($translate($subject), $submitted);
                        $body = $this->fillMessage($translate($body), $submitted);

                        $from = $sendWithUserEmail
                            ? [$submitted['from'] => (string) $submitted['name']]
                            : $sender;
                        $replyTo = $sendWithUserEmail
                            ? null
                            : [$submitted['from'] => (string) $submitted['name']];
                        $to = $options['author_email'] ? [$options['author_email'] => ''] : null;
                        $bcc = $setting('contactus_author_only')
                            ? null
                            : ($notifyRecipients ?: ($to ? null : $setting('administrator_email')) ?: null);

                        $result = $this->sendEmail->__invoke($body, $subject, $to, $from, null, $bcc, $replyTo);
                        if (!$result) {
                            $status = 'error';
                            $message = new PsrMessage(
                                'Sorry, we are not able to send the email to the author.' // @translate
                            );
                        }
                    }

                    // Notification message to administrators.
                    else {
                        $subject = $this->getMailSubject($options)
                            ?: sprintf($translate('[Contact] %s'), $this->mailer->getInstallationTitle());
                        $body = $siteSetting('contactus_notify_body')
                            ?: $translate($this->defaultOptions['notify_body']);
                        $subject= $this->fillMessage($translate(strtr($subject, ['%7B' => '{', '%7D' => '}'])), $submitted);
                        $body = $this->fillMessage($translate(strtr($body, ['%7B' => '{', '%7D' => '}'])), $submitted);

                        // The message to the admin is always from admin to
                        // avoid issue, but with a reply-to.
                        $from = $sender;
                        $to = $notifyRecipients ?: null;
                        $replyTo = [$submitted['from'] => (string) $submitted['name']];

                        $result = $this->sendEmail->__invoke($body, $subject, $to, $from, null, null, $replyTo);
                        // When there is an issue, don't try to send other mail.
                        if (!$result) {
                            $status = 'error';
                            $message = new PsrMessage(
                                'Sorry, the message is recorded, but we are not able to notify the admin at once. You may come back later if you don’t receive answer.' // @translate
                            );
                        }
                        // Send the confirmation message to the visitor.
                        elseif ($options['confirmation_enabled']) {
                            if ($newsletterOnly) {
                                $message = $siteSetting('contactus_confirmation_message_newsletter')
                                    ?: $translate($this->defaultOptions['confirmation_message_newsletter']);
                            } else {
                                $message = $siteSetting('contactus_confirmation_message')
                                    ?: $translate($this->defaultOptions['confirmation_message']);
                            }
                            $message = strtr($message, ['%7B' => '{', '%7D' => '}']);
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

                            if ($newsletterOnly) {
                                $subject = $options['confirmation_subject'] ?: $this->defaultOptions['confirmation_newsletter_subject'];
                                $body = $options['confirmation_body'] ?: $this->defaultOptions['confirmation_newsletter_body'];
                            } else {
                                $subject = $options['confirmation_subject'] ?: $this->defaultOptions['confirmation_subject'];
                                $body = $options['confirmation_body'] ?: $this->defaultOptions['confirmation_body'];
                            }
                            $subject = $this->fillMessage($translate(strtr($subject, ['%7B' => '{', '%7D' => '}'])), $submitted);
                            $body = $this->fillMessage($translate(strtr($body, ['%7B' => '{', '%7D' => '}'])), $submitted);

                            // The message to the visitor is always from admin.
                            $from = $sender;
                            $to = [$submitted['from'] => (string) $submitted['name']];

                            $result = $this->sendEmail->__invoke($body, $subject, $to, $from);
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
                error_reporting($errorReporting);
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
                'fields' => $fieldsForForm,
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

        $args = [
            'heading' => $options['heading'],
            'html' => $options['html'],
            'asButton' => $options['as_button'],
            'form' => $form,
            'fields' => $options['fields'],
            'resource' => $options['resource'],
            'contact' => $isContactAuthor ? 'author' : 'us',
            'status' => $status,
            'message' => $message ? $message->setTranslator($view->translator()) : null,
        ];

        if ($options['as_button']) {
            $plugins = $this->view->getHelperPluginManager();
            $url = $plugins->get('url');
            $form->setAttribute('action', $site
                ? $url('site/contact-us', ['action' => 'send-mail'], true)
                : $url('contact-us', ['action' => 'send-mail'])
            );
            // With a button, the submit is managed by ajax, so return json.
            // Else, the button and dialog are displayed directly.
            if ($isPost) {
                // Only status and message are really needed.
                return $args;
            }
        }

        return $view->partial($template, $args);
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
            $propertyId = $this->easyMeta->propertyId($propertyEmail);
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
    protected function fillMessage(?string $message, array $placeholders): string
    {
        if (empty($message)) {
            return (string) $message;
        }

        // TODO Remove this fix (and in other places) earlier.
        $message = strtr($message, ['%7B' => '{', '%7D' => '}']);

        $plugins = $this->view->getHelperPluginManager();
        $url = $plugins->get('url');
        $site = $this->currentSite();
        $translate = $plugins->get('translate');

        $matches = [];
        preg_match_all('~\{resources::(?<term>[a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+)\}~m', $message, $matches);
        $resourceTerms = array_unique($matches['term'] ?? []);

        // Any field can be a placeholder, except array (except ids).
        // Flatify array to simplify process.
        $fields = $placeholders['fields'] ?? [];
        $placeholders['email'] ??= $placeholders['from'];
        $placeholders += $fields;

        if (!empty($placeholders['id'])) {
            $placeholders['id'] = is_array($placeholders['id']) ? $placeholders['id'] : [$placeholders['id']];
            $idTitles = $this->api->search('items', ['id' => $placeholders['id']], ['initialize' => false, 'returnScalar' => 'title'])->getContent();
            $baseUrlItem = rtrim($url('site/resource-id', ['site-slug' => $site->slug(), 'controller' => 'item', 'id' => '00'], ['force_canonical' => true]), '/0');
            // {resources}: list of urls.
            $placeholders['resources'] = implode(', ', array_map(fn($v) => "$baseUrlItem/$v", array_keys($idTitles)));
            // {resources_ids}: list of ids.
            $placeholders['resources_ids'] = implode(', ', array_keys($idTitles));
            // {resources_urls}: list of urls. Alias of {resource}.
            $placeholders['resources_urls'] = implode(', ', array_map(fn($v) => "$baseUrlItem/$v", array_keys($idTitles)));
            // {resources_url}: single url to all selected resources.
            $placeholders['resources_url'] = $url('site/resource', ['site-slug' => $site->slug(), 'controller' => 'item'], ['query' => ['id' => implode(',', $placeholders['id'])], 'force_canonical' => true]);
            // {resources_url_admin}: single url to all selected resources.
            $placeholders['resources_url_admin'] = $url('admin/default', ['controller' => 'item'], ['query' => ['id' => implode(',', $placeholders['id'])], 'force_canonical' => true]);
            // {resources::property term}: list of titles or identifiers, etc.
            // This process is slower, so fill it only when needed.
            if ($resourceTerms) {
                $resources = $this->api->search('items', ['id' => $placeholders['id']])->getContent();
                $vals = array_fill_keys($resourceTerms, []);
                foreach ($resources as $resource) {
                    foreach ($resourceTerms as $term) {
                        $value = $resource->value($term);
                        if ($value) {
                            $vals[$term][] = (string) $value;
                        }
                    }
                }
                foreach ($vals as $term => $termVals) {
                    $placeholders['resources::' . $term] = implode(', ', $termVals);
                }
            }
            // Html.
            // TODO Manage html mail.
            // {resources_links}: list of links.
            $baseLink = '<a href="' . $baseUrlItem . '/%1$d">%2$s</a>';
            $placeholders['resources_links'] = implode(', ', array_map(fn($k, $v) => sprintf($baseLink, $k, $v ?: $translate('[No title]')), array_keys($idTitles), $idTitles));
        }

        $placeholders = array_filter($placeholders, fn ($v) => !is_array($v));

        $replace = [];
        foreach ($placeholders as $placeholder => $value) {
            $replace['{' . $placeholder . '}'] = $value;
        }

        // Placehoders are the submitted values: from, email, name, site_title,
        // site_url, subject, message, ip, resources.

        $defaultPlaceholders = [
            '{fields}' => '',
            '{zip_url}' => '',
            '{ip}' => (new RemoteAddress())->getIpAddress(),
            '{main_title}' => $this->mailer->getInstallationTitle(),
            '{main_url}' => $url('top', [], ['force_canonical' => true]),
            '{site_title}' => $site->title(),
            '{site_url}' => $site->siteUrl(null, true),
            '{resources}' => '',
            '{resources_ids}' => '',
            '{resources_urls}' => '',
            '{resources_url}' => '',
            '{resources_url_admin}' => '',
            // Html.
            '{resources_links}' => '',
        ];
        foreach ($matches['term'] ?? [] as $term) {
            $defaultPlaceholders['{resource::' . $term . '}'] = '';
        }
        $replace += $defaultPlaceholders;

        // Fill the single resource.
        if (!empty($this->currentOptions['resource'])) {
            $replace['{resource}'] = $this->currentOptions['resource']->siteUrl(null, true);
            $replace['{resource_id}'] = $this->currentOptions['resource']->id();
            $replace['{resource_title}'] = $this->currentOptions['resource']->displayTitle();
            $replace['{resource_url}'] = $replace['{resource}'];
            $replace['{resource_url_admin}'] = $this->currentOptions['resource']->adminUrl(null, true);
            $replace['{resource_link}'] = sprintf('<a href="%1$s">%2$s</a>', $replace['{resource_url}'], $replace['{resource_title}']);
            // TODO Don't use json_decode(json_encode()).
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
            // TODO Clean unused terms.
        }

        if ($fields && strpos($message, '{fields}') !== false) {
            $fieldsArray = [];
            foreach ($fields as $field => $value) {
                if ($value === '' || $value === null || $value === [] || $field === 'id') {
                    continue;
                }
                if (is_array($value)) {
                    // TODO Recursive multiple value for sub-fieldset with multiple values? The use case will be very rare.
                    if (is_array(reset($value))) {
                        $fieldsArray[] = "* $field :\n" . json_encode($value, 2496);
                    } else {
                        $fieldsArray[] = "* $field :\n    *" . implode("\n    *", $value);
                    }
                } else {
                    $fieldsArray[] = "* $field :\n$value";
                }
            }
            $replace['{fields}'] = implode("\n\n", $fieldsArray);
        }

        return strtr($message, $replace);
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
        return strtr((string) $string, ["\r\n" => "\n", "\n\r" => "\n", "\r" => "\n"]);
    }
}
