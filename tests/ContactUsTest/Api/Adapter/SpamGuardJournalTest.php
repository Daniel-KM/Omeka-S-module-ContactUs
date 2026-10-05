<?php declare(strict_types=1);

namespace ContactUsTest\Api\Adapter;

use ContactUsTest\ContactUsTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests the report of the manual decisions to the journal of SpamGuard.
 *
 * @group integration
 */
class SpamGuardJournalTest extends AbstractHttpControllerTestCase
{
    use ContactUsTestTrait;

    const IP = '203.0.113.55';

    protected $ids = [];

    public function setUp(): void
    {
        parent::setUp();
        if (!$this->getServiceLocator()->has('SpamGuard\SpamLog')) {
            $this->markTestSkipped('Requires the module SpamGuard.');
        }
        $this->loginAdmin();
        $_SERVER['REMOTE_ADDR'] = self::IP;
    }

    public function tearDown(): void
    {
        foreach ($this->ids as $id) {
            try {
                $this->api()->delete('contact_messages', $id);
            } catch (\Exception $e) {
            }
        }
        if ($this->getServiceLocator()->has('SpamGuard\SpamLog')) {
            $this->getServiceLocator()->get('Omeka\Connection')
                ->executeStatement('DELETE FROM `spam_log` WHERE `ip` = :ip', ['ip' => self::IP]);
        }
        $this->logout();
        parent::tearDown();
    }

    protected function journal(): array
    {
        return $this->getServiceLocator()->get('Omeka\Connection')->executeQuery(
            'SELECT `source`, `reasons`, `is_spam` FROM `spam_log` WHERE `ip` = :ip ORDER BY `id`',
            ['ip' => self::IP]
        )->fetchAllAssociative();
    }

    protected function create(bool $isSpam, ?string $reason = null): int
    {
        $data = [
            'o:email' => 'visitor@example.org',
            'o-module-contact:body' => 'Hello',
            'o-module-contact:is_spam' => $isSpam,
        ];
        if ($reason !== null) {
            $data['o-module-contact:spam_reason'] = $reason;
        }
        $id = $this->api()->create('contact_messages', $data)->getContent()->id();
        $this->ids[] = $id;
        return $id;
    }

    public function testManualSpamIsReported(): void
    {
        $id = $this->create(false);
        $this->api()->update('contact_messages', $id, ['o-module-contact:is_spam' => true], [], ['isPartial' => true]);
        $journal = $this->journal();
        $this->assertCount(1, $journal);
        $this->assertSame('contactus', $journal[0]['source']);
        $this->assertSame('admin', $journal[0]['reasons']);
        $this->assertSame(1, (int) $journal[0]['is_spam']);
        $this->assertTrue($this->getServiceLocator()->get('SpamGuard\SpamLog')
            ->hasRecentSpam(self::IP, 24, \SpamGuard\SpamStrategy\IpReputation::RELIABLE_REASONS));
    }

    /**
     * An admin who unmarks a false positive cancels the reputation of the ip.
     */
    public function testManualUnspamCancelsTheReputation(): void
    {
        $id = $this->create(true, 'keyword');
        $this->getServiceLocator()->get('SpamGuard\SpamLog')->record(self::IP, 'contactus', ['keyword'], true);
        sleep(1);
        $this->api()->update('contact_messages', $id, ['o-module-contact:is_spam' => false], [], ['isPartial' => true]);
        $this->assertFalse($this->getServiceLocator()->get('SpamGuard\SpamLog')
            ->hasRecentSpam(self::IP, 24, \SpamGuard\SpamStrategy\IpReputation::RELIABLE_REASONS));
    }

    /**
     * The automatic verdicts are recorded by SpamGuard itself, not twice by
     * the adapter.
     */
    public function testAutomaticVerdictIsNotReportedByTheAdapter(): void
    {
        $this->create(true, 'keyword');
        $this->assertSame([], $this->journal());
    }
}
