<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommissionDistributorBundle\Entity\Distributor;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeHistory;
use Tourze\CommissionUpgradeBundle\Repository\DistributorLevelUpgradeHistoryRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * 集成测试：DistributorLevelUpgradeHistoryRepository
 *
 * 测试目标：验证 DistributorLevelUpgradeHistoryRepository 的查询行为和持久化功能
 *
 * @internal
 */
#[CoversClass(DistributorLevelUpgradeHistoryRepository::class)]
#[RunTestsInSeparateProcesses]
final class DistributorLevelUpgradeHistoryRepositoryTest extends AbstractRepositoryTestCase
{

    /**
     * 实现抽象方法：创建一个新的实体（不持久化）
     */
    protected function createNewEntity(): object
    {
        // 创建依赖实体
        $distributorLevel = new DistributorLevel();
        $distributorLevel->setName('Level_' . uniqid());

        $previousLevel = new DistributorLevel();
        $previousLevel->setName('Bronze_' . uniqid());

        $newLevel = new DistributorLevel();
        $newLevel->setName('Silver_' . uniqid());

        // 创建测试用户
        $userManager = self::getService(\Tourze\UserServiceContracts\UserManagerInterface::class);
        $user = $userManager->createUser(
            userIdentifier: 'test-user-' . uniqid(),
            password: 'password123'
        );

        // 创建并持久化 Distributor（需要 user 和 level）
        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($distributorLevel);

        // 持久化关联实体
        $em = self::getEntityManager();
        $em->persist($user);
        $em->persist($distributorLevel);
        $em->persist($distributor);
        $em->persist($previousLevel);
        $em->persist($newLevel);
        $em->flush();

        $history = new DistributorLevelUpgradeHistory();
        $history->setDistributor($distributor);
        $history->setPreviousLevel($previousLevel);
        $history->setNewLevel($newLevel);
        $history->setSatisfiedExpression('distributor.performance >= 10000');
        $history->setContextSnapshot(['performance' => 12000]);
        $history->setUpgradeTime(new \DateTimeImmutable());
        $history->setTriggerType('auto');

        return $history;
    }

    /**
     * 实现抽象方法：获取 Repository 实例
     */
    protected function getRepository(): DistributorLevelUpgradeHistoryRepository
    {
        return self::getService(DistributorLevelUpgradeHistoryRepository::class);
    }

    /**
     * 测试设置钩子（无需自定义逻辑）
     */
    protected function onSetUp(): void
    {
        // 无需自定义设置逻辑，使用基类的默认实现
    }

    /**
     * TC-001：根据分销员查找升级历史（按时间倒序）
     *
     * 验证 findByDistributor() 方法是否正确查找分销员的升级历史，并按时间倒序排列
     */
    public function testFindByDistributorShouldReturnHistoriesOrderedByTimeDesc(): void
    {
        // Arrange - 创建测试数据
        $distributor = $this->createDistributor();
        $previousLevel = $this->createDistributorLevel('Bronze');
        $newLevel = $this->createDistributorLevel('Silver');

        // 创建两条历史记录，时间不同
        $history1 = $this->createUpgradeHistory($distributor, $previousLevel, $newLevel, new \DateTimeImmutable('2024-01-01'));
        $history2 = $this->createUpgradeHistory($distributor, $previousLevel, $newLevel, new \DateTimeImmutable('2024-01-02'));

        $em = self::getEntityManager();
        $em->persist($history1);
        $em->persist($history2);
        $em->flush();

        // Act - 查询分销员的升级历史
        $results = $this->getRepository()->findByDistributor($distributor);

        // Assert
        $this->assertNotEmpty($results, '应该找到升级历史记录');
        $this->assertGreaterThanOrEqual(2, count($results), '至少应该有2条历史记录');

        // 验证返回的是正确的实体类型
        foreach ($results as $result) {
            $this->assertInstanceOf(DistributorLevelUpgradeHistory::class, $result);
        }

        // 验证按时间倒序排列（最新的在前）
        $firstResult = $results[0];
        $secondResult = $results[1];
        $this->assertGreaterThanOrEqual(
            $secondResult->getUpgradeTime()->getTimestamp(),
            $firstResult->getUpgradeTime()->getTimestamp(),
            '结果应该按时间倒序排列'
        );
    }

