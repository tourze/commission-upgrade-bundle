<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeRule;
use Tourze\CommissionUpgradeBundle\Repository\DistributorLevelUpgradeRuleRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * 集成测试：DistributorLevelUpgradeRuleRepository
 *
 * 测试目标：验证 DistributorLevelUpgradeRuleRepository 的查询行为和持久化功能
 *
 * @internal
 */
#[CoversClass(DistributorLevelUpgradeRuleRepository::class)]
#[RunTestsInSeparateProcesses]
final class DistributorLevelUpgradeRuleRepositoryTest extends AbstractRepositoryTestCase
{

    /**
     * 实现抽象方法：创建一个新的实体（不持久化）
     */
    protected function createNewEntity(): object
    {
        $sourceLevel = new DistributorLevel();
        $sourceLevel->setName('Source_' . uniqid());

        $targetLevel = new DistributorLevel();
        $targetLevel->setName('Target_' . uniqid());

        // 持久化关联实体
        $em = self::getEntityManager();
        $em->persist($sourceLevel);
        $em->persist($targetLevel);
        $em->flush();

        $rule = new DistributorLevelUpgradeRule();
        $rule->setSourceLevel($sourceLevel);
        $rule->setTargetLevel($targetLevel);
        $rule->setUpgradeExpression('distributor.performance >= 10000');
        $rule->setIsEnabled(true);
        $rule->setDescription('Test upgrade rule for ' . uniqid());

        return $rule;
    }

    /**
     * 实现抽象方法：获取 Repository 实例
     */
    protected function getRepository(): DistributorLevelUpgradeRuleRepository
    {
        return self::getService(DistributorLevelUpgradeRuleRepository::class);
    }

    /**
     * 测试设置钩子（无需自定义逻辑）
     */
    protected function onSetUp(): void
    {
        // 无需自定义设置逻辑，使用基类的默认实现
    }

    /**
     * TC-001：根据源等级查找启用的升级规则
     *
     * 验证 findBySourceLevel() 方法是否正确查找根据源等级找到的启用升级规则
     */
    public function testFindBySourceLevelShouldReturnRuleWhenEnabled(): void
    {
        // Arrange - 创建启用的规则
        $sourceLevel = $this->createDistributorLevel('Bronze_' . uniqid());
        $targetLevel = $this->createDistributorLevel('Silver_' . uniqid());

        $rule = $this->createUpgradeRule($sourceLevel, $targetLevel, true);

        $em = self::getEntityManager();
        $em->persist($rule);
        $em->flush();

        // Act - 根据源等级查询
        $found = $this->getRepository()->findBySourceLevel($sourceLevel);

        // Assert
        $this->assertNotNull($found, '应该找到启用的升级规则');
        $this->assertInstanceOf(
            DistributorLevelUpgradeRule::class,
            $found,
            '返回的对象应该是 DistributorLevelUpgradeRule 实例'
        );
        $this->assertEquals(
            $rule->getId(),
            $found->getId(),
            '返回的规则应该与创建的规则一致'
        );
        $this->assertTrue($found->isEnabled(), '返回的规则应该是启用状态');
    }

    /**
     * TC-002：根据源等级查找禁用规则返回null
     *
     * 验证 findBySourceLevel() 方法不返回禁用的升级规则
     */
    public function testFindBySourceLevelShouldReturnNullWhenDisabled(): void
    {
        // Arrange - 创建禁用的规则
        $sourceLevel = $this->createDistributorLevel('Level1_' . uniqid());
        $targetLevel = $this->createDistributorLevel('Level2_' . uniqid());

        $rule = $this->createUpgradeRule($sourceLevel, $targetLevel, false);

        $em = self::getEntityManager();
        $em->persist($rule);
        $em->flush();

        // Act - 根据源等级查询
        $found = $this->getRepository()->findBySourceLevel($sourceLevel);

        // Assert
        $this->assertNull($found, '不应该找到禁用的升级规则');
    }

    /**
     * TC-003：根据源等级查找不存在的规则返回null
     *
     * 验证当源等级没有对应规则时返回null
     */
    public function testFindBySourceLevelShouldReturnNullWhenNoRule(): void
    {
        // Arrange - 创建没有规则的等级
        $sourceLevel = $this->createDistributorLevel('NoRule_' . uniqid());

        // Act
        $found = $this->getRepository()->findBySourceLevel($sourceLevel);

        // Assert
        $this->assertNull($found, '没有规则的等级应该返回null');
    }

    /**
     * TC-004：查找所有启用的规则
     *
     * 验证 findAllEnabled() 方法是否正确返回所有启用的升级规则
     */
    public function testFindAllEnabledShouldReturnOnlyEnabledRules(): void
    {
        // Arrange - 创建启用和禁用的规则
        $sourceLevel1 = $this->createDistributorLevel('Source1_' . uniqid());
        $targetLevel1 = $this->createDistributorLevel('Target1_' . uniqid());
        $enabledRule = $this->createUpgradeRule($sourceLevel1, $targetLevel1, true);

        $sourceLevel2 = $this->createDistributorLevel('Source2_' . uniqid());
        $targetLevel2 = $this->createDistributorLevel('Target2_' . uniqid());
        $disabledRule = $this->createUpgradeRule($sourceLevel2, $targetLevel2, false);

        $em = self::getEntityManager();
        $em->persist($enabledRule);
        $em->persist($disabledRule);
        $em->flush();

        // Act
        $results = $this->getRepository()->findAllEnabled();

        // Assert
        $this->assertIsArray($results, 'findAllEnabled() 应该返回数组');

        // 验证返回的结果中至少包含我们的启用规则
        $foundIds = array_map(fn ($r) => $r->getId(), $results);
        $this->assertContains(
            $enabledRule->getId(),
            $foundIds,
            '启用的规则应该在查询结果中'
        );

        // 验证禁用的规则不在结果中
        $this->assertNotContains(
            $disabledRule->getId(),
            $foundIds,
            '禁用的规则不应该在查询结果中'
        );

        // 验证所有返回的规则都是启用的
        foreach ($results as $result) {
            $this->assertTrue(
                $result->isEnabled(),
                '返回的所有规则都应该是启用状态'
            );
        }
    }

