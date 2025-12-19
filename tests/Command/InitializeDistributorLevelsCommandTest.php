<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\CommissionUpgradeBundle\Command\InitializeDistributorLevelsCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * 功能测试：验证批量初始化分销员等级命令.
 *
 * 对应任务：初始化分销员等级
 * 测试目标：验证命令的注册、配置、参数解析
 */
#[CoversClass(InitializeDistributorLevelsCommand::class)]
#[RunTestsInSeparateProcesses]
final class InitializeDistributorLevelsCommandTest extends AbstractCommandTestCase
{
    private ?CommandTester $commandTester = null;

    protected function getCommandTester(): CommandTester
    {
        if (null === $this->commandTester) {
            $application = new Application(self::$kernel);
            $command = $application->find('commission-upgrade:initialize-levels');
            $this->commandTester = new CommandTester($command);
        }

        return $this->commandTester;
    }

    protected function onSetUp(): void
    {
    }

    /**
     * 测试场景：命令能正常执行
     */
    public function testExecute(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
    }

    /**
     * 测试场景：命令支持 --batch-size 选项.
     */
    public function testOptionBatchSize(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute(['--batch-size' => '50']);

        $this->assertSame(0, $commandTester->getStatusCode());
    }

    /**
     * 测试场景：命令支持 --dry-run 选项.
     */
    public function testOptionDryRun(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute(['--dry-run' => true]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('模拟运行', $output);
    }
}
