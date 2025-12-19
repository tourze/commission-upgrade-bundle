<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\Entity\DirectUpgradeRule;
use Tourze\CommissionUpgradeBundle\Repository\DirectUpgradeRuleRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * 单元测试：DirectUpgradeRuleRepository 仓储类
 *
 * 测试直升规则仓储的查询方法
 * @internal
 */
#[CoversClass(DirectUpgradeRuleRepository::class)]
#[RunTestsInSeparateProcesses]
final class DirectUpgradeRuleRepositoryTest extends AbstractRepositoryTestCase
{
    /**
     * 实现抽象方法：创建一个新的实体（不持久化）
     */
    protected function createNewEntity(): object
    {
        $targetLevel = new DistributorLevel();
        $targetLevel->setName('Target_' . uniqid());
        $targetLevel->setLevel(1);

        // 持久化关联实体
        $em = self::getEntityManager();
        $em->persist($targetLevel);
        $em->flush();

        $rule = new DirectUpgradeRule();
        $rule->setTargetLevel($targetLevel);
        $rule->setUpgradeExpression('distributor.performance >= 10000');
        $rule->setPriority(100);
        $rule->setIsEnabled(true);

        return $rule;
    }

    /**
     * 实现抽象方法：获取 Repository 实例
     */
    protected function getRepository(): DirectUpgradeRuleRepository
    {
        return self::getService(DirectUpgradeRuleRepository::class);
    }

    /**
     * 测试设置钩子（无需自定义逻辑）
     */
    protected function onSetUp(): void
    {
        // 无需自定义设置逻辑，使用基类的默认实现
    }

    /**
     * TC-001：测试 findEligibleRulesForLevel 方法 - 基本功能
     *
     * 验证能够找到适用的直升规则，按优先级排序
     * 注意：target_level_id 有唯一约束，每个目标等级只能有一条规则
     */
    public function testFindEligibleRulesForLevel(): void
    {
        // Arrange
        $currentLevel = $this->createDistributorLevel('普通会员', 1);
        $targetLevel1 = $this->createDistributorLevel('银牌会员', 2);
        $targetLevel2 = $this->createDistributorLevel('金牌会员', 3);
        $targetLevel3 = $this->createDistributorLevel('钻石会员', 4);

        // 创建多个规则，优先级不同（每个目标等级只能有一条规则）
        $rule1 = $this->createDirectUpgradeRule($targetLevel2, 'rule1', 100, true); // 中等优先级
        $rule2 = $this->createDirectUpgradeRule($targetLevel1, 'rule2', 200, true); // 高优先级
        $rule3 = $this->createDirectUpgradeRule($targetLevel3, 'rule3', 50, true);  // 低优先级

        // 添加一个不适用的规则（目标等级不高于当前等级）
        $lowerTargetLevel = $this->createDistributorLevel('访客', 0);
        $this->createDirectUpgradeRule($lowerTargetLevel, 'rule_not_applicable', 300, true);

        // 添加一个禁用的规则（需要使用不同的目标等级）
        $disabledTargetLevel = $this->createDistributorLevel('禁用目标', 5);
        $this->createDirectUpgradeRule($disabledTargetLevel, 'disabled_rule', 250, false);

        self::getEntityManager()->flush();

        // Act
        $eligibleRules = $this->getRepository()->findEligibleRulesForLevel($currentLevel);

        // Assert
        $this->assertCount(3, $eligibleRules, '应该找到3个符合条件的规则');

        // 验证排序：按优先级降序
        $this->assertSame('rule2', $eligibleRules[0]->getUpgradeExpression()); // 优先级 200
        $this->assertSame('rule1', $eligibleRules[1]->getUpgradeExpression()); // 优先级 100
        $this->assertSame('rule3', $eligibleRules[2]->getUpgradeExpression()); // 优先级 50

        // 验证目标等级都高于当前等级
        foreach ($eligibleRules as $rule) {
            $this->assertGreaterThan(
                $currentLevel->getLevel(),
                $rule->getTargetLevel()->getLevel(),
                '所有返回的规则目标等级都应该高于当前等级'
            );
        }
    }

