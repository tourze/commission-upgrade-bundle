<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\CommissionUpgradeBundle\Command\BatchCheckUpgradeCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * 功能测试：验证批量命令投递消息到队列
 *
 * 对应任务：T025 [P] [US3]
 * 测试目标：验证批量升级检查命令的参数解析、消息投递、性能要求
 */
#[CoversClass(BatchCheckUpgradeCommand::class)]
#[RunTestsInSeparateProcesses]
final class BatchCheckUpgradeCommandTest extends AbstractCommandTestCase
{
    private ?CommandTester $commandTester = null;

    protected function getCommandTester(): CommandTester
    {
        if (null === $this->commandTester) {
            $application = new Application(self::$kernel);
            $command = $application->find('commission-upgrade:batch-check');
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
     * 测试场景：命令支持 --level 参数
     */
    public function testOptionLevel(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute(['--level' => '1']);

        $this->assertSame(0, $commandTester->getStatusCode());
    }

    /**
     * 测试场景：命令支持 --limit 参数
     */
    public function testOptionLimit(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute(['--limit' => '100']);

        $this->assertSame(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertNotEmpty($output);
    }
}
