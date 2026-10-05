<?php declare(strict_types=1);

namespace ContactUsTest\Spam;

use ContactUs\Service\SpamCheckerFactory;
use ContactUs\Spam\LocalSpamChecker;
use ContactUs\Spam\SpamGuardChecker;
use Interop\Container\ContainerInterface;
use Omeka\Module\Manager as ModuleManager;
use Omeka\Module\Module;
use PHPUnit\Framework\TestCase;

/**
 * The module works without SpamGuard: the factory falls back on the local
 * checker whenever SpamGuard is missing or not active.
 */
class SpamCheckerFactoryTest extends TestCase
{
    protected function services(?string $state, bool $hasService): ContainerInterface
    {
        $module = null;
        if ($state !== null) {
            $module = $this->createMock(Module::class);
            $module->method('getState')->willReturn($state);
        }
        $moduleManager = $this->createMock(ModuleManager::class);
        $moduleManager->method('getModule')->willReturn($module);

        $services = $this->createMock(ContainerInterface::class);
        $services->method('has')->willReturnCallback(fn ($name) => $name === 'SpamGuard\SpamChecker' && $hasService);
        $services->method('get')->willReturnCallback(fn ($name) => $name === 'Omeka\ModuleManager'
            ? $moduleManager
            : new \stdClass());
        return $services;
    }

    /**
     * @dataProvider stateProvider
     */
    public function testChecker(?string $state, bool $hasService, string $expected): void
    {
        $checker = (new SpamCheckerFactory())($this->services($state, $hasService), 'ContactUs\SpamChecker');
        $this->assertInstanceOf($expected, $checker);
    }

    public function stateProvider(): array
    {
        return [
            'SpamGuard not installed' => [null, false, LocalSpamChecker::class],
            'SpamGuard installed but not active' => [ModuleManager::STATE_NOT_ACTIVE, false, LocalSpamChecker::class],
            'SpamGuard to upgrade' => [ModuleManager::STATE_NEEDS_UPGRADE, true, LocalSpamChecker::class],
            'SpamGuard active' => [ModuleManager::STATE_ACTIVE, true, SpamGuardChecker::class],
        ];
    }
}
