<?php declare(strict_types=1);

namespace ContactUsTest\Stdlib;

use Common\Mvc\Controller\Plugin\SendEmail;
use Common\Stdlib\EasyMeta;
use Common\Stdlib\PsrMessage;
use ContactUs\Stdlib\ContactSubmission;
use Laminas\Form\Form;
use Laminas\Form\FormElementManager;
use Omeka\Api\Manager as ApiManager;
use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Mailer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the isolatable helpers of ContactSubmission (the ones that do
 * not need a full http/view context). A small proxy exposes the protected
 * methods; the collaborators are mocked.
 */
class ContactSubmissionTest extends TestCase
{
    private ?array $serverBackup = null;

    protected function tearDown(): void
    {
        if ($this->serverBackup !== null) {
            $_SERVER = $this->serverBackup;
            $this->serverBackup = null;
        }
    }

    private function submission(?Messenger $messenger = null): ContactSubmissionProxy
    {
        return new ContactSubmissionProxy(
            $this->createMock(Api::class),
            $this->createMock(ApiManager::class),
            $this->createMock(EasyMeta::class),
            $this->createMock(FormElementManager::class),
            $this->createMock(Mailer::class),
            $messenger ?? $this->createMock(Messenger::class),
            $this->createMock(SendEmail::class),
            []
        );
    }

    public function testFixEndOfLineNormalizesEveryStyle(): void
    {
        $out = $this->submission()->proxyFixEndOfLine("a\r\nb\n\rc\rd\ne");
        $this->assertSame("a\nb\nc\nd\ne", $out);
    }

    public function testStringToListTrimsAndDropsEmptyLines(): void
    {
        $out = $this->submission()->proxyStringToList("  a \r\n\n b \r\n   \n c");
        $this->assertSame(['a', 'b', 'c'], array_values($out));
    }

    public function testCheckAntispamOptionsPassesArraysThrough(): void
    {
        $in = ['Question?' => 'answer'];
        $this->assertSame($in, $this->submission()->proxyCheckAntispamOptions($in));
    }

    public function testCheckAntispamOptionsParsesKeyValueString(): void
    {
        $out = $this->submission()->proxyCheckAntispamOptions("How much? = 2\nJust a label");
        $this->assertSame(['How much?' => '2', 'Just a label' => ''], $out);
    }

    public function testClientIpPrefersFirstForwardedFor(): void
    {
        $this->serverBackup = $_SERVER;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9, 10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
        $this->assertSame('203.0.113.9', $this->submission()->proxyClientIp());
    }

    public function testClientIpFallsBackToRemoteAddr(): void
    {
        $this->serverBackup = $_SERVER;
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP']);
        $_SERVER['REMOTE_ADDR'] = '198.51.100.4';
        $this->assertSame('198.51.100.4', $this->submission()->proxyClientIp());
    }

    public function testClientIpIgnoresInvalidForwardedFor(): void
    {
        $this->serverBackup = $_SERVER;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';
        unset($_SERVER['HTTP_X_REAL_IP']);
        $_SERVER['REMOTE_ADDR'] = '198.51.100.5';
        $this->assertSame('198.51.100.5', $this->submission()->proxyClientIp());
    }

    public function testFormErrorMessageListsErrorsAndRegistersThem(): void
    {
        $form = $this->createMock(Form::class);
        $form->method('getMessages')->willReturn([
            'from' => ['isEmpty' => 'Value is required.'],
            'subject' => ['tooLong' => 'Too long.'],
        ]);
        $messenger = $this->createMock(Messenger::class);
        $messenger->expects($this->once())->method('addFormErrors')->with($form);

        $message = $this->submission($messenger)->proxyFormErrorMessage($form);

        $this->assertInstanceOf(PsrMessage::class, $message);
        $rendered = (string) $message;
        $this->assertStringContainsString('Value is required.', $rendered);
        $this->assertStringContainsString('Too long.', $rendered);
    }

    public function testFormErrorMessageIsGenericWithoutErrors(): void
    {
        $form = $this->createMock(Form::class);
        $form->method('getMessages')->willReturn([]);
        $message = $this->submission()->proxyFormErrorMessage($form);
        $this->assertInstanceOf(PsrMessage::class, $message);
        $this->assertSame('There is an error.', (string) $message);
    }

    // =========================================================================
    // Custom fields posted by a theme
    // =========================================================================

    /**
     * The fields declared for the form, as prepared by normalizeOptions(): the
     * custom ones and a core one, that must never be collected as a field.
     */
    private function fieldsForForm(): array
    {
        return [
            'phone' => ['type' => 'text', 'options' => ['label' => 'Phone']],
            'topics' => ['type' => 'multicheckbox', 'options' => ['label' => 'Topics']],
            'subject' => null,
        ];
    }

    public function testCollectPostedFieldsFromFlatParams(): void
    {
        $out = $this->submission()->proxyCollectPostedFields($this->fieldsForForm(), [
            'from' => 'a@example.org',
            'subject' => 'Hi',
            'message' => 'Hello',
            'phone' => '0600',
            'topics' => ['a', 'b'],
        ]);
        $this->assertSame(['phone' => '0600', 'topics' => ['a', 'b']], $out);
    }