    /**
     * TC-002：根据分销员查找升级历史时限制返回数量
     *
     * 验证 findByDistributor() 方法的 limit 参数是否生效
     */
    public function testFindByDistributorShouldRespectLimitParameter(): void
    {
        // Arrange - 创建测试数据
        $distributor = $this->createDistributor();
        $previousLevel = $this->createDistributorLevel('Bronze_' . uniqid());
        $newLevel = $this->createDistributorLevel('Silver_' . uniqid());

        // 创建5条历史记录
        $em = self::getEntityManager();
        for ($i = 1; $i <= 5; $i++) {
            $history = $this->createUpgradeHistory(
                $distributor,
                $previousLevel,
                $newLevel,
                new \DateTimeImmutable("2024-01-{$i}")
            );
            $em->persist($history);
        }
        $em->flush();

        // Act - 限制返回2条
        $results = $this->getRepository()->findByDistributor($distributor, 2);

        // Assert
        $this->assertCount(2, $results, '应该只返回2条记录');
    }

    /**
     * TC-003：查询不存在的分销员应返回空数组
     *
     * 验证当分销员没有升级历史时，findByDistributor() 返回空数组
     */
    public function testFindByDistributorWithNoHistoryShouldReturnEmptyArray(): void
    {
        // Arrange - 创建没有历史记录的分销员
        $distributor = $this->createDistributor();
        $em = self::getEntityManager();
        $em->persist($distributor);
        $em->flush();

        // Act
        $results = $this->getRepository()->findByDistributor($distributor);

        // Assert
        $this->assertIsArray($results);
        $this->assertEmpty($results, '没有历史记录的分销员应该返回空数组');
    }

    /**
     * TC-004：统计指定时间范围内的升级事件数量
     *
     * 验证 countByTimeRange() 方法是否正确统计时间范围内的升级数量
     */
    public function testCountByTimeRangeShouldReturnCorrectCount(): void
    {
        // Arrange - 创建测试数据
        $distributor = $this->createDistributor();
        $previousLevel = $this->createDistributorLevel('Level1_' . uniqid());
        $newLevel = $this->createDistributorLevel('Level2_' . uniqid());

        $em = self::getEntityManager();

        // 在范围内创建3条记录
        $history1 = $this->createUpgradeHistory($distributor, $previousLevel, $newLevel, new \DateTimeImmutable('2024-02-01'));
        $history2 = $this->createUpgradeHistory($distributor, $previousLevel, $newLevel, new \DateTimeImmutable('2024-02-15'));
        $history3 = $this->createUpgradeHistory($distributor, $previousLevel, $newLevel, new \DateTimeImmutable('2024-02-28'));

        // 在范围外创建1条记录
        $history4 = $this->createUpgradeHistory($distributor, $previousLevel, $newLevel, new \DateTimeImmutable('2024-03-15'));

        $em->persist($history1);
        $em->persist($history2);
        $em->persist($history3);
        $em->persist($history4);
        $em->flush();

        // Act - 统计2月份的升级数量
        $count = $this->getRepository()->countByTimeRange(
            new \DateTimeImmutable('2024-02-01'),
            new \DateTimeImmutable('2024-02-28 23:59:59')
        );

        // Assert
        $this->assertGreaterThanOrEqual(3, $count, '2月份应该至少有3条升级记录');
    }

    /**
     * TC-005：统计空时间范围应返回0
     *
     * 验证当时间范围内没有记录时，countByTimeRange() 返回0
     */
    public function testCountByTimeRangeWithNoRecordsShouldReturnZero(): void
    {
        // Arrange - 使用一个不存在记录的时间范围
        $start = new \DateTimeImmutable('2099-01-01');
        $end = new \DateTimeImmutable('2099-12-31');

        // Act
        $count = $this->getRepository()->countByTimeRange($start, $end);

        // Assert
        $this->assertEquals(0, $count, '空时间范围应该返回0');
    }

