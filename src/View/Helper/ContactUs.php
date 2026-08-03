<?php declare(strict_types=1);

namespace ContactUs\View\Helper;

use Common\Mvc\Controller\Plugin\SendEmail;
use Common\Stdlib\EasyMeta;
use ContactUs\Stdlib\ContactSubmission;
use Laminas\Form\FormElementManager;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Manager as ApiManager;
use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Mailer;
use Psr\Container\ContainerInterface;

/**
 * Display the contact us form or handle its submission.
 *
 * The whole processing lives in ContactSubmission, a per-call state object: the
 * helper is a shared instance (a page may hold several contact blocks), so the
 * per-submission state must not be kept here. Only the cross-call dedup markers
 * stay on the helper, so a single submission is stored and sent once even when
 * several blocks match the same post.
 *
 * @see \ContactUs\Stdlib\ContactSubmission
 * @see \ContactUs\Site\BlockLayout\ContactUs
 */
class ContactUs extends AbstractHelper
{
    /**
     * @var \Omeka\Mvc\Controller\Plugin\Api
     */
    protected $apiPlugin;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

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
     * @var ContainerInterface|null
     */
    protected $services;

    /**
     * @var \Omeka\Api\Response|null
     */
    protected $postStored;

    /**
     * @var \Common\Stdlib\PsrMessage|null
     */
    protected $messageSent;

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
        $this->apiPlugin = $api;
        $this->api = $apiManager;
        $this->easyMeta = $easyMeta;
        $this->formElementManager = $formElementManager;
        $this->mailer = $mailer;
        $this->messenger = $messenger;
        $this->sendEmail = $sendEmail;
        $this->defaultOptions = $defaultOptions;
        $this->services = $services;
    }

    /**
     * @return string|array Array is used only to return data after a post
     * submitted via a dialog.
     */
    public function __invoke(array $options = [])
    {
        $submission = new ContactSubmission(
            $this->apiPlugin,
            $this->api,
            $this->easyMeta,
            $this->formElementManager,
            $this->mailer,
            $this->messenger,
            $this->sendEmail,
            $this->defaultOptions,
            $this->services,
            $this->getView(),
            $this->postStored,
            $this->messageSent
        );

        $result = $submission($options);

        // Keep the dedup markers so the next block on the page does not store
        // and send the same submission again.
        $this->postStored = $submission->postStored();
        $this->messageSent = $submission->messageSent();

        return $result;
    }
}
