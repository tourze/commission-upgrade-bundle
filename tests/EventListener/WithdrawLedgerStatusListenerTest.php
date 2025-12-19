<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommissionUpgradeBundle\EventListener\WithdrawLedgerStatusListener;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(WithdrawLedgerStatusListener::class)]
#[RunTestsInSeparateProcesses]
final class WithdrawLedgerStatusListenerTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 集成测试初始化
    }

    public function testListenerCanBeInstantiated(): void
    {
        $listener = self::getService(WithdrawLedgerStatusListener::class);
        $this->assertInstanceOf(WithdrawLedgerStatusListener::class, $listener);
    }

    public function testListenerIsNotAbstract(): void
    {
        $reflection = new \ReflectionClass(WithdrawLedgerStatusListener::class);
        $this->assertFalse($reflection->isAbstract());
    }
}
