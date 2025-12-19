<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommissionUpgradeBundle\EventListener\CommissionLedgerStatusListener;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CommissionLedgerStatusListener::class)]
#[RunTestsInSeparateProcesses]
final class CommissionLedgerStatusListenerTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 集成测试初始化
    }

    public function testListenerShouldBeInstantiated(): void
    {
        $listener = self::getService(CommissionLedgerStatusListener::class);
        $this->assertInstanceOf(CommissionLedgerStatusListener::class, $listener);
    }

    public function testListenerShouldNotBeAbstract(): void
    {
        $reflection = new \ReflectionClass(CommissionLedgerStatusListener::class);
        $this->assertFalse($reflection->isAbstract());
    }
}
