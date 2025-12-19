<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionLevelBundle\Repository\DistributorLevelRepository;
use Tourze\CommissionUpgradeBundle\Command\MigrateDistributorLevelFieldCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * 功能测试：验证分销等级 level 字段迁移命令.
 *
 * 对应任务：为现有的 DistributorLevel 实体初始化 level 字段
 * 测试目标：验证迁移命令的参数解析、数据更新、选项处理
 */
#[CoversClass(MigrateDistributorLevelFieldCommand::class)]
#[RunTestsInSeparateProcesses]
final class MigrateDistributorLevelFieldCommandTest extends AbstractCommandTestCase
{
    private ?CommandTester $commandTester = null;

    protected function getCommandTester(): CommandTester
    {
        if (null === $this->commandTester) {
            $application = new Application(self::$kernel);
            $command = $application->find('commission-upgrade:migrate-distributor-level-field');
            $this->commandTester = new CommandTester($command);
        }

        return $this->commandTester;
    }

    protected function onSetUp(): void
    {
        // 清理测试数据
        $repository = self::getService(DistributorLevelRepository::class);
        $repository->createQueryBuilder('dl')
            ->delete()
            ->getQuery()
            ->execute();

        $em = self::getService(EntityManagerInterface::class);
        $em->flush();
        $em->clear();
    }

    /**
     * 测试场景：当没有需要迁移的记录时，命令能正常执行并返回成功.
     */
    public function testExecute(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('所有 DistributorLevel 记录的 level 字段已经设置完成', $output);
    }

    /**
     * 测试场景：命令支持 --dry-run 选项，模拟运行不实际更新数据库.
     */
    public function testOptionDryRun(): void
    {
        // 创建测试数据：level 为 0 的等级
        $em = self::getService(EntityManagerInterface::class);
        $level1 = new DistributorLevel();
        $level1->setName('测试等级1');
        $level1->setLevel(0);
        $em->persist($level1);

        $level2 = new DistributorLevel();
        $level2->setName('测试等级2');
        $level2->setLevel(0);
        $em->persist($level2);

        $em->flush();
        $em->clear();

        // 执行 dry-run
        $commandTester = $this->getCommandTester();
        $commandTester->execute(['--dry-run' => true]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('模拟运行', $output);
        $this->assertStringContainsString('这是模拟运行，实际未更新数据库', $output);

        // 验证数据未被修改
        $em = self::getService(EntityManagerInterface::class);
        $updatedLevel1 = $em->find(DistributorLevel::class, $level1->getId());
        $this->assertNotNull($updatedLevel1);
        $this->assertSame(0, $updatedLevel1->getLevel(), 'dry-run 模式下数据不应被修改');
    }

    /**
     * 测试场景：命令支持 --force 选项，强制更新所有记录.
     */
    public function testOptionForce(): void
    {
        // 创建测试数据：包含已设置 level 的记录
        $em = self::getService(EntityManagerInterface::class);
        $level1 = new DistributorLevel();
        $level1->setName('已有等级1');
        $level1->setLevel(5);
        $em->persist($level1);

        $level2 = new DistributorLevel();
        $level2->setName('已有等级2');
        $level2->setLevel(10);
        $em->persist($level2);

        $em->flush();
        $id1 = $level1->getId();
        $id2 = $level2->getId();
        $em->clear();

        // 执行 --force
        $commandTester = $this->getCommandTester();
        $commandTester->execute(['--force' => true]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('找到 2 个需要更新 level 字段的记录', $output);

        // 验证数据已按 ID 顺序重新分配 level 值
        $em = self::getService(EntityManagerInterface::class);
        $updatedLevel1 = $em->find(DistributorLevel::class, $id1);
        $updatedLevel2 = $em->find(DistributorLevel::class, $id2);

        $this->assertNotNull($updatedLevel1);
        $this->assertNotNull($updatedLevel2);

        // ID 小的应该获得较小的 level 值
        if ($id1 < $id2) {
            $this->assertSame(1, $updatedLevel1->getLevel());
            $this->assertSame(2, $updatedLevel2->getLevel());
        } else {
            $this->assertSame(1, $updatedLevel2->getLevel());
            $this->assertSame(2, $updatedLevel1->getLevel());
        }
    }

    /**
     * 测试场景：迁移 level 为 0 的等级记录.
     */
    public function testMigrateLevelsWithDefaultValue(): void
    {
        // 创建测试数据：部分 level 为 0，部分已设置
        $em = self::getService(EntityManagerInterface::class);

        $level1 = new DistributorLevel();
        $level1->setName('需要迁移1');
        $level1->setLevel(0);
        $em->persist($level1);

        $level2 = new DistributorLevel();
        $level2->setName('已设置等级');
        $level2->setLevel(5);
        $em->persist($level2);

        $level3 = new DistributorLevel();
        $level3->setName('需要迁移2');
        $level3->setLevel(0);
        $em->persist($level3);

        $em->flush();
        $id1 = $level1->getId();
        $id3 = $level3->getId();
        $em->clear();

        // 执行迁移（不使用 force，只迁移 level=0 的记录）
        $commandTester = $this->getCommandTester();
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('找到 2 个需要更新 level 字段的记录', $output);
        $this->assertStringContainsString('共更新 2 个记录', $output);

        // 验证只有 level=0 的记录被更新
        $em = self::getService(EntityManagerInterface::class);
        $updatedLevel1 = $em->find(DistributorLevel::class, $id1);
        $updatedLevel2 = $em->find(DistributorLevel::class, $level2->getId());
        $updatedLevel3 = $em->find(DistributorLevel::class, $id3);

        $this->assertNotNull($updatedLevel1);
        $this->assertNotNull($updatedLevel2);
        $this->assertNotNull($updatedLevel3);

        // level=0 的记录应该被更新
        $this->assertNotSame(0, $updatedLevel1->getLevel());
        $this->assertNotSame(0, $updatedLevel3->getLevel());

        // 已有 level 值的记录不应被修改
        $this->assertSame(5, $updatedLevel2->getLevel());

        // 验证按 ID 顺序分配的 level 值
        if ($id1 < $id3) {
            $this->assertSame(1, $updatedLevel1->getLevel());
            $this->assertSame(2, $updatedLevel3->getLevel());
        } else {
            $this->assertSame(1, $updatedLevel3->getLevel());
            $this->assertSame(2, $updatedLevel1->getLevel());
        }
    }
}
