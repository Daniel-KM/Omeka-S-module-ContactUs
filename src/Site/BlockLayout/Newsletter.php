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

class Newsletter extends AbstractBlockLayout implements TemplateableBlockLayoutInterface
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/block-layout/newsletter';

    public function getLabel()
    {
        return 'Newsletter'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore): void
    {
        $data = $block->getData();

        $data['confirmation_enabled'] = !empty($data['confirmation_enabled']);
        $data['antispam'] = !empty($data['antispam']);

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
        $defaultSettings = $services->get('Config')['contactus']['block_settings']['newsletter'];
        $blockFieldset = \ContactUs\Form\NewsletterFieldset::class;

        $data = $block ? ($block->data() ?? []) + $defaultSettings : $defaultSettings;

        $dataForm = [];
        foreach ($data as $key => $value) {
            $dataForm['o:block[__blockIndex__][o:data][' . $key . ']'] = $value;
        }
        $fieldset = $formElementManager->get($blockFieldset);
        $fieldset->populateValues($dataForm);

        $html = '<p class="explanation">'
            . $view->translate('Append a form to allow visitors to subscribe to a newsletter.') // @translate
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

        $options['newsletter_only'] = true;

        if (!empty($options['antispam'])) {
            $options['questions'] = $view->siteSetting('contactus_questions');
        }

        $vars = [];
        $vars['block'] = $block;
        $vars['options'] = $options;

        return $view->partial($templateViewScript, $vars);
    }
}
