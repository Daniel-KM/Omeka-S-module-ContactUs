<?php declare(strict_types=1);

namespace ContactUsTest\Api\Adapter;

use ContactUsTest\ContactUsTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests the storage of the reasons of the spam status.
 *
 * @group integration
 */
class SpamReasonTest extends AbstractHttpControllerTestCase
{
    use ContactUsTestTrait;

    protected $ids = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        foreach ($this->ids as $id) {
            try {
                $this->api()->delete('contact_messages', $id);
            } catch (\Exception $e) {
            }
        }
        $this->logout();
        parent::tearDown();
    }

    protected function create(array $data): \ContactUs\Api\Representation\MessageRepresentation
    {
        $message = $this->api()->create('contact_messages', $data + [
            'o:email' => 'visitor@example.org',
            'o-module-contact:body' => 'Hello',
            'o-module-contact:ip' => '203.0.113.88',
        ])->getContent();
        $this->ids[] = $message->id();
        return $message;
    }

    public function testReasonsOfTheAutomaticChecksAreStored(): void
    {
        $message = $this->create([
            'o-module-contact:is_spam' => true,
            'o-module-contact:spam_reason' => 'tooFast,keyword',
        ]);
        $this->assertTrue($message->isSpam());
        $this->assertSame('tooFast,keyword', $message->spamReason());
        $this->assertSame(['tooFast', 'keyword'], $message->spamReasons());
    }

    public function testLegitimateMessageHasNoReason(): void
    {
        $message = $this->create(['o-module-contact:is_spam' => false]);
        $this->assertNull($message->spamReason());
        $this->assertSame([], $message->spamReasons());
    }

    /**
     * Marking a message as spam by hand stores "admin" and replaces the
     * reasons of the automatic checks.
     */
    public function testManualSpamIsStoredAsAdmin(): void
    {
        $message = $this->create(['o-module-contact:is_spam' => false]);
        $message = $this->api()->update('contact_messages', $message->id(), [
            'o-module-contact:is_spam' => true,
        ], [], ['isPartial' => true])->getContent();
        $this->assertTrue($message->isSpam());
        $this->assertSame('admin', $message->spamReason());
    }

    /**
     * Unmarking a false positive is stored as "admin" too, so the false
     * positives of the automatic checks remain visible.
     */
    public function testManualUnspamIsStoredAsAdmin(): void
    {
        $message = $this->create([
            'o-module-contact:is_spam' => true,
            'o-module-contact:spam_reason' => 'tooFast',
        ]);
        $message = $this->api()->update('contact_messages', $message->id(), [
            'o-module-contact:is_spam' => false,
        ], [], ['isPartial' => true])->getContent();
        $this->assertFalse($message->isSpam());
        $this->assertSame('admin', $message->spamReason());
    }

    /**
     * An update that does not change the status keeps the stored reasons.
     */
    public function testUnchangedStatusKeepsTheReasons(): void
    {
        $message = $this->create([
            'o-module-contact:is_spam' => true,
            'o-module-contact:spam_reason' => 'keyword',
        ]);
        $message = $this->api()->update('contact_messages', $message->id(), [
            'o-module-contact:is_spam' => true,
            'o-module-contact:is_read' => true,
        ], [], ['isPartial' => true])->getContent();
        $this->assertSame('keyword', $message->spamReason());
    }

    public function testReasonIsExposedInJson(): void
    {
        $message = $this->create([
            'o-module-contact:is_spam' => true,
            'o-module-contact:spam_reason' => 'honeypot',
        ]);
        $json = $message->jsonSerialize();
        $this->assertSame('honeypot', $json['o-module-contact:spam_reason']);
    }

    /**
     * @dataProvider filterProvider
     */
    public function testFilterBySpamReason(string $filter, array $expected): void
    {
        $ids = [
            'tooFast' => $this->create(['o-module-contact:is_spam' => true, 'o-module-contact:spam_reason' => 'tooFast'])->id(),
            'powIp' => $this->create(['o-module-contact:is_spam' => true, 'o-module-contact:spam_reason' => 'powChallenge,ipReputation'])->id(),
            'keyword' => $this->create(['o-module-contact:is_spam' => true, 'o-module-contact:spam_reason' => 'keyword'])->id(),
            'mixed' => $this->create(['o-module-contact:is_spam' => true, 'o-module-contact:spam_reason' => 'tooFast,linkTld'])->id(),
            'admin' => $this->create(['o-module-contact:is_spam' => true, 'o-module-contact:spam_reason' => 'admin'])->id(),
            'legit' => $this->create(['o-module-contact:is_spam' => false])->id(),
        ];
        $found = $this->api()->search('contact_messages', [
            'spam_reason' => $filter,
            'id' => array_values($ids),
        ], ['returnScalar' => 'id'])->getContent();
        $found = array_map('intval', array_values($found));
        sort($found);
        $wanted = array_map(fn ($k) => $ids[$k], $expected);
        sort($wanted);
        $this->assertSame($wanted, $found);
    }

    public function filterProvider(): array
    {
        return [
            'possible false positives' => ['fragile', ['tooFast', 'powIp']],
            'reliable checks' => ['reliable', ['keyword', 'mixed']],
            'manual decision' => ['admin', ['admin']],
            'one reason alone' => ['tooFast', ['tooFast', 'mixed']],
            'one reason in the middle of a list' => ['ipReputation', ['powIp']],
            'no prefix match' => ['too', []],
        ];
    }
}
