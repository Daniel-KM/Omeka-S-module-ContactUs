<?php declare(strict_types=1);

namespace ContactUsTest\Form;

use ContactUs\Form\ContactUsForm;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ordering/partition of the flat contact.
 *
 * Core content fields (from/name/subject/message) may be repositioned and
 * relabeled among the custom fields; unlisted core fields are auto-appended.
 */
class ContactUsFormPartitionTest extends TestCase
{
    private function names(array $order): array
    {
        return array_map(fn ($item) => $item['name'], $order);
    }

    public function testNoCoreListedKeepsDefaultOrderThenCustom(): void
    {
        $fields = [
            'phone' => ['name' => 'phone', 'type' => 'Laminas\Form\Element\Tel', 'options' => ['label' => 'Phone']],
        ];
        $p = ContactUsForm::partitionFields($fields);
        $this->assertSame(['from', 'name', 'subject', 'message', 'phone'], $this->names($p['order']));
        $this->assertSame(['phone'], array_keys($p['custom']));
        $this->assertSame([], $p['relabel']);
    }

    public function testEmptyDefinitionIsJustCoreOrder(): void
    {
        $p = ContactUsForm::partitionFields([]);
        $this->assertSame(['from', 'name', 'subject', 'message'], $this->names($p['order']));
    }

    public function testCoreListedControlsOrderAndInterleavesCustom(): void
    {
        $fields = [
            'name' => ['name' => 'name', 'options' => ['label' => '']],
            'phone' => ['name' => 'phone', 'options' => ['label' => 'Phone']],
            'email' => ['name' => 'email', 'options' => ['label' => '']],
            'message' => ['name' => 'message', 'options' => ['label' => '']],
        ];
        $p = ContactUsForm::partitionFields($fields);
        // "email" is an alias of the core "from"; "subject" not listed is
        // appended at the end.
        $this->assertSame(['name', 'phone', 'from', 'message', 'subject'], $this->names($p['order']));
        $this->assertSame(['phone'], array_keys($p['custom']));
    }

    public function testEmailAliasMapsToFromAndBodyToMessage(): void
    {
        $fields = [
            'body' => ['name' => 'body', 'options' => ['label' => '']],
            'email' => ['name' => 'email', 'options' => ['label' => '']],
        ];
        $p = ContactUsForm::partitionFields($fields);
        $this->assertSame(['message', 'from', 'name', 'subject'], $this->names($p['order']));
    }

    public function testRelabelIsCapturedAndStarStripped(): void
    {
        $fields = [
            'email' => ['name' => 'email', 'options' => ['label' => 'Courriel']],
            'message' => ['name' => 'message', 'options' => ['label' => '* Votre message']],
        ];
        $p = ContactUsForm::partitionFields($fields);
        $this->assertSame(['from' => 'Courriel', 'message' => 'Votre message'], $p['relabel']);
    }

    public function testReservedCustomNamesAreSkipped(): void
    {
        $fields = [
            'submit' => ['name' => 'submit', 'options' => ['label' => 'x']],
            'consent' => ['name' => 'consent', 'options' => ['label' => 'x']],
            'phone' => ['name' => 'phone', 'options' => ['label' => 'Phone']],
        ];
        $p = ContactUsForm::partitionFields($fields);
        $this->assertSame(['phone'], array_keys($p['custom']));
        $this->assertNotContains('submit', $this->names($p['order']));
        $this->assertNotContains('consent', $this->names($p['order']));
    }

    public function testIdSystemFieldStaysCustom(): void
    {
        $fields = [
            'id' => ['type' => 'hidden', 'value' => [1, 2]],
            'phone' => ['name' => 'phone', 'options' => ['label' => 'Phone']],
        ];
        $p = ContactUsForm::partitionFields($fields);
        $this->assertArrayHasKey('id', $p['custom']);
        $this->assertArrayHasKey('phone', $p['custom']);
    }

    public function testDuplicateCoreAliasKeepsFirstPosition(): void
    {
        $fields = [
            'email' => ['name' => 'email', 'options' => ['label' => '']],
            'from' => ['name' => 'from', 'options' => ['label' => '']],
        ];
        $p = ContactUsForm::partitionFields($fields);
        // Both alias the core "from"; it appears once, at the first position.
        $this->assertSame(['from', 'name', 'subject', 'message'], $this->names($p['order']));
    }

    public function testLegacyScalarLabelsSupported(): void
    {
        $fields = [
            'email' => 'Courriel',
            'phone' => 'Phone',
        ];
        $p = ContactUsForm::partitionFields($fields);
        $this->assertSame('Courriel', $p['relabel']['from']);
        $this->assertSame(['phone'], array_keys($p['custom']));
    }
}
