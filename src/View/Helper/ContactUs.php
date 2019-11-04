<?php
namespace ContactUs\View\Helper;

use ContactUs\Form\ContactUsForm;
use Omeka\Stdlib\Mailer;
use Omeka\Stdlib\Message;
use Zend\Form\FormElementManager\FormElementManagerV3Polyfill as FormElementManager;
use Zend\Http\PhpEnvironment\RemoteAddress;
use Zend\Session\Container;
use Zend\View\Helper\AbstractHelper;

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
     * @var Mailer $mailer
     */
    protected $mailer;

    public function __construct(
        FormElementManager $formElementManager,
        array $defaultOptions,
        Mailer $mailer
    ) {
        $this->formElementManager = $formElementManager;
        $this->defaultOptions = $defaultOptions;
        $this->mailer = $mailer;
    }

    /**
     * Display the contact us form.
     *
     * @param array $options
     * @return string Html string.
     */
    public function __invoke($options = [])
    {
        $options += $this->defaultOptions + ['template' => null, 'resource' => null];

        $view = $this->getView();

        $isAuthenticated = (bool) $view->identity();
        $translate = $view->plugin('translate');

        $antispam = !$isAuthenticated && !empty($options['antispam']) && !empty($options['questions']);
        $isSpam = false;
        $message = null;
        $status = null;
        $formOptions = [];
        $defaultForm = true;

        $params = $view->params()->fromPost();
        if ($params) {
            if ($antispam) {
                $isSpam = $this->checkSpam($options, $params);
                if (!$isSpam) {
                    $question = (new Container('ContactUs'))->question;
                    $answer = isset($params['answer']) ? $params['answer'] : false;
                    $checkAnswer = $options['questions'][$question];
                    $formOptions = [
                        'question' => $question,
                        'answer' => $answer,
                        'checkAnswer' => $checkAnswer,
                    ];
                }
            }

            /** @var \ContactUs\Form\ContactUsForm $form */
            $form = $this->formElementManager->get(ContactUsForm::class, $formOptions);
            $form->setData($params);
            if ($form->isValid()) {
                $args = $form->getData();
                $status = 'success';
                // If spam, return a success message, but don't send email.
                $message = new Message(
                    $translate('Thank you for your message %s. We will answer you soon.'), // @translate
                    $args['name']
                        ? sprintf('%s (%s)', $args['name'], $args['from'])
                        : sprintf('(%s)', $args['from'])
                );
                // Send the message to the administrator of the site.
                if (!$isSpam) {
                    // Add some keys to use as placeholders.
                    $site = $this->currentSite();
                    $args['email'] = $args['from'];
                    $args['site_title'] = $site->title();
                    $args['site_url'] = $site->siteUrl();

                    $mail = [];
                    $mail['from'] = $args['from'];
                    $mail['fromName'] = $args['name'] ?: null;
                    // Keep compatibility with old versions.
                    $to = $options['notify_recipients'] ?: [];
                    if (!$to) {
                        $to = $view->siteSetting('contactus_notify_recipients');
                        if (!$to) {
                            $to = $view->setting('contactus_notify_recipients');
                            if (!$to) {
                                $owner = $site->owner();
                                $to = $owner ? [$owner->email()] : [$view->setting('administrator_email')];
                            }
                        }
                    }
                    $mail['to'] = $to;
                    $mail['subject'] = sprintf($translate('[Contact] %s'), $this->mailer->getInstallationTitle());
                    $body = <<<TXT
A user has contacted you.

email: {email}
name: {name}
ip: {ip}
object: {object}
message:

{message}
TXT;
                    $body = $translate($body);
                    $mail['body'] = $this->fillMessage($body, $args);

                    $result = $this->sendEmail($mail);
                    if (!$result) {
                        $status = 'error';
                        $message = new Message(
                            $translate('Sorry, we are not able to send email to notify the admin. Come back later.') // @translate
                        );
                    }
                    // Send the confirmation message to the visitor.
                    elseif ($options['confirmation_enabled']) {
                        $message = new Message(
                            $translate('Thank you for your message %s. Check your confirmation mail. We will answer you soon.'), // @translate
                            $args['name']
                                ? sprintf('%s (%s)', $args['name'], $args['from'])
                                : sprintf('(%s)', $args['from'])
                        );
                        $mail = [];
                        $mail['from'] = $owner ? $owner->email() : $view->setting('administrator_email');
                        $mail['fromName'] = $owner ? $owner->name() : null;
                        $mail['to'] = $args['from'];
                        $mail['toName'] = $args['name'] ?: null;
                        $subject = $options['confirmation_subject'] ?: $this->defaultSettings['confirmation_subject'];
                        $mail['subject'] = $this->fillMessage($translate($subject), $args);
                        $body = $options['confirmation_body'] ?: $this->defaultSettings['confirmation_body'];
                        $mail['body'] = $this->fillMessage($translate($body), $args);

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
                    $translate('There is an error.')
                );
                $defaultForm = false;
            }
        }

        if ($defaultForm) {
            if ($antispam) {
                $question = array_rand($options['questions']);
                $answer = $options['questions'][$question];
                $formOptions = [
                    'question' => $question,
                    'checkAnswer' => $answer,
                ];
                $session = new Container('ContactUs');
                $session->question = $question;
            }
            $form = $this->formElementManager->get(ContactUsForm::class, $formOptions);
        }

        $template = $options['template'] ?: self::PARTIAL_NAME;
        unset($options['template']);
        return $view->partial(
            $template,
            [
                'heading' => $options['heading'],
                'form' => $form,
                'message' => $message,
                'status' => $status,
                'options' => $options,
            ]
        );
    }

    /**
     * Check if a post is a spam.
     *
     * @param array $options
     * @param array $params Post data.
     * @return boolean
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
        ];
        $params += $defaultParams;

        $mailer = $this->mailer;
        $message = $mailer->createMessage();
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
            $mailer->send($message);
        } catch (\Exception $e) {
            $view = $this->getView();
            $view->logger()->err(new Message(
                'Error when sending email. Arguments:\n%s', // @translate
                json_encode($params, 448)
            ));
            return false;
        }

        return true;
    }

    /**
     * @return \Omeka\Api\Representation\SiteRepresentation
     */
    protected function currentSite()
    {
        return $this->getView()
            ->getHelperPluginManager()
            ->get('Zend\View\Helper\ViewModel')
            ->getRoot()
            ->getVariable('site');
    }
}