    /**
     * TC-002：测试 findEligibleRulesForLevel 方法 - 最低等级要求过滤
     *
     * 验证最低等级要求的过滤功能
     * 注意：target_level_id 有唯一约束，每个目标等级只能有一条规则
     */
    public function testFindEligibleRulesForLevelWithMinLevelRequirement(): void
    {
        // Arrange
        $currentLevel = $this->createDistributorLevel('普通会员', 1);
        $targetLevel1 = $this->createDistributorLevel('VIP会员', 5);
        $targetLevel2 = $this->createDistributorLevel('超级VIP', 6);

        // 创建规则：要求最低等级为 2，当前等级 1 不满足
        $ruleWithMinReq = $this->createDirectUpgradeRule($targetLevel1, 'requires_level_2', 100, true, 2);

        // 创建规则：无最低等级要求（使用不同的目标等级）
        $ruleWithoutMinReq = $this->createDirectUpgradeRule($targetLevel2, 'no_min_req', 50, true);

        self::getEntityManager()->flush();

        // Act
        $eligibleRules = $this->getRepository()->findEligibleRulesForLevel($currentLevel);

        // Assert
        $this->assertCount(1, $eligibleRules, '只应该找到1个符合条件的规则');
        $this->assertSame('no_min_req', $eligibleRules[0]->getUpgradeExpression());
    }

    /**
     * TC-003：测试 findByTargetLevel 方法
     *
     * 验证根据目标等级查找规则
     * 注意：target_level_id 有唯一约束，每个目标等级只能有一条规则
     */
    public function testFindByTargetLevel(): void
    {
        // Arrange
        $targetLevel1 = $this->createDistributorLevel('黄金会员', 3);
        $targetLevel2 = $this->createDistributorLevel('钻石会员', 4);
        $targetLevel3 = $this->createDistributorLevel('白金会员', 5);

        $rule1 = $this->createDirectUpgradeRule($targetLevel1, 'to_gold', 100, true);
        $rule2 = $this->createDirectUpgradeRule($targetLevel2, 'to_diamond', 100, true);
        // 使用不同的目标等级创建禁用规则
        $disabledRule = $this->createDirectUpgradeRule($targetLevel3, 'disabled_to_platinum', 100, false);

        self::getEntityManager()->flush();

        // Act
        $foundRule = $this->getRepository()->findByTargetLevel($targetLevel1);

        // Assert
        $this->assertNotNull($foundRule);
        $this->assertSame('to_gold', $foundRule->getUpgradeExpression());
        $this->assertTrue($foundRule->isEnabled());
    }

    /**
     * TC-004：测试 findByTargetLevel 方法 - 未找到结果
     *
     * 验证目标等级不存在时的处理
     */
    public function testFindByTargetLevelNotFound(): void
    {
        // Arrange
        $targetLevel = $this->createDistributorLevel('不存在的等级', 99);
        self::getEntityManager()->flush();

        // Act
        $foundRule = $this->getRepository()->findByTargetLevel($targetLevel);

        // Assert
        $this->assertNull($foundRule, '不存在的目标等级应该返回 null');
    }

    /**
     * TC-005：测试 findAllEnabled 方法
     *
     * 验证查找所有启用的规则，按目标等级降序排列
     * 注意：target_level_id 有唯一约束，每个目标等级只能有一条规则
     * 注意：测试环境已加载 DataFixtures，所以会有额外的数据
     */
    public function testFindAllEnabled(): void
    {
        // Arrange
        $level1 = $this->createDistributorLevel('铜牌测试', 101);
        $level2 = $this->createDistributorLevel('银牌测试', 102);
        $level3 = $this->createDistributorLevel('金牌测试', 103);
        $level4 = $this->createDistributorLevel('白金测试', 104);

        $rule1 = $this->createDirectUpgradeRule($level3, 'to_gold_test', 100, true);    // 等级103，优先级100
        $rule2 = $this->createDirectUpgradeRule($level1, 'to_bronze_test', 200, true);  // 等级101，优先级200
        $rule3 = $this->createDirectUpgradeRule($level2, 'to_silver_test', 150, true);  // 等级102，优先级150
        // 使用不同的目标等级创建禁用规则
        $disabledRule = $this->createDirectUpgradeRule($level4, 'disabled_test', 300, false); // 禁用

        self::getEntityManager()->flush();

        // Act
        $enabledRules = $this->getRepository()->findAllEnabled();

        // Assert - 至少包含我们创建的3个启用规则，加上 Fixtures 中的规则
        $this->assertGreaterThanOrEqual(3, count($enabledRules), '应该至少找到3个启用的规则');

        // 筛选出我们创建的测试规则
        $testRules = array_filter($enabledRules, fn ($r) => str_contains($r->getUpgradeExpression(), '_test'));
        $this->assertCount(3, $testRules, '应该找到3个测试规则');

        // 验证排序：按目标等级降序（在测试规则中）
        $testRulesArray = array_values($testRules);
        $this->assertSame(103, $testRulesArray[0]->getTargetLevel()->getLevel()); // 金牌测试
        $this->assertSame(102, $testRulesArray[1]->getTargetLevel()->getLevel()); // 银牌测试
        $this->assertSame(101, $testRulesArray[2]->getTargetLevel()->getLevel()); // 铜牌测试
    }

