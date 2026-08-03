<?php declare(strict_types=1);

namespace ContactUsTest\Form;

use ContactUs\Form\ContactUsForm;
use Laminas\Form\Element;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Integration tests: build the real flat form through the FormElementManager
 * and check rendered element order and the presence of required core fields.
 */
class ContactUsFormBuildTest extends AbstractHttpControllerTestCase
{
    private function build(array $fields): ContactUsForm
    {
        $services = $this->getApplication()->getServiceManager();
        /** @var \Laminas\Form\FormElementManager $formManager */
        $formManager = $services->get('FormElementManager');
        return $formManager->get(ContactUsForm::class, ['fields' => $fields]);
    }

    private function contentOrder(ContactUsForm $form): array
    {
        $names = array_keys($form->getElements());
        return array_values(array_filter(
            $names,
            fn ($n) => in_array($n, ['from', 'name', 'subject', 'message', 'phone', 'topic'], true)
        ));
    }

    public function testDefaultOrderWhenNoCoreListed(): void
    {
        $form = $this->build([
            'phone' => ['name' => 'phone', 'type' => Element\Tel::class, 'options' => ['label' => 'Phone']],
        ]);
        $this->assertSame(['from', 'name', 'subject', 'message', 'phone'], $this->contentOrder($form));
        $this->assertTrue($form->has('from'));
        $this->assertTrue($form->has('message'));
    }

    public function testCoreRepositionedAndCustomInterleaved(): void
    {
        $form = $this->build([
            'name' => ['name' => 'name', 'options' => ['label' => '']],
            'phone' => ['name' => 'phone', 'type' => Element\Tel::class, 'options' => ['label' => 'Phone']],
            'email' => ['name' => 'email', 'options' => ['label' => 'Courriel']],
            'message' => ['name' => 'message', 'options' => ['label' => '']],
        ]);
        // subject is unlisted, appended at the end of the content zone.
        $this->assertSame(['name', 'phone', 'from', 'message', 'subject'], $this->contentOrder($form));
    }

    public function testMissingRequiredCoreAreAutoAdded(): void
    {
        // The admin lists only "name": email (from) and message must still be
        // present and required.
        $form = $this->build([
            'name' => ['name' => 'name', 'options' => ['label' => '']],
        ]);
        $this->assertTrue($form->has('from'));
        $this->assertTrue($form->has('message'));
        $this->assertTrue((bool) $form->get('from')->getAttribute('required'));
        $this->assertTrue((bool) $form->get('message')->getAttribute('required'));
    }

    public function testRelabelAppliesToCoreField(): void
    {
        $form = $this->build([
            'email' => ['name' => 'email', 'options' => ['label' => 'Courriel']],
        ]);
        $this->assertSame('Courriel', $form->get('from')->getLabel());
    }

    public function testCustomSelectIsBuiltFlat(): void
    {
        $form = $this->build([
            'topic' => [
                'name' => 'topic',
                'type' => Element\Select::class,
                'options' => ['label' => 'Subject', 'value_options' => ['q' => 'Question', 'b' => 'Bug']],
            ],
        ]);
        $this->assertTrue($form->has('topic'));
        $this->assertInstanceOf(Element\Select::class, $form->get('topic'));
        $this->assertFalse($form->has('fields'), 'The legacy "fields" fieldset must not exist anymore.');
    }
}
