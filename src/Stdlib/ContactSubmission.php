<?php declare(strict_types=1);

namespace ContactUs\Stdlib;

use Common\Mvc\Controller\Plugin\SendEmail;
use Common\Stdlib\EasyMeta;
use Common\Stdlib\PsrMessage;
use ContactUs\Form\ContactUsForm;
use ContactUs\Form\NewsletterForm;
use ContactUs\Stdlib\ContactMessageMailer;
use Laminas\Form\FormElementManager;
use Laminas\Session\Container;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Manager as ApiManager;
use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Mailer;
use Psr\Container\ContainerInterface;

/**
 * @see \Access\Site\BlockLayout\AccessRequest
 * @see \ContactUs\Site\BlockLayout\ContactUs
 */
class ContactSubmission
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

    /**
     * @var \Laminas\View\Renderer\PhpRenderer
     */
    protected $view;

    /**
     * @var \Omeka\Api\Response|null
     */
    protected $postStored;

    /**
     * @var \Common\Stdlib\PsrMessage|null
     */
    protected $messageSent;

    /**
     * Per-submission state, set by normalizeOptions() and shared by the phases.
     *
     * @var array
     */
    protected $options = [];

    protected $params = [];

    protected $isPost = false;

    protected $user;

    protected $site;

    protected $isContactAuthor = false;

    protected $template;

    protected $sendWithUserEmail = false;

    protected $fieldsForForm = [];

    protected $attachFile = false;

    protected $consentLabel = '';

    protected $unsubscribe = false;

    protected $unsubscribeLabel = '';

    protected $newsletterOnly = false;

    protected $newsletterLabel = '';

    protected $antispam = false;

    protected $isSpam = false;

    protected $status;

    protected $message;

    protected $defaultForm = true;

    protected $question = '';

    protected $answer = '';

    protected $checkAnswer = '';

    protected $form;

    public function __construct(
        Api $api,
        ApiManager $apiManager,
        EasyMeta $easyMeta,
        FormElementManager $formElementManager,
        Mailer $mailer,
        Messenger $messenger,
        SendEmail $sendEmail,
        array $defaultOptions,
        ?ContainerInterface $services = null,
        ?PhpRenderer $view = null,
        $postStored = null,
        $messageSent = null
    ) {
        $this->api = $apiManager;
        $this->apiPlugin = $api;
        $this->easyMeta = $easyMeta;
        $this->formElementManager = $formElementManager;
        $this->mailer = $mailer;
        $this->messenger = $messenger;
        $this->sendEmail = $sendEmail;
        $this->services = $services;
        $this->view = $view;
        $this->postStored = $postStored;
        $this->messageSent = $messageSent;
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
        $early = $this->normalizeOptions($options);
        if ($early !== null) {
            return $early;
        }
        if ($this->isPost) {
            $this->processPost();
        }
        $this->buildForm();
        return $this->renderArgs();
    }

    /**
     * Merge the options with the defaults, resolve the request and the context,
     * normalize the fields and init the submission state. Returns the rendered
     * output when there is nothing to process (author contact without an author
     * email), else null.
     *
     * @return string|array|null
     */
    protected function normalizeOptions(array $options)
    {
        $this->options = $options + $this->defaultOptions;

        $view = $this->view;

        $this->params = $view->params()->fromPost();
        $this->isPost = !empty($this->params);

        $this->template = $this->options['template']
            ?: ($this->options['as_button'] ? self::PARTIAL_NAME_BUTTON : self::PARTIAL_NAME);

        $this->isContactAuthor = $this->options['contact'] === 'author';
        if ($this->isContactAuthor) {
            // Remove useless options.
            $this->options['attach_file'] = false;
            $this->options['consent_label'] = '';
            $this->options['newsletter_label'] = '';
            $this->options['author_email'] = $this->authorEmail($this->options);
            // Early return when there is no author email.
            if (empty($this->options['author_email'])) {
                $args = [
                    'heading' => $this->options['heading'],
                    'html' => $this->options['html'],
                    'asButton' => $this->options['as_button'],
                    'form' => null,
                    'resource' => $this->options['resource'],
                    'contact' => 'author',
                    'status' => 'error',
                    'message' => $this->errorMessage,
                ];
                return $this->isPost
                    // Only status and message are really needed.
                    ? $args
                    : $view->partial($this->template, $args);
            }
        }

        $this->currentOptions = $this->options;

        $this->user = $view->identity();
        $setting = $view->plugin('setting');
        $siteSetting = $view->plugin('siteSetting');
        $translate = $view->plugin('translate');

        $this->site = $this->currentSite();

        $this->sendWithUserEmail = (bool) $setting('contactus_send_with_user_email');

        // Manage list of resource ids automatically, if any. "resource_ids" is
        // used for standard forms and fields for complex forms with multiple
        // specific fields.
        // TODO Manage "resource_ids" in backend, not only in js. But useless: already via fields[id] anyway. So "resource_ids" should be deprecated.

        // TODO Next version: rework the custom fields ("fields") into a proper
        // model. Today they are a raw array (configured through a technical
        // ArrayTextarea) normalized both here and in
        // ContactUsForm::appendFields. Possible evolutions to note for the next
        // version: - a typed Field value object (name, type, label, required,
        // multiple,
        //   value, options) with a single factory parsing the current formats
        //   ("* Label" required syntax, arrays, the "id" list of resource ids),
        //   so the form and this normalization share one source of truth;
        // - a structured, non-technical field editor in the block/site config
        //   feeding that same model, keeping the ArrayTextarea as a fallback;
        // - a field-type registry so a module can add its own field types;
        // - move the whole fields normalization into the buildForm() seam so
        // the
        //   future model plugs in there without touching the flow.

        // The field "id" should be an array.
        // When hidden, the value may or may not be converted.
        if (empty($this->options['fields']['id']) || $this->options['fields']['id'] === '[]') {
            $this->options['fields']['id'] = [];
        } elseif (is_string($this->options['fields']['id'])
            && (
                (substr($this->options['fields']['id'], 0, 1) === '[' && substr($this->options['fields']['id'], -1) === ']')
                || (substr($this->options['fields']['id'], 0, 1) === '{' && substr($this->options['fields']['id'], -1) === '}')
            )
        ) {
            $this->options['fields']['id'] = json_decode($this->options['fields']['id'], true);
        } elseif (!is_array($this->options['fields']['id'])) {
            $this->options['fields']['id'] = [$this->options['fields']['id']];
        }

        // For fields, append the resource early.
        if ($this->options['resource']) {
            $this->options['fields']['id'][] = $this->options['resource']->id();
        }

        // The fields id should be integer and unique.
        $this->options['fields']['id'] = isset($this->options['fields']['id']['value'])
            ? array_values(array_unique(array_filter(array_map('intval', $this->options['fields']['id']['value']))))
            : array_values(array_unique(array_filter(array_map('intval', $this->options['fields']['id']))));

        // The option fields are all specific fields set via the theme.
        // They are added in the form. The list of ids is added automatically.
        // For form, the fields id should be hidden.
        $this->fieldsForForm = $this->options['fields'];
        $this->fieldsForForm['id'] = [
            'type' => 'hidden',
            'value' => $this->fieldsForForm['id'],
        ];

        $this->attachFile = !empty($this->options['attach_file']);
        $this->consentLabel = trim((string) $this->options['consent_label']);
        $this->unsubscribe = !empty($this->options['unsubscribe']);
        $this->unsubscribeLabel = trim((string) $this->options['unsubscribe_label']);
        $this->newsletterOnly = !empty($this->options['newsletter_only']);
        $this->newsletterLabel = trim((string) $this->options['newsletter_label']);

        $this->antispam = empty($this->user)
            && !empty($this->options['antispam'])
            && !empty($this->options['questions']);
        $this->isSpam = false;
        $this->message = null;
        $this->status = null;
        $this->defaultForm = true;

        $this->question = '';
        $this->answer = '';
        $this->checkAnswer = '';

        // Sometime, questions/answers are not converted into array in form.
        // Fix https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/issues/10.
        // This is probably related to an old config that wasn't updated. So,
        // waiting the admin to check an issue in the page and to resave it.
        // TODO Remove this check and associated code during upgrade.
        if ($this->antispam) {
            $this->options['questions'] = $this->checkAntispamOptions($this->options['questions']);
        }

        return null;
    }

    /**
     * Handle a posted submission: spam evaluation, storage and mails.
     */
    protected function processPost(): void
    {
        $view = $this->view;
        $setting = $view->plugin('setting');
        $translate = $view->plugin('translate');

        $spam = $this->evaluateSpam($this->params, $this->options, $this->user, $this->antispam);
        $this->isSpam = $spam['isSpam'];
        $blockSubmission = $spam['blockSubmission'];
        $this->question = $spam['question'];
        $this->answer = $spam['answer'];
        $this->checkAnswer = $spam['checkAnswer'];

        $this->params += ['from' => null, 'name' => null];
        $hasEmail = $this->params['from'] || $this->user;

        // Issue a fresh single-use proof-of-work salt and stamp the load time
        // for the form built here: the spam check just consumed the previous
        // salt, so a form redisplayed with validation errors must carry a new
        // challenge, otherwise the corrected resubmission would fail PoW and be
        // dropped as spam. On success the form is rebuilt (buildForm) with its
        // own salt, so this one is simply superseded.
        $powSalt = '';
        $session = new Container('ContactUs');
        $session->form_loaded_at = time();
        if (empty($this->user) && !$setting('contactus_pow_skip')) {
            $powSalt = bin2hex(random_bytes(16));
            $session->pow_salt = $powSalt;
            $session->pow_issued_at = time();
        }

        $formOptions = [
            'fields' => $this->fieldsForForm,
            'attach_file' => $this->attachFile,
            'consent_label' => $this->consentLabel,
            'newsletter_label' => $this->newsletterLabel,
            'unsubscribe' => $this->unsubscribe,
            'unsubscribe_label' => $this->unsubscribeLabel,
            'question' => $this->question,
            'answer' => $this->answer,
            'check_answer' => $this->checkAnswer,
            'user' => $this->user,
            'contact' => $this->isContactAuthor ? 'author' : 'us',
            'form_display_user_email_hidden' => !empty($this->options['form_display_user_email_hidden']),
            'form_display_user_name_hidden' => !empty($this->options['form_display_user_name_hidden']),
            'recaptcha' => !empty($this->options['recaptcha']),
            'pow_salt' => $powSalt,
        ];
        $this->form = $this->newsletterOnly
            ? $this->getFormNewsletter($formOptions)
            : $this->getFormContactUs($formOptions);

        // Collect the custom fields values. The form is now flat, so read the
        // top-level params, with a fallback to the legacy "fields[...]"
        // structure for old themes. Core fields (from/name/subject/message) are
        // excluded: they are stored on their own message columns.
        $postedFields = $this->collectPostedFields();

        /**
         * @fixme There is a warning on php 8 on date and time validator that is not fixed in version 2.25, the last version supporting 7.4.
         * @see \Laminas\Validator\DateStep::convertString() ligne 207: output may be false.
         */
        $errorReporting = error_reporting();
        error_reporting($errorReporting & ~E_WARNING);

        $this->form->setData($this->params);
        if ($blockSubmission) {
            error_reporting($errorReporting);
            $this->status = 'error';
            $this->message = new PsrMessage(
                'Links (URLs) are not allowed in messages. Please remove any link and resubmit.' // @translate
            );
            $this->messenger->addError($this->message->translate());
            $this->defaultForm = false;
        } elseif ($hasEmail && $this->form->isValid()) {
            $submitted = $this->form->getData();
            error_reporting($errorReporting);

            // Stamp the rate-limit marker now that the form is valid (spam or
            // not), so rapid resubmissions are throttled without punishing a
            // legitimate visitor who is correcting a validation error.
            if (empty($this->user)) {
                $session = new Container('ContactUs');
                $session->last_submit_ip = $this->clientIp();
                $session->last_submit_at = time();
            }

            if ($this->user) {
                $submitted['from'] = $this->user->getEmail();
                // TODO What is the purpose of removing user name only for contact, not newsletter?
                $submitted['name'] = $this->newsletterOnly ? $this->user->getName() : null;
            }

            $fileData = $this->attachFile ? $view->params()->fromFiles() : [];

            // If spam, store the message and return a success message, but
            // don't send email.

            // Status is checked below.
            $this->status = 'success';
            if ($this->newsletterOnly) {
                if ($this->unsubscribe) {
                    $this->message = new PsrMessage(
                        'The unsubscription for {email} is confirmed.', // @translate
                        ['email' => $submitted['from']]
                    );
                } else {
                    $this->message = new PsrMessage(
                        'Thank you for subscribing to our newsletter, {name}.', // @translate
                        ['name' => $submitted['name'] ? sprintf('%s (%s)', $submitted['name'], $submitted['from']) : $submitted['from']]
                    );
                }
            } else {
                if ($this->isContactAuthor) {
                    $this->message = new PsrMessage(
                        'Thank you for your message, {name}. It will be sent to the author as soon as possible.', // @translate
                        ['name' => $submitted['name'] ? sprintf('%s (%s)', $submitted['name'], $submitted['from']) : $submitted['from']]
                    );
                } else {
                    $this->message = new PsrMessage(
                        'Thank you for your message, {name}. We will answer you as soon as possible.', // @translate
                        ['name' => $submitted['name'] ? sprintf('%s (%s)', $submitted['name'], $submitted['from']) : $submitted['from']]
                    );
                }
            }

            // Manage the specific field for multiple ids and convert it
            // into a resource when possible.
            if (empty($postedFields['id'])) {
                unset($postedFields['id']);
            } elseif (is_array($postedFields['id']) && count($postedFields['id']) === 1 && empty($this->options['resource'])) {
                try {
                    $this->options['resource'] = $this->api->read('resources', ['id' => (int) reset($postedFields['id'])])->getContent();
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
                'o:owner' => $this->user,
                'o:email' => $submitted['from'],
                'o:name' => $this->newsletterOnly ? null : $submitted['name'],
                'o:resource' => !empty($this->options['resource']) ? ['o:id' => $this->options['resource']->id()] : null,
                'o:site' => ['o:id' => $this->site->id()],
                'o-module-contact:subject' => $this->newsletterOnly
                    ? $translate($formOptions['unsubscribe']
                        ? 'Unsubscribe newsletter' // @translate
                        : 'Subscribe newsletter') // @translate
                    : $submitted['subject'],
                'o-module-contact:body' => $this->newsletterOnly
                    ? $translate($formOptions['unsubscribe'] ? 'Unsubscribe newsletter' : 'Subscribe newsletter')
                    : $submitted['message'],
                'o-module-contact:fields' => $postedFields,
                'o-module-contact:newsletter' => $this->newsletterOnly
                    ? empty($formOptions['unsubscribe'])
                    : ($this->newsletterLabel ? $submitted['newsletter'] === 'yes' : null),
                'o-module-contact:is_spam' => $this->isSpam,
                'o-module-contact:to_author' => $this->isContactAuthor,
            ];
            $response = null;
            if ($this->postStored === null) {
                $response = $this->apiPlugin->__invoke($this->form)->create('contact_messages', $data, $fileData);
                $isFirst = true;
                $this->postStored = !empty($response);
            } else {
                $isFirst = false;
                $response = $this->postStored;
            }

            // The message is already sent. Just keep the response.
            if (!$isFirst) {
                $this->message = $this->messageSent;
            } elseif (!$response) {
                $this->status = 'error';
                $this->message = $this->formErrorMessage($this->form);
                $this->defaultForm = false;
            }

            // Send non-spam message to administrators and author.
            elseif (!$this->isSpam) {
                $sent = $this->dispatchMessages(
                    $response->getContent(),
                    $submitted,
                    $this->options,
                    $this->isContactAuthor,
                    $this->sendWithUserEmail,
                    $this->newsletterLabel,
                    $this->newsletterOnly
                );
                // Keep the earlier "success"/"Thank you" when the dispatch has
                // nothing to override with (notification sent, no
                // confirmation).
                if ($sent['status'] !== null) {
                    $this->status = $sent['status'];
                }
                if ($sent['message'] !== null) {
                    $this->message = $sent['message'];
                }
            }
        } else {
            error_reporting($errorReporting);
            $this->status = 'error';
            $this->message = $this->formErrorMessage($this->form);
            $this->defaultForm = false;
        }
    }

    /**
     * Build the form to display. This is the seam for the future fields model
     * evolution (see the note in normalizeOptions()).
     */
    protected function buildForm(): void
    {
        $setting = $this->view->plugin('setting');

        if ($this->defaultForm) {
            $session = new Container('ContactUs');
            // Stamp the form generation time so the submit handler can reject
            // too-fast (bot) and too-old (expired) submissions.
            $session->form_loaded_at = time();
            // Generate a proof-of-work salt.
            // Limitation: the salt lives in a single session key, so with
            // several contact blocks on one page, only the last-rendered can be
            // submitted.
            // TODO Fix salt check on multi-contact forms on the same page (very rare).
            $powSalt = '';
            if (!$setting('contactus_pow_skip') && empty($this->user)) {
                $powSalt = bin2hex(random_bytes(16));
                $session->pow_salt = $powSalt;
                $session->pow_issued_at = time();
            }
            if ($this->antispam) {
                $this->question = array_rand($this->options['questions']);
                $this->answer = $this->options['questions'][$this->question];
                $session->question = $this->question;
            } else {
                $this->question = '';
                $this->answer = '';
                $this->checkAnswer = '';
            }
            $formOptions = [
                'fields' => $this->fieldsForForm,
                'attach_file' => $this->attachFile,
                'consent_label' => $this->consentLabel,
                'newsletter_label' => $this->newsletterLabel,
                'unsubscribe' => $this->unsubscribe,
                'unsubscribe_label' => $this->unsubscribeLabel,
                'question' => $this->question,
                'answer' => $this->answer,
                'check_answer' => $this->checkAnswer,
                'user' => $this->user,
                'contact' => $this->isContactAuthor ? 'author' : 'us',
                'form_display_user_email_hidden' => !empty($this->options['form_display_user_email_hidden']),
                'form_display_user_name_hidden' => !empty($this->options['form_display_user_name_hidden']),
                'recaptcha' => !empty($this->options['recaptcha']),
                'pow_salt' => $powSalt,
            ];
            $this->form = $this->newsletterOnly
                ? $this->getFormNewsletter($formOptions)
                : $this->getFormContactUs($formOptions);
        }

        if ($this->user) {
            $this->form->get('from')
                ->setValue($this->user->getEmail())
                ->setAttribute('disabled', 'disabled');
            if (!$this->newsletterOnly) {
                $this->form->get('name')
                    ->setValue($this->user->getName())
                    ->setAttribute('disabled', 'disabled');
            }
        }

        if ($this->options['resource']) {
            $this->answer = 'About resource %s (%s).'; // @translate
            $this->form->get('message')
                ->setAttribute('value', sprintf($this->answer, $this->options['resource']->displayTitle(), $this->options['resource']->siteUrl(null, true)) . "\n\n");
        }

        $this->form->init();
        $this->form->setName($this->newsletterOnly ? 'newsletter' : 'contact-us');
    }

    /**
     * Assemble the partial arguments and render, or return the data for an ajax
     * (button) post.
     *
     * @return string|array
     */
    protected function renderArgs()
    {
        $view = $this->view;

        $this->messageSent = $this->message;

        $args = [
            'heading' => $this->options['heading'],
            'html' => $this->options['html'],
            'asButton' => $this->options['as_button'],
            'form' => $this->form,
            'fields' => $this->options['fields'],
            'resource' => $this->options['resource'],
            'contact' => $this->isContactAuthor ? 'author' : 'us',
            'status' => $this->status,
            'message' => $this->message ? $this->message->setTranslator($view->translator()) : null,
        ];

        if ($this->options['as_button']) {
            $plugins = $this->view->getHelperPluginManager();
            $url = $plugins->get('url');
            $this->form->setAttribute('action', $this->site
                ? $url('site/contact-us', ['action' => 'send-mail'], true)
                : $url('contact-us', ['action' => 'send-mail'])
            );
            // With a button, the submit is managed by ajax, so return json.
            // Else, the button and dialog are displayed directly.
            if ($this->isPost) {
                // Only status and message are really needed.
                return $args;
            }
        }

        return $view->partial($this->template, $args);
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
        $view = $this->view;
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
                // Thresholds aligned with SpamGuard, so the local fallback
                // behaves like the engine when it is not active.
                'minDelay' => (int) ($setting('spamguard_min_delay') ?? 1),
                'rateLimitSeconds' => (int) ($setting('spamguard_rate_limit_seconds') ?? 10),
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

        // Consume the single-use proof-of-work salt. The rate-limit marker is
        // stamped later, only for a valid submission (see processPost), so a
        // visitor who first tripped a validation error is not throttled on the
        // corrected resubmission.
        unset($session->pow_salt, $session->pow_issued_at);

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
        $view = $this->view;
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

        $view = $this->view;

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
    /**
     * Register the form errors in the messenger and return a summary message.
     */
    protected function formErrorMessage($form): PsrMessage
    {
        $errorMessages = [];
        foreach ($form->getMessages() as $formKeyMessages) {
            foreach ($formKeyMessages as $formKeyMessage) {
                $errorMessages[] = is_array($formKeyMessage) ? reset($formKeyMessage) : $formKeyMessage;
            }
        }
        // TODO Map errors key with form (keep original keys of the form).
        $this->messenger->addFormErrors($form);
        return count($errorMessages)
            ? new PsrMessage(
                'There is an error: {errors}', // @translate
                ['errors' => implode(", \n", $errorMessages)]
            )
            : new PsrMessage(
                'There is an error.' // @translate
            );
    }

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

        return $this->view->plugin('prepareMessage')->fillMessage($message, $placeholders, $context);
    }

    /**
     * Collect the values of the custom fields from the posted params.
     *
     * The form is flat since version 3.4.32, so the values are read at the top
     * level, with a fallback to the legacy "fields[…]" structure used by the
     * themes that were not upgraded.
     */
    protected function collectPostedFields(): array
    {
        if (!$this->fieldsForForm) {
            return [];
        }

        $partition = \ContactUs\Form\ContactUsForm::partitionFields($this->fieldsForForm);

        // Manage exception for list of ids and security, because hidden fields
        // are not fully checked.
        $fieldIds = $this->params['id'] ?? $this->params['fields']['id'] ?? [];
        if (!empty($fieldIds) && !is_array($fieldIds)) {
            $fieldIdsJson = json_decode($fieldIds, true);
            $fieldIds = is_array($fieldIdsJson) ? $fieldIdsJson : [$fieldIds];
        }
        $fieldIds = array_values(array_unique(array_filter(array_map('intval', $fieldIds))));

        $postedFields = [];
        foreach (array_keys($partition['custom']) as $name) {
            if ($name === 'id') {
                $postedFields['id'] = $fieldIds;
                continue;
            }
            $postedFields[$name] = $this->params[$name]
                ?? $this->params['fields'][$name]
                ?? null;
        }

        return $postedFields;
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

        $view = $this->view;
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

    public function postStored()
    {
        return $this->postStored;
    }

    public function messageSent()
    {
        return $this->messageSent;
    }
}