    /**
     * TC-006：测试 findHighestPriorityRule 方法
     *
     * 验证查找最高优先级的适用规则
     * 注意：target_level_id 有唯一约束，每个目标等级只能有一条规则
     */
    public function testFindHighestPriorityRule(): void
    {
        // Arrange
        $currentLevel = $this->createDistributorLevel('普通用户', 0);
        $targetLevel1 = $this->createDistributorLevel('会员', 1);
        $targetLevel2 = $this->createDistributorLevel('VIP', 2);
        $targetLevel3 = $this->createDistributorLevel('超级VIP', 3);

        // 创建规则，不同优先级（每个目标等级只能有一条规则）
        $lowPriorityRule = $this->createDirectUpgradeRule($targetLevel1, 'low_priority', 50, true);
        $highPriorityRule = $this->createDirectUpgradeRule($targetLevel2, 'high_priority', 200, true);
        $mediumPriorityRule = $this->createDirectUpgradeRule($targetLevel3, 'medium_priority', 100, true);

        self::getEntityManager()->flush();

        // Act
        $highestRule = $this->getRepository()->findHighestPriorityRule($currentLevel);

        // Assert
        $this->assertNotNull($highestRule);
        $this->assertSame('high_priority', $highestRule->getUpgradeExpression());
        $this->assertSame(200, $highestRule->getPriority());
    }

    /**
     * TC-007：测试 hasDirectRuleToLevel 方法
     *
     * 验证检查是否存在到指定等级的直升规则
     */
    public function testHasDirectRuleToLevel(): void
    {
        // Arrange
        $existingLevel = $this->createDistributorLevel('存在的等级', 1);
        $nonExistingLevel = $this->createDistributorLevel('不存在的等级', 2);

        $this->createDirectUpgradeRule($existingLevel, 'exists', 100, true);

        self::getEntityManager()->flush();

        // Act & Assert
        $this->assertTrue($this->getRepository()->hasDirectRuleToLevel($existingLevel));
        $this->assertFalse($this->getRepository()->hasDirectRuleToLevel($nonExistingLevel));
    }

    /**
     * TC-008：测试 save 和 remove 方法
     *
     * 验证实体的保存和删除功能
     */
    public function testSaveAndRemove(): void
    {
        // Arrange
        $targetLevel = $this->createDistributorLevel('测试等级', 1);
        $rule = $this->createDirectUpgradeRule($targetLevel, 'test_rule', 100, true);

        // Act - Save
        $this->getRepository()->save($rule);
        self::getEntityManager()->clear();

        // Assert - Save
        $savedRule = $this->getRepository()->findByTargetLevel($targetLevel);
        $this->assertNotNull($savedRule);
        $this->assertSame('test_rule', $savedRule->getUpgradeExpression());

        // Act - Remove
        $this->getRepository()->remove($savedRule);
        self::getEntityManager()->clear();

        // Assert - Remove
        $removedRule = $this->getRepository()->findByTargetLevel($targetLevel);
        $this->assertNull($removedRule);
    }

    /**
     * 创建 DistributorLevel 实例并持久化
     */
    private function createDistributorLevel(string $name, int $level): DistributorLevel
    {
        $distributorLevel = new DistributorLevel();
        $distributorLevel->setName($name);
        $distributorLevel->setLevel($level);

        self::getEntityManager()->persist($distributorLevel);

        return $distributorLevel;
    }

    /**
     * 创建 DirectUpgradeRule 实例并持久化
     */
    private function createDirectUpgradeRule(
        DistributorLevel $targetLevel,
        string $expression,
        int $priority,
        bool $enabled,
        ?int $minLevelRequirement = null
    ): DirectUpgradeRule {
        $rule = new DirectUpgradeRule();
        $rule->setTargetLevel($targetLevel);
        $rule->setUpgradeExpression($expression);
        $rule->setPriority($priority);
        $rule->setIsEnabled($enabled);
        if (null !== $minLevelRequirement) {
            $rule->setMinLevelRequirement($minLevelRequirement);
        }

        self::getEntityManager()->persist($rule);

        return $rule;
    }
}