    /**
     * A theme that was not upgraded still posts the custom fields inside a
     * "fields" fieldset: the values must be collected all the same.
     */
    public function testCollectPostedFieldsFromLegacyThemeFieldset(): void
    {
        $out = $this->submission()->proxyCollectPostedFields($this->fieldsForForm(), [
            'from' => 'a@example.org',
            'subject' => 'Hi',
            'message' => 'Hello',
            'fields' => ['phone' => '0600', 'topics' => ['a', 'b']],
        ]);
        $this->assertSame(['phone' => '0600', 'topics' => ['a', 'b']], $out);
    }

    public function testCollectPostedFieldsPrefersFlatOverLegacy(): void
    {
        $out = $this->submission()->proxyCollectPostedFields($this->fieldsForForm(), [
            'phone' => 'flat',
            'fields' => ['phone' => 'legacy'],
        ]);
        $this->assertSame('flat', $out['phone']);
    }

    public function testCollectPostedFieldsMissingValueIsNull(): void
    {
        $out = $this->submission()->proxyCollectPostedFields($this->fieldsForForm(), []);
        $this->assertSame(['phone' => null, 'topics' => null], $out);
    }

    /**
     * The resource ids are hidden fields, so they are always normalized to a
     * list of positive integers, from the flat param or the legacy fieldset.
     */
    public function testCollectPostedFieldsNormalizesResourceIds(): void
    {
        $fields = ['id' => ['type' => 'hidden']] + $this->fieldsForForm();

        $flat = $this->submission()->proxyCollectPostedFields($fields, ['id' => ['3', 3, '0', 'x', 5]]);
        $this->assertSame([3, 5], $flat['id']);

        $json = $this->submission()->proxyCollectPostedFields($fields, ['id' => '[7,8]']);
        $this->assertSame([7, 8], $json['id']);

        $legacy = $this->submission()->proxyCollectPostedFields($fields, ['fields' => ['id' => ['4']]]);
        $this->assertSame([4], $legacy['id']);
    }

    public function testFillMessageReplacesEachSubmittedField(): void
    {
        $out = $this->submission()->proxyFillMessage(
            'phone={phone} topics={topics}',
            ['fields' => ['phone' => '0600', 'topics' => ['a', 'b']]],
            ['phone' => [], 'topics' => []],
            $this->createMock(\Omeka\Api\Representation\SiteRepresentation::class)
        );
        $this->assertSame('phone=0600 topics=a, b', $out);
    }

    public function testFillMessageEmptiesDeclaredButNotSubmittedField(): void
    {
        $out = $this->submission()->proxyFillMessage(
            '[{phone}]',
            ['fields' => []],
            ['phone' => []],
            $this->createMock(\Omeka\Api\Representation\SiteRepresentation::class)
        );
        $this->assertSame('[]', $out);
    }

    public function testGetMailSubjectReturnsProvidedSubject(): void
    {
        $this->assertSame(
            'Custom subject',
            $this->submission()->proxyGetMailSubject(['subject' => 'Custom subject'])
        );
    }
}

/**
 * Exposes the protected helpers under test.
 */
class ContactSubmissionProxy extends ContactSubmission
{
    public function proxyFixEndOfLine($string): string
    {
        return $this->fixEndOfLine($string);
    }

    public function proxyStringToList($string): array
    {
        return $this->stringToList($string);
    }

    public function proxyCheckAntispamOptions($options): array
    {
        return $this->checkAntispamOptions($options);
    }

    public function proxyClientIp(): string
    {
        return $this->clientIp();
    }

    public function proxyFormErrorMessage($form): PsrMessage
    {
        return $this->formErrorMessage($form);
    }

    public function proxyGetMailSubject(array $options): string
    {
        return $this->getMailSubject($options);
    }

    public function proxyCollectPostedFields(array $fieldsForForm, array $params): array
    {
        $this->fieldsForForm = $fieldsForForm;
        $this->params = $params;
        return $this->collectPostedFields();
    }

    public function proxyFillMessage(string $message, array $placeholders, array $declaredFields, $site = null): string
    {
        $this->options['fields'] = $declaredFields;
        $this->options['resource'] = null;
        $this->view = new FakeFillMessageView($site);
        return $this->fillMessage($message, $placeholders);
    }
}

/**
 * Minimal view exposing the "prepareMessage" plugin used by fillMessage(): the
 * common interpolation is tested in the Common module, so the fake only applies
 * the placeholders it receives.
 */
class FakeFillMessageView
{
    // Not null, else currentSite() looks for the root view model.
    public $site;

    public function __construct($site)
    {
        $this->site = $site;
    }

    public function plugin($name)
    {
        return new class {
            public function fillMessage($message, array $placeholders = [], array $context = []): string
            {
                $replace = [];
                foreach ($placeholders as $key => $value) {
                    if (!is_array($value) && !is_object($value)) {
                        $replace['{' . $key . '}'] = (string) $value;
                    }
                }
                return strtr($message, $replace);
            }
        };
    }
}