    /**
     * TC-005：查找所有启用的规则时没有启用规则返回空数组
     *
     * 验证当没有启用的规则时，findAllEnabled() 返回空数组
     */
    public function testFindAllEnabledShouldReturnEmptyArrayWhenNoEnabledRules(): void
    {
        // Arrange - 删除所有启用的规则
        $em = self::getEntityManager();
        $allRules = $this->getRepository()->findAll();
        foreach ($allRules as $rule) {
            if ($rule->isEnabled()) {
                $rule->setIsEnabled(false);
                $em->persist($rule);
            }
        }
        $em->flush();

        // Act
        $results = $this->getRepository()->findAllEnabled();

        // Assert
        $this->assertIsArray($results);
        $this->assertEmpty($results, '没有启用规则时应该返回空数组');
    }

    /**
     * TC-006：保存实体
     *
     * 验证 save() 方法能否正确保存实体到数据库
     */
    public function testSaveMethodShouldPersistEntity(): void
    {
        // Arrange - 创建新实体但不保存
        $sourceLevel = $this->createDistributorLevel('SaveSource_' . uniqid());
        $targetLevel = $this->createDistributorLevel('SaveTarget_' . uniqid());
        $rule = $this->createUpgradeRule($sourceLevel, $targetLevel, true);

        // Act - 调用 save() 方法保存
        $this->getRepository()->save($rule, flush: true);

        // Assert - 验证实体已被保存
        $this->assertNotNull($rule->getId(), '保存后实体应该有ID');

        // 尝试重新查询验证确实已保存
        $found = $this->getRepository()->find($rule->getId());
        $this->assertNotNull($found, '保存的实体应该能通过ID查到');
        $this->assertEquals($rule->getId(), $found->getId());
    }

    /**
     * TC-007：保存不刷新
     *
     * 验证 save() 方法在 flush=false 时是否不立即保存到数据库
     */
    public function testSaveMethodWithoutFlush(): void
    {
        // Arrange
        $sourceLevel = $this->createDistributorLevel('NoFlushSource_' . uniqid());
        $targetLevel = $this->createDistributorLevel('NoFlushTarget_' . uniqid());
        $rule = $this->createUpgradeRule($sourceLevel, $targetLevel, true);

        // Act - 保存但不刷新
        $this->getRepository()->save($rule, flush: false);

        // 手动刷新
        self::getEntityManager()->flush();

        // Assert
        $this->assertNotNull($rule->getId(), '即使不立即刷新，实体应该在后续flush后获得ID');
    }

    /**
     * TC-008：删除实体
     *
     * 验证 remove() 方法能否正确删除实体
     */
    public function testRemoveMethodShouldDeleteEntity(): void
    {
        // Arrange - 创建并保存实体
        $sourceLevel = $this->createDistributorLevel('RemoveSource_' . uniqid());
        $targetLevel = $this->createDistributorLevel('RemoveTarget_' . uniqid());
        $rule = $this->createUpgradeRule($sourceLevel, $targetLevel, true);

        $em = self::getEntityManager();
        $em->persist($rule);
        $em->flush();

        $ruleId = $rule->getId();

        // Act - 删除实体
        $this->getRepository()->remove($rule, flush: true);

        // Assert - 验证实体已被删除
        $found = $this->getRepository()->find($ruleId);
        $this->assertNull($found, '删除后的实体应该无法查询到');
    }

    /**
     * TC-009：测试基本的 CRUD 操作 - 查询
     *
     * 验证通过 ID 查询实体
     */
    public function testFindByIdShouldReturnEntity(): void
    {
        // Arrange
        $sourceLevel = $this->createDistributorLevel('FindSource_' . uniqid());
        $targetLevel = $this->createDistributorLevel('FindTarget_' . uniqid());
        $rule = $this->createUpgradeRule($sourceLevel, $targetLevel, true);

        $em = self::getEntityManager();
        $em->persist($rule);
        $em->flush();

        $ruleId = $rule->getId();

        // Act
        $found = $this->getRepository()->find($ruleId);

        // Assert
        $this->assertNotNull($found);
        $this->assertInstanceOf(DistributorLevelUpgradeRule::class, $found);
        $this->assertEquals($ruleId, $found->getId());
    }

    /**
     * TC-010：测试查询不存在的ID返回null
     *
     * 验证查询不存在的实体返回null
     */
    public function testFindByNonExistentIdShouldReturnNull(): void
    {
        // Act
        $found = $this->getRepository()->find('non-existent-id-' . uniqid());

        // Assert
        $this->assertNull($found, '查询不存在的ID应该返回null');
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
     * 创建测试用的升级规则
     */
    private function createUpgradeRule(
        DistributorLevel $sourceLevel,
        DistributorLevel $targetLevel,
        bool $isEnabled
    ): DistributorLevelUpgradeRule {
        $rule = new DistributorLevelUpgradeRule();
        $rule->setSourceLevel($sourceLevel);
        $rule->setTargetLevel($targetLevel);
        $rule->setUpgradeExpression('distributor.performance >= 10000');
        $rule->setIsEnabled($isEnabled);
        $rule->setDescription('Test upgrade rule for ' . uniqid());

        return $rule;
    }
}