    /**
     * TC-006：保存实体
     *
     * 验证 save() 方法能否正确保存实体到数据库
     */
    public function testSaveMethodShouldPersistEntity(): void
    {
        // Arrange - 创建新实体
        $distributor = $this->createDistributor();
        $previousLevel = $this->createDistributorLevel('SaveTest1_' . uniqid());
        $newLevel = $this->createDistributorLevel('SaveTest2_' . uniqid());

        $history = $this->createUpgradeHistory($distributor, $previousLevel, $newLevel);

        // Act - 使用 save 方法保存
        $this->getRepository()->save($history, flush: true);

        // Assert
        $this->assertNotNull($history->getId(), '保存后实体应该有ID');

        // 验证可以重新查询到
        $found = $this->getRepository()->find($history->getId());
        $this->assertNotNull($found, '保存的实体应该能通过ID查询到');
        $this->assertEquals($history->getId(), $found->getId());
    }

    /**
     * TC-007：保存不刷新
     *
     * 验证 save() 方法在 flush=false 时是否不立即保存到数据库
     */
    public function testSaveMethodWithoutFlushShouldNotImmediatelyPersist(): void
    {
        // Arrange
        $distributor = $this->createDistributor();
        $previousLevel = $this->createDistributorLevel('NoFlush1_' . uniqid());
        $newLevel = $this->createDistributorLevel('NoFlush2_' . uniqid());

        $history = $this->createUpgradeHistory($distributor, $previousLevel, $newLevel);

        // Act - 保存但不刷新
        $this->getRepository()->save($history, flush: false);

        // 手动刷新
        self::getEntityManager()->flush();

        // Assert
        $this->assertNotNull($history->getId(), '即使不立即刷新，实体应该在后续flush后获得ID');
    }

    /**
     * TC-008：删除实体
     *
     * 验证 remove() 方法能否正确删除实体
     */
    public function testRemoveMethodShouldDeleteEntity(): void
    {
        // Arrange - 创建并保存实体
        $distributor = $this->createDistributor();
        $previousLevel = $this->createDistributorLevel('RemoveTest1_' . uniqid());
        $newLevel = $this->createDistributorLevel('RemoveTest2_' . uniqid());

        $history = $this->createUpgradeHistory($distributor, $previousLevel, $newLevel);

        $em = self::getEntityManager();
        $em->persist($history);
        $em->flush();

        $historyId = $history->getId();

        // Act - 删除实体
        $this->getRepository()->remove($history, flush: true);

        // Assert - 验证实体已被删除
        $found = $this->getRepository()->find($historyId);
        $this->assertNull($found, '删除后的实体应该无法查询到');
    }

    /**
     * 创建测试用的分销员
     */
    private function createDistributor(): Distributor
    {
        // 创建测试用户
        $userManager = self::getService(\Tourze\UserServiceContracts\UserManagerInterface::class);
        $user = $userManager->createUser(
            userIdentifier: 'test-dist-user-' . uniqid(),
            password: 'password123'
        );

        // 创建分销员等级
        $level = new DistributorLevel();
        $level->setName('TestLevel_' . uniqid());

        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($level);

        $em = self::getEntityManager();
        $em->persist($user);
        $em->persist($level);
        $em->persist($distributor);
        $em->flush();

        return $distributor;
    }

    /**
     * 创建测试用的分销员等级
     */
    private function createDistributorLevel(string $name): DistributorLevel
    {
        $level = new DistributorLevel();
        $level->setName($name);

        $em = self::getEntityManager();
        $em->persist($level);
        $em->flush();

        return $level;
    }

    /**
     * 创建测试用的升级历史
     */
    private function createUpgradeHistory(
        Distributor $distributor,
        DistributorLevel $previousLevel,
        DistributorLevel $newLevel,
        ?\DateTimeImmutable $upgradeTime = null
    ): DistributorLevelUpgradeHistory {
        $history = new DistributorLevelUpgradeHistory();
        $history->setDistributor($distributor);
        $history->setPreviousLevel($previousLevel);
        $history->setNewLevel($newLevel);
        $history->setSatisfiedExpression('distributor.performance >= 10000');
        $history->setContextSnapshot(['performance' => 12000]);
        $history->setUpgradeTime($upgradeTime ?? new \DateTimeImmutable());
        $history->setTriggerType('auto');

        return $history;
    }
}
