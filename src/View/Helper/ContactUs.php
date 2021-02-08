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
    const PARTIAL_NAME = 'common/helper/contact-us';

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

    public function __construct(
        FormElementManager $formElementManager,
        array $defaultOptions,
        Mailer $mailer,
        Api $api
    ) {
        $this->formElementManager = $formElementManager;
        $this->defaultOptions = $defaultOptions;
        $this->mailer = $mailer;
        $this->api = $api;
    }

    /**
     * Display the contact us form.
     *
     * @param array $options
     * @return string Html string.
     */
    public function __invoke($options = [])
    {
        $options += $this->defaultOptions + [
            'template' => null,
            'resource' => null,
            'heading' => null,
            'html' => null,
            'attach_file' => null,
            'newsletter_label' => null,
        ];

        $view = $this->getView();

        $user = $view->identity();
        $translate = $view->plugin('translate');

        $attachFile = !empty($options['attach_file']);
        $newsletterLabel = trim((string) $options['newsletter_label']);

        $antispam = empty($user)
            && !empty($options['antispam']) && !empty($options['questions']);
        $isSpam = false;
        $message = null;
        $status = null;
        $defaultForm = true;

        $question = '';
        $answer = '';
        $checkAnswer = '';

        // Sometime, questions/answers are not converted into array in form.
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
                'newsletter_label' => $newsletterLabel,
                'question' => $question,
                'answer' => $answer,
                'check_answer' => $checkAnswer,
                'user' => $user,
            ]);
            $form
                ->setAttachFile($attachFile)
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

                $status = 'success';
                // If spam, return a success message, but don't send email.
                $message = new Message(
                    $translate('Thank you for your message %s. We will answer you soon.'), // @translate
                    $submitted['name']
                        ? sprintf('%s (%s)', $submitted['name'], $submitted['from'])
                        : sprintf('(%s)', $submitted['from'])
                );

                $site = $this->currentSite();

                // Store contact message. Security checks are done in adapter.
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
                // Send non-spam message to site administrator.
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

                    $mail = [];
                    $mail['from'] = $contactMessage->email();
                    $mail['fromName'] = $contactMessage->name();
                    // Keep compatibility with old versions.
                    $mail['to'] = $this->getNotifyRecipients($options);
                    $mail['subject'] = $this->getMailSubject($options)
                        ?: sprintf($translate('[Contact] %s'), $this->mailer->getInstallationTitle());
                    $body = <<<TXT
A user has contacted you.

email: {email}
name: {name}
ip: {ip}

{newsletter}
subject: {subject}
message:

{message}
TXT;
                    $body = $translate($body);
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
                        $mail['from'] = reset($notifyRecipients);
                        $mail['to'] = $submitted['from'];
                        $mail['toName'] = $submitted['name'] ?: null;
                        $subject = $options['confirmation_subject'] ?: $this->defaultSettings['confirmation_subject'];
                        $mail['subject'] = $this->fillMessage($translate($subject), $submitted);
                        $body = $options['confirmation_body'] ?: $this->defaultSettings['confirmation_body'];
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
                'newsletter_label' => $newsletterLabel,
                'question' => $question,
                'answer' => $answer,
                'check_answer' => $checkAnswer,
                'user' => $user,
            ]);
            $form
                ->setAttachFile($attachFile)
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

        $template = $options['template'] ?: self::PARTIAL_NAME;
        return $view->partial(
            $template,
            [
                'heading' => $options['heading'],
                'html' => $options['html'],
                'form' => $form,
                'message' => $message,
                'status' => $status,
                'resource' => $options['resource'],
            ]
        );
    }

    /**
     * Check if a post is a spam.
     *
     * @param array $options
     * @param array $params Post data.
     * @return bool
     */
    protected function checkSpam(array $options, array $params)
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
     *
     * @param string $message
     * @param array $placeholders
     * @return string
     */
    protected function fillMessage($message, array $placeholders)
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

    /**
     * @param array $options
     * @return array
     */
    protected function getNotifyRecipients(array $options)
    {
        $view = $this->getView();
        $list = $options['notify_recipients'] ?: [];
        if (!$list) {
            $list = $view->siteSetting('contactus_notify_recipients');
            if (!$list) {
                $list = $view->setting('contactus_notify_recipients');
                if (!$list) {
                    $site = $this->currentSite();
                    $owner = $site->owner();
                    $list = $owner ? [$owner->email()] : [$view->setting('administrator_email')];
                }
            }
        }
        return $list;
    }

    /**
     * Send an email.
     *
     * @param array $params The params are already checked (from, to, subject,
     * body).
     * @return bool
     */
    protected function sendEmail(array $params)
    {
        $defaultParams = [
            'fromName' => null,
            'toName' => null,
            'subject' => sprintf($this->getView()->translate('[Contact] %s'), $this->mailer->getInstallationTitle()),
            'body' => null,
        ];
        $params += $defaultParams;

        $message = $this->mailer->createMessage();
        $message
            ->setSubject($params['subject'])
            ->setBody($params['body']);
        $to = is_array($params['to']) ? $params['to'] : [$params['to']];
        foreach ($to as $t) {
            $message->addTo($t);
        }
        if ($params['from']) {
            $message
                ->setFrom($params['from'], $params['fromName']);
        }

        try {
            $this->mailer->send($message);
            return true;
        } catch (\Exception $e) {
            $view = $this->getView();
            $view->logger()->err(new Message(
                'Error when sending email. Arguments:\n%s', // @translate
                json_encode($params, 448)
            ));
            return false;
        }
    }

    /**
     * @return \Omeka\Api\Representation\SiteRepresentation
     */
    protected function currentSite()
    {
        $view = $this->getView();
        return isset($view->site)
            ? $view->site
            : $view->getHelperPluginManager()->get('Laminas\View\Helper\ViewModel')->getRoot()->getVariable('site');
    }

    protected function getMailSubject(array $options = [])
    {
        return empty($options['subject'])
            ? sprintf($this->getView()->translate('[Contact] %s'), $this->mailer->getInstallationTitle())
            : $options['subject'];
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
