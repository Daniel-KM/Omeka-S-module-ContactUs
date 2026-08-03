<?php declare(strict_types=1);

namespace ContactUs\View\Helper;

use Common\Mvc\Controller\Plugin\SendEmail;
use Common\Stdlib\EasyMeta;
use Common\Stdlib\PsrMessage;
use ContactUs\Form\ContactUsForm;
use ContactUs\Form\NewsletterForm;
use ContactUs\Stdlib\ContactMessageMailer;
use Laminas\Form\FormElementManager;
use Laminas\Session\Container;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Manager as ApiManager;
use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Mailer;
use Psr\Container\ContainerInterface;

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

    /**
     * @var ContainerInterface|null
     */
    protected $services;

    public function __construct(
        Api $api,
        ApiManager $apiManager,
        EasyMeta $easyMeta,
        FormElementManager $formElementManager,
        Mailer $mailer,
        Messenger $messenger,
        SendEmail $sendEmail,
        array $defaultOptions,
        ?ContainerInterface $services = null
    ) {
        $this->api = $apiManager;
        $this->apiPlugin = $api;
        $this->easyMeta = $easyMeta;
        $this->formElementManager = $formElementManager;
        $this->mailer = $mailer;
        $this->messenger = $messenger;
        $this->sendEmail = $sendEmail;
        $this->services = $services;
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
        $spamReasons = [];
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
            $spam = $this->evaluateSpam($params, $options, $user, $antispam);
            $isSpam = $spam['isSpam'];
            $blockSubmission = $spam['blockSubmission'];
            $question = $spam['question'];
            $answer = $spam['answer'];
            $checkAnswer = $spam['checkAnswer'];

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
            if ($blockSubmission) {
                error_reporting($errorReporting);
                $status = 'error';
                $message = new PsrMessage(
                    'Links (URLs) are not allowed in messages. Please remove any link and resubmit.' // @translate
                );
                $this->messenger->addError($message->translate());
                $defaultForm = false;
            } elseif ($hasEmail && $form->isValid()) {
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
                    } catch (\Throwable $e) {
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
                    $sent = $this->dispatchMessages(
                        $response->getContent(),
                        $submitted,
                        $options,
                        $isContactAuthor,
                        $sendWithUserEmail,
                        $newsletterLabel,
                        $newsletterOnly
                    );
                    $status = $sent['status'];
                    $message = $sent['message'];
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
            $session = new Container('ContactUs');
            // Stamp the form generation time so the submit handler can reject
            // too-fast (bot) and too-old (expired) submissions.
            $session->form_loaded_at = time();
            // Generate a proof-of-work salt. The client browser must find a
            // nonce such that sha256(salt:nonce) starts with 4 hex zeros
            // (~65k hashes, about one second in a modern browser).
            $powSalt = '';
            if (!$setting('contactus_pow_skip') && empty($user)) {
                $powSalt = bin2hex(random_bytes(16));
                $session->pow_salt = $powSalt;
                $session->pow_issued_at = time();
            }
            if ($antispam) {
                $question = array_rand($options['questions']);
                $answer = $options['questions'][$question];
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
                'pow_salt' => $powSalt,
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

    /**
     * Run the spam checks on a submission and return the outcome.
     *
     * Also consumes the single-use proof-of-work salt and stamps the rate-limit
     * marker in the session, and logs a detected spam.
     *
     * @return array{isSpam: bool, blockSubmission: bool, question: string,
     *   answer: mixed, checkAnswer: string}
     */
    protected function evaluateSpam(array $params, array $options, $user, bool $antispam): array
    {
        $view = $this->getView();
        $setting = $view->plugin('setting');
        $siteSetting = $view->plugin('siteSetting');

        $spamReasons = [];
        $question = '';
        $answer = '';
        $checkAnswer = '';

        $session = new Container('ContactUs');
        $currentIp = $this->clientIp();

        // Snapshot the session state issued when the form was rendered,
        // before any mutation below, so both the local checks and the
        // delegated SpamGuard check see the values sent with the form.
        $loadedAt = (int) ($session->form_loaded_at ?? 0);
        $powSalt = (string) ($session->pow_salt ?? '');
        $powIssuedAt = (int) ($session->pow_issued_at ?? 0);
        $prevSubmitAt = (int) ($session->last_submit_at ?? 0);
        $prevSubmitIp = (string) ($session->last_submit_ip ?? '');

        // Resolve the spam checker (SpamGuard adapter when the module is
        // active, else the local fallback) and collect the matched reasons.
        // A single interface avoids the earlier double, conflicting run.
        if (empty($user)) {
            $spamContext = [
                'ip' => $currentIp,
                'userAgent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'email' => $params['from'] ?? '',
                'subject' => $params['subject'] ?? '',
                'body' => $params['message'] ?? '',
                'formLoadedAt' => $loadedAt,
                'honeypot' => $params['contact_website'] ?? '',
                'powSalt' => $powSalt,
                'powNonce' => $params['pow_nonce'] ?? '',
                'powIssuedAt' => $powIssuedAt,
                'prevSubmitAt' => $prevSubmitAt,
                'prevSubmitIp' => $prevSubmitIp,
                'checkDnsMx' => (bool) $setting('contactus_check_dns_mx'),
                'powSkip' => (bool) $setting('contactus_pow_skip'),
            ];
            $spamChecker = $this->services
                ? $this->services->get('ContactUs\SpamChecker')
                : new \ContactUs\Spam\LocalSpamChecker();
            $reasons = $spamChecker->check($spamContext);
            if ($reasons) {
                $spamReasons = array_values(array_unique(array_merge($spamReasons, $reasons)));
            }
        }

        // ContactUs-specific checks, always applied (not covered by
        // SpamGuard).

        // Too slow: the rendered form has expired.
        if (empty($user) && $loadedAt && (time() - $loadedAt) > 3600) {
            $spamReasons[] = 'tooSlow';
        }

        // Strict rejection of any URL in subject or message when the site
        // setting is enabled. Matches http(s)://, protocol-relative // and
        // bare www. hosts.
        if (empty($user) && $siteSetting('contactus_block_urls')) {
            $candidate = (string) ($params['subject'] ?? '') . "\n" . (string) ($params['message'] ?? '');
            if ($candidate !== '' && preg_match('~(?:https?:)?//[a-z0-9]|\bwww\.[a-z0-9]~i', $candidate)) {
                $spamReasons[] = 'url';
            }
        }

        // Question/answer captcha.
        if ($antispam) {
            $question = (new Container('ContactUs'))->question;
            if ($this->checkSpam($options, $params)) {
                $spamReasons[] = 'captcha';
            } else {
                $answer = $params['answer'] ?? false;
                $checkAnswer = $options['questions'][$question] ?? '';
            }
        }

        // Consume the single-use proof-of-work salt and stamp the new
        // rate-limit marker for the next submission, unless this one was
        // rate-limited (keep the earlier marker to keep the window closed).
        unset($session->pow_salt, $session->pow_issued_at);
        if (empty($user) && !in_array('rateLimit', $spamReasons, true)) {
            $session->last_submit_ip = $currentIp;
            $session->last_submit_at = time();
        }

        $isSpam = !empty($spamReasons);
        // Hard reject when an URL was found and the strict block is on. The
        // message is not stored and the visitor sees an explicit error.
        $blockSubmission = in_array('url', $spamReasons, true)
            && $siteSetting('contactus_block_urls');
        if ($isSpam && $this->services) {
            $this->services->get('Omeka\Logger')->notice(
                '[ContactUs] Spam detected: reasons={reasons}; ip={ip}; email={email}', // @translate
                [
                    'reasons' => implode(',', $spamReasons),
                    'ip' => $this->clientIp(),
                    'email' => (string) ($params['from'] ?? ''),
                ]
            );
        }

        return [
            'isSpam' => $isSpam,
            'blockSubmission' => $blockSubmission,
            'question' => $question,
            'answer' => $answer,
            'checkAnswer' => $checkAnswer,
        ];
    }

    /**
     * Send the notification, author and confirmation mails for a stored,
     * non-spam contact message and return the resulting status and message.
     *
     * @return array{status: ?string, message: ?\Common\Stdlib\PsrMessage}
     */
    protected function dispatchMessages(
        \ContactUs\Api\Representation\MessageRepresentation $contactMessage,
        array $submitted,
        array $options,
        bool $isContactAuthor,
        bool $sendWithUserEmail,
        string $newsletterLabel,
        bool $newsletterOnly
    ): array {
        $view = $this->getView();
        $setting = $view->plugin('setting');
        $siteSetting = $view->plugin('siteSetting');
        $translate = $view->plugin('translate');

        $status = null;
        $message = null;

        // Use contact message and not form, because it is filtered.
        // Add some keys for placeholders too.
        /** @var \ContactUs\Api\Representation\MessageRepresentation $contactMessage */
        $submitted['from'] = $contactMessage->email();
        $submitted['name'] = $contactMessage->name();
        $submitted['site_title'] = $contactMessage->site()->title();
        $submitted['site_url'] = $contactMessage->site()->siteUrl(null, true);
        $submitted['subject'] = $contactMessage->subject()
            ?: (new PsrMessage(
                '[Contact] {main_title}', // @translate
                ['main_title' => $this->mailer->getInstallationTitle()]
            ))->translate();
        $submitted['message'] = $contactMessage->body();
        $submitted['ip'] = $contactMessage->ip();
        $submitted['zip_url'] = $contactMessage->zipUrl();

        if ($newsletterLabel) {
            $submitted['newsletter'] = (new PsrMessage(
                'newsletter: {answer}', // @translate
                ['answer' => $contactMessage->newsletter()
                    ? $translate('yes') // @translate
                    : $translate('no')] // @translate
            ))->translate() . "\n";
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

        $mailer = new ContactMessageMailer($this->sendEmail);

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

            $to = $options['author_email'] ? [$options['author_email'] => ''] : null;
            $bcc = $setting('contactus_author_only')
                ? null
                : ($notifyRecipients ?: ($to ? null : $setting('administrator_email')) ?: null);

            $result = $mailer->toAuthor($subject, $body, (string) $submitted['from'], (string) $submitted['name'], $to, $sender, $bcc, $sendWithUserEmail);
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
                ?: (new PsrMessage(
                    '[Contact] {main_title}', // @translate
                    ['main_title' => $this->mailer->getInstallationTitle()]
                ))->translate();
            $body = $siteSetting('contactus_notify_body')
                ?: $translate($this->defaultOptions['notify_body']);
            $subject= $this->fillMessage($translate(strtr($subject, ['%7B' => '{', '%7D' => '}'])), $submitted);
            $body = $this->fillMessage($translate(strtr($body, ['%7B' => '{', '%7D' => '}'])), $submitted);

            $to = $notifyRecipients ?: null;

            $result = $mailer->notifyAdmins($subject, $body, (string) $submitted['from'], (string) $submitted['name'], $to, $sender);
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

                // Reply-to is the configured support address, else
                // the administrator, so the visitor can answer a
                // monitored mailbox (never a no-reply).
                $replyToEmail = $setting('contactus_reply_to_email') ?: $setting('administrator_email');
                $replyTo = $replyToEmail ? [$replyToEmail => ''] : null;

                $result = $mailer->confirmToVisitor($subject, $body, (string) $submitted['from'], (string) $submitted['name'], $sender, $replyTo);
                if (!$result) {
                    $status = 'error';
                    $message = new PsrMessage(
                        'Sorry, we are not able to send the confirmation email.' // @translate
                    );
                }
            }
        }

        return ['status' => $status, 'message' => $message];
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
        if (empty($question)
            || !isset($options['questions'][$question])
            || empty($params['check'])
            || !is_string($params['check'])
        ) {
            return true;
        }
        return !hash_equals(substr(md5($question), 0, 16), $params['check']);
    }

    /**
     * Fill a message with placeholders (moustache style).
     */
    protected function fillMessage(?string $message, array $placeholders): string
    {
        if (empty($message)) {
            return (string) $message;
        }

        // Compute the ContactUs-specific placeholders, then delegate the common
        // work (common placeholders, single and multiple resources,
        // interpolation) to the shared "prepareMessage" view helper.
        $fields = $placeholders['fields'] ?? [];
        $placeholders['email'] ??= $placeholders['from'] ?? null;
        $placeholders['zip_url'] ??= '';

        // {fields}: formatted list of the submitted custom fields.
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
            $placeholders['fields'] = implode("\n\n", $fieldsArray);
        } else {
            $placeholders['fields'] = '';
        }

        // Each scalar field is also a placeholder on its own.
        $placeholders += array_filter($fields, fn ($v) => !is_array($v));

        // The resource ids for the multiple-resources placeholders come from
        // the "id" key of the submitted fields, not from a top-level "id".
        $context = [
            'site' => $this->currentSite(),
            'resource' => $this->currentOptions['resource'] ?? null,
            'resource_ids' => $fields['id'] ?? null,
        ];

        return $this->getView()->plugin('prepareMessage')->fillMessage($message, $placeholders, $context);
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
        $default = (new PsrMessage(
            '[Contact] {main_title}', // @translate
            ['main_title' => $this->mailer->getInstallationTitle()]
        ))->translate();

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

    /**
     * Resolve the real client IP when the site sits behind a reverse proxy.
     *
     * Laminas RemoteAddress only reads REMOTE_ADDR, which is the proxy IP.
     * Mirror the logic of MessageAdapter::getClientIp and prefer the first hop
     * of X-Forwarded-For, then X-Real-IP, then REMOTE_ADDR. The proxy setup is
     * assumed to strip spoofed upstream headers.
     */
    protected function clientIp(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $first = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])
            && filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP)
        ) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}
