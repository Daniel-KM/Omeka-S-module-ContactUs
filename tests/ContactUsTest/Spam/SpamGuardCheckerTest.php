<?php declare(strict_types=1);

namespace ContactUsTest\Spam;

use ContactUs\Spam\SpamGuardChecker;
use PHPUnit\Framework\TestCase;

/**
 * Tests the adapter to the module SpamGuard: the options of this module must
 * reach the strategies.
 */
class SpamGuardCheckerTest extends TestCase
{
    public function setUp(): void
    {
        if (!class_exists(\SpamGuard\SpamContext::class)) {
            $path = dirname(__DIR__, 4) . '/SpamGuard/src/SpamContext.php';
            if (!file_exists($path)) {
                $this->markTestSkipped('Requires the module SpamGuard.');
            }
            require_once $path;
        }
    }

    /**
     * Run the adapter with a fake engine that captures the context.
     */
    protected function captureContext(array $context): \SpamGuard\SpamContext
    {
        $engine = new class {
            public $context;

            public function check($context)
            {
                $this->context = $context;
                return new class {
                    public $reasons = [];

                    public function isSpam(): bool
                    {
                        return false;
                    }
                };
            }
        };
        (new SpamGuardChecker($engine))->check($context);
        return $engine->context;
    }

    public function testPowSkipIsForwarded(): void
    {
        $this->assertTrue($this->captureContext(['powSkip' => true])->extra['powSkip']);
        $this->assertFalse($this->captureContext(['powSkip' => false])->extra['powSkip']);
    }

    public function testCheckDnsMxIsForwarded(): void
    {
        $this->assertTrue($this->captureContext(['checkDnsMx' => true])->extra['checkDnsMx']);
        $this->assertFalse($this->captureContext([])->extra['checkDnsMx']);
    }

    public function testSignalsOfTheSessionAreForwarded(): void
    {
        $ctx = $this->captureContext([
            'formLoadedAt' => 1700000000,
            'powSalt' => 'salt',
            'powNonce' => '42',
        ]);
        $this->assertSame(1700000000, $ctx->formLoadedAt);
        $this->assertSame('salt', $ctx->extra['powSalt']);
        $this->assertSame('42', $ctx->extra['powNonce']);
    }
}
