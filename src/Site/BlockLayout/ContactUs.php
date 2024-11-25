<?php declare(strict_types=1);

namespace ContactUs\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Site\BlockLayout\TemplateableBlockLayoutInterface;
use Omeka\Stdlib\ErrorStore;

class ContactUs extends AbstractBlockLayout implements TemplateableBlockLayoutInterface
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/block-layout/contact-us';

    public function getLabel()
    {
        return 'Contact us'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore): void
    {
        $data = $block->getData();

        // Check and normalize options.
        $hasError = false;

        $data['antispam'] = !empty($data['antispam']);

        if (empty($data['questions'])) {
            $data['questions'] = [];
        } elseif (!is_array($data['questions'])) {
            $questions = $this->stringToList($data['questions']);
            $data['questions'] = [];
            foreach ($questions as $questionAnswer) {
                [$question, $answer] = is_array($questionAnswer)
                    ? [key($questionAnswer), reset($questionAnswer)]
                    : (array_map('trim', explode('=', $questionAnswer, 2)) + ['', '']);
                if ($question === '' || $answer === '') {
                    $errorStore->addError('questions', 'To create antispam, each question must be separated from the answer by a "=".'); // @translate
                    $hasError = true;
                }
                $data['questions'][$question] = $answer;
            }
        }

        // The element ArrayTextarea is not managed by block.
        if (empty($data['fields'])) {
            $data['fields'] = [];
        } elseif (!is_array($data['fields'])) {
            $fields = $this->stringToList($data['fields']);
            $data['fields'] = [];
            foreach ($fields as $nameLabel) {
                [$name, $label] = is_array($nameLabel)
                    ? [key($nameLabel), reset($nameLabel)]
                    : (array_map('trim', explode('=', $nameLabel, 2)) + ['', '']);
                if ($name === '' || $label === '') {
                    $errorStore->addError('fields', 'To append fields, each row must contain a name and a label separated by a "=".'); // @translate
                    $hasError = true;
                }
                $data['fields'][$name] = $label;
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

        $data = $block ? ($block->data() ?? []) + $defaultSettings : $defaultSettings;

        $dataForm = [];
        foreach ($data as $key => $value) {
            $dataForm['o:block[__blockIndex__][o:data][' . $key . ']'] = $value;
        }
        $fieldset = $formElementManager->get($blockFieldset);
        $fieldset->populateValues($dataForm);

        $html = '<p class="explanation">'
            . $view->translate('Append a form to allow visitors to contact us. Site settings are used when following fields are empty.') // @translate
            . '</p>';
        $html .= $view->formCollection($fieldset, false);
        return $html;
    }

    public function prepareRender(PhpRenderer $view): void
    {
        $assetUrl = $view->plugin('assetUrl');
        $view->headLink()
            ->appendStylesheet($assetUrl('css/contact-us.css', 'ContactUs'));
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block, $templateViewScript = self::PARTIAL_NAME)
    {
        $options = $block->data();
        $options['html'] = '';

        $options['newsletter_label'] = empty($options['newsletter']) ? '' : $options['newsletter_label'];
        unset($options['newsletter']);

        if (!empty($options['antispam']) && empty($options['questions'])) {
            $options['questions'] = $view->siteSetting('contactus_questions');
        }

        $vars = [];
        $vars['block'] = $block;
        $vars['options'] = $options;

        return $view->partial($templateViewScript, $vars);
    }

    /**
     * Get each line of a string separately.
     *
     * @param string $string
     * @return array
     */
    protected function stringToList($string)
    {
        if (is_array($string)) {
            return $string;
        }
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
