<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\CommissionUpgradeBundle\Command\ValidateUpgradeRulesCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * 功能测试：验证升级规则命令.
 *
 * 对应任务：规则表达式验证
 * 测试目标：验证升级规则的表达式语法验证功能
 */
#[CoversClass(ValidateUpgradeRulesCommand::class)]
#[RunTestsInSeparateProcesses]
final class ValidateUpgradeRulesCommandTest extends AbstractCommandTestCase
{
    private ?CommandTester $commandTester = null;

    protected function getCommandTester(): CommandTester
    {
        if (null === $this->commandTester) {
            $application = new Application(self::$kernel);
            $command = $application->find('commission-upgrade:validate-rules');
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
}
