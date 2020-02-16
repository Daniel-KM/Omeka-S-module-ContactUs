<?php
namespace ContactUs\Site\BlockLayout;

use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Stdlib\ErrorStore;
use Zend\View\Renderer\PhpRenderer;

class ContactUs extends AbstractBlockLayout
{
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

        $notifyRecipients = $this->stringToList($data['notify_recipients']);
        if (empty($notifyRecipients)) {
            $data['notify_recipients'] = $notifyRecipients;
        } else {
            $data['notify_recipients'] = [];
            foreach ($notifyRecipients as $notifyRecipient) {
                if (filter_var($notifyRecipient, FILTER_VALIDATE_EMAIL)) {
                    $data['notify_recipients'][] = $notifyRecipient;
                }
            }
            if (empty($data['notify_recipients'])) {
                $errorStore->addError('notify_recipients', 'Check emails for notifications or remove them to use default ones.'); // @translate
                $hasError = true;
            }
        }

        if (empty($data['questions'])) {
            $data['questions'] = [];
        } else {
            $questions = $this->stringToList($data['questions']);
            $data['questions'] = [];
            foreach ($questions as $questionAnswer) {
                list($question, $answer) = array_map('trim', explode('=', $questionAnswer, 2));
                if ($question === '' || $answer === '') {
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
        // Factory is not used to make rendering simpler.
        $services = $site->getServiceLocator();
        $formElementManager = $services->get('FormElementManager');
        $defaultSettings = $services->get('Config')['contactus']['block_settings']['contactUs'];
        $blockFieldset = \ContactUs\Form\ContactUsFieldset::class;

        $data = $block ? $block->data() + $defaultSettings : $defaultSettings;
        if (is_array($data['notify_recipients'])) {
            $values = $data['notify_recipients'];
            $data['notify_recipients'] = '';
            foreach ($values as $value) {
                $data['notify_recipients'] .= $value . "\n";
            }
        }
        if (is_array($data['questions'])) {
            $questions = $data['questions'];
            $data['questions'] = '';
            foreach ($questions as $question => $answer) {
                $data['questions'] .= $question . ' = ' . $answer . "\n";
            }
        }

        $dataForm = [];
        foreach ($data as $key => $value) {
            $dataForm['o:block[__blockIndex__][o:data][' . $key . ']'] = $value;
        }
        $fieldset = $formElementManager->get($blockFieldset);
        $fieldset->populateValues($dataForm);

        $html = '<p class="explanation">'
            . $view->translate('Append a form to allow visitors to contact us.') // @translate
            . '</p>';
        $html .= $view->formCollection($fieldset, false);
        return $html;
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $options = $block->data();
        $options['html'] = '';
        unset($options['template']);

        $vars = [];
        $vars['block'] = $block;
        $vars['options'] = $options;

        $template = $block->dataValue('template', self::PARTIAL_NAME);
        return $view->resolver($template)
            ? $view->partial($template, $vars)
            : $view->partial(self::PARTIAL_NAME, $vars);
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
