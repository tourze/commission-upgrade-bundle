<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommissionUpgradeBundle\CommissionUpgradeBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(CommissionUpgradeBundle::class)]
#[RunTestsInSeparateProcesses]
final class CommissionUpgradeBundleTest extends AbstractBundleTestCase
{
    protected function onSetUp(): void
    {
        // 集成测试初始化
    }
}
