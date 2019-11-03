<?php
namespace ContactUs\Site\BlockLayout;

use ContactUs\Form\ContactUsBlockForm;
use ContactUs\Form\ContactUsForm;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Stdlib\ErrorStore;
use Omeka\Stdlib\Mailer;
use Omeka\Stdlib\Message;
use Zend\Form\FormElementManager\FormElementManagerV3Polyfill as FormElementManager;
use Zend\Http\PhpEnvironment\RemoteAddress;
use Zend\Log\Logger;
use Zend\Session\Container;
use Zend\View\Renderer\PhpRenderer;

class ContactUs extends AbstractBlockLayout
{
    /**
     * @var FormElementManager
     */
    protected $formElementManager;

    /**
     * @var array
     */
    protected $defaultSettings = [];

    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var bool
     */
    protected $isAuthenticated;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param FormElementManager $formElementManager
     * @param array $defaultSettings
     * @param Mailer $mailer
     * @param bool $isAuthenticated
     * @param Logger $logger
     */
    public function __construct(
        FormElementManager $formElementManager,
        array $defaultSettings,
        Mailer $mailer,
        $isAuthenticated,
        Logger $logger
    ) {
        $this->formElementManager = $formElementManager;
        $this->defaultSettings = $defaultSettings;
        $this->mailer = $mailer;
        $this->isAuthenticated = $isAuthenticated;
        $this->logger = $logger;
    }

    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/block-layout/contact-us';

    public function getLabel()
    {
        return 'Contact us'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $data = $block->getData();

        // Check and normalize options.
        $hasError = false;

        $data['antispam'] = !empty($data['antispam']);

        if (empty($data['questions'])) {
            $data['questions'] = [];
        } else {
            $questions = $this->stringToList($data['questions']);
            $data['questions'] = [];
            foreach ($questions as $questionAnswer) {
                list($question, $answer) = array_map('trim', explode('=', $questionAnswer, 2));
                if ($answer === '') {
                    $errorStore->addError('questions', 'To create antispam, each question must be separated from the answer by a "=".'); // @translate
                    $hasError = true;
                }
                $data['questions'][$question] = $answer;
            }
        }

        if ($hasError) {
            return;
        }

        $block->setData($data);
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        SitePageRepresentation $page = null,
        SitePageBlockRepresentation $block = null
    ) {
        /** @var \ContactUs\Form\ContactUsForm $form */
        $form = $this->formElementManager->get(ContactUsBlockForm::class);

        $addedBlock = empty($block);
        $data = $addedBlock ? $this->defaultSettings : $block->data() + $this->defaultSettings;
        if (is_array($data['questions'])) {
            $questions = $data['questions'];
            $data['questions'] = '';
            foreach ($questions as $question => $answer) {
                $data['questions'] .= $question . ' = ' . $answer . "\n";
            }
        }

        $form->setData([
            'o:block[__blockIndex__][o:data]' => $data,
        ]);

        $form->prepare();

        $html = '<p class="explanation">'
            . $view->translate('Append a form to allow visitors to contact us.') // @translate
            . '</p>';
        $html .= $view->formCollection($form, false);
        return $html;
    }

    public function prepareRender(PhpRenderer $view)
    {
        $view->headLink()
            ->appendStylesheet($view->assetUrl('css/contact-us.css', 'ContactUs'));
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $data = $block->data();
        $antispam = !$this->isAuthenticated && !empty($data['antispam']) && !empty($data['questions']);
        $isSpam = false;
        $message = null;
        $status = null;
        $formOptions = [];
        $defaultForm = true;
        $translate = $view->plugin('translate');

        $params = $view->params()->fromPost();
        if ($params) {
            if ($antispam) {
                $isSpam = $this->checkSpam($view, $block, $params);
                if (!$isSpam) {
                    $question = (new Container('ContactUs'))->question;
                    $answer = isset($params['answer']) ? $params['answer'] : false;
                    $checkAnswer = $data['questions'][$question];
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
                    $args['email'] = $args['from'];
                    $args['site_title'] = $block->page()->site()->title();
                    $args['site_url'] = $block->page()->site()->siteUrl();

                    $mail = [];
                    $mail['from'] = $args['from'];
                    $mail['fromName'] = $args['name'] ?: null;
                    $owner = $block->page()->site()->owner();
                    $mail['to'] = $owner ? $owner->email() : $view->setting('administrator_email');
                    $mail['toName'] = $owner ? $owner->name() : null;
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
                            $translate('Sorry, we are not enable to send your email. Come back later.') // @translate
                        );
                    }
                    // Send the confirmation message to the visitor.
                    elseif ($data['confirmation_enabled']) {
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
                        $subject = $data['confirmation_subject'] ?: $this->defaultSettings['confirmation_subject'];
                        $mail['subject'] = $this->fillMessage($translate($subject), $args);
                        $body = $data['confirmation_body'] ?: $this->defaultSettings['confirmation_body'];
                        $mail['body'] = $this->fillMessage($translate($body), $args);

                        $result = $this->sendEmail($mail);
                        if (!$result) {
                            $status = 'error';
                            $message = new Message(
                                $translate('Sorry, we are not enable to send the confirmation email.') // @translate
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
                $question = array_rand($data['questions']);
                $answer = $data['questions'][$question];
                $formOptions = [
                    'question' => $question,
                    'checkAnswer' => $answer,
                ];
                $session = new Container('ContactUs');
                $session->question = $question;
            }
            $form = $this->formElementManager->get(ContactUsForm::class, $formOptions);
        }

        return $view->partial(
            self::PARTIAL_NAME,
            [
                'block' => $block,
                'form' => $form,
                'message' => $message,
                'status' => $status,
            ]
        );
    }

    /**
     * Check if a post is a spam.
     *
     * @param PhpRenderer $view
     * @param SitePageBlockRepresentation $block
     * @param array $params
     * @return boolean
     */
    protected function checkSpam(PhpRenderer $view, SitePageBlockRepresentation $block, array $params)
    {
        $data = $block->data();
        $session = new Container('ContactUs');
        $question = isset($session->question) ? $session->question : null;
        $isSpam = empty($question)
            || !isset($data['questions'][$question])
            || empty($params['check'])
            || substr(md5($question), 0, 16) !== $params['check'];
        return $isSpam;
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

        $result = str_replace(array_keys($holders), array_values($holders), $message);
        return $result;
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
            ->setTo($params['to'], $params['toName'])
            ->setSubject($params['subject'])
            ->setBody($params['body']);
        if ($params['from']) {
            $message
                ->setFrom($params['from'], $params['fromName']);
        }

        try {
            $mailer->send($message);
        } catch (\Exception $e) {
            $this->logger->err(new Message(
                'Error when sending email. Arguments:\n%s', // @translate
                json_encode($params, 448)
            ));
            return false;
        }

        return true;
    }

    /**
     * Get each line of a string separately.
     *
     * @param string $string
     * @return array
     */
    protected function stringToList($string)
    {
        return array_filter(array_map('trim', explode("\n", $this->fixEndOfLine($string))));
    }

    /**
     * Clean the text area from end of lines.
     *
     * This method fixes Windows and Apple copy/paste from a textarea input.
     *
     * @param string $string
     * @return string
     */
    protected function fixEndOfLine($string)
    {
        return str_replace(["\r\n", "\n\r", "\r"], ["\n", "\n", "\n"], $string);
    }
}
