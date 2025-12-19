<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\Entity\DirectUpgradeRule;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * 单元测试：DirectUpgradeRule 实体类
 *
 * 验证直升规则实体的基本属性和方法
 * @internal
 */
#[CoversClass(DirectUpgradeRule::class)]
class DirectUpgradeRuleTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new DirectUpgradeRule();
    }

    /**
     * @return array<array{string, mixed}>
     */
    public static function propertiesProvider(): array
    {
        $targetLevel = self::createDistributorLevel('钻石分销员', 5);

        return [
            ['targetLevel', $targetLevel],
            ['upgradeExpression', 'settledCommissionAmount >= 50000 and inviteeCount >= 100'],
            ['priority', 999],
            ['description', '钻石等级直升规则，用于重要客户快速升级'],
            ['minLevelRequirement', 2],
        ];
    }

    /**
     * 创建真实的 DistributorLevel 对象
     */
    private static function createDistributorLevel(string $name, int $level): DistributorLevel
    {
        $distributorLevel = new DistributorLevel();
        $distributorLevel->setName($name);
        $distributorLevel->setLevel($level);

        return $distributorLevel;
    }

    /**
     * TC-001：验证实体实现 Stringable 接口
     *
     * 确保实体可以通过 __toString() 方法转换为字符串
     */
    public function testEntityImplementsStringable(): void
    {
        $this->assertTrue(
            is_a(DirectUpgradeRule::class, \Stringable::class, true),
            'DirectUpgradeRule 必须实现 Stringable 接口'
        );
    }

    /**
     * TC-002：验证 __toString() 方法的格式
     *
     * 确保实体的字符串表示包含目标等级和优先级信息
     */
    public function testToStringFormat(): void
    {
        // Arrange
        $entity = new DirectUpgradeRule();

        $targetLevel = self::createDistributorLevel('钻石分销员', 5);
        $priority = 100;

        $entity->setTargetLevel($targetLevel);
        $entity->setPriority($priority);

        // Act
        $stringRepresentation = (string) $entity;

        // Assert
        $this->assertStringContainsString('直升至', $stringRepresentation);
        $this->assertStringContainsString('钻石分销员', $stringRepresentation);
        $this->assertStringContainsString('优先级:100', $stringRepresentation);
    }

    /**
     * TC-003：验证默认值
     *
     * 测试实体创建时的默认值
     */
    public function testDefaultValues(): void
    {
        // Arrange
        $entity = new DirectUpgradeRule();

        // Assert - 默认值验证
        $this->assertSame(0, $entity->getPriority(), '默认优先级应为 0');
        $this->assertTrue($entity->isEnabled(), '默认状态应为启用');
        $this->assertNull($entity->getDescription(), '默认描述应为 null');
        $this->assertNull($entity->getMinLevelRequirement(), '默认最低等级要求应为 null');
    }

    /**
     * TC-004：验证 isEligibleForLevel 方法 - 当前等级低于目标等级且无最低等级要求
     *
     * 测试符合条件的情况
     */
    public function testIsEligibleForLevelWithoutMinRequirement(): void
    {
        // Arrange
        $entity = new DirectUpgradeRule();
        $targetLevel = self::createDistributorLevel('黄金分销员', 3);
        $currentLevel = self::createDistributorLevel('普通分销员', 1);

        $entity->setTargetLevel($targetLevel);
        // 不设置最低等级要求

        // Act & Assert
        $this->assertTrue($entity->isEligibleForLevel($currentLevel), '当前等级1应该可以直升到等级3');
    }

    /**
     * TC-005：验证 isEligibleForLevel 方法 - 当前等级不低于目标等级
     *
     * 测试不符合条件的情况：等级相等或更高
     */
    public function testIsEligibleForLevelWhenCurrentLevelNotLower(): void
    {
        // Arrange
        $entity = new DirectUpgradeRule();
        $targetLevel = self::createDistributorLevel('黄金分销员', 3);

        $entity->setTargetLevel($targetLevel);

        // Test: 等级相等
        $currentLevelEqual = self::createDistributorLevel('黄金分销员', 3);
        $this->assertFalse($entity->isEligibleForLevel($currentLevelEqual), '相同等级不应该符合直升条件');

        // Test: 等级更高
        $currentLevelHigher = self::createDistributorLevel('钻石分销员', 5);
        $this->assertFalse($entity->isEligibleForLevel($currentLevelHigher), '更高等级不应该符合直升条件');
    }

    /**
     * TC-006：验证 isEligibleForLevel 方法 - 不满足最低等级要求
     *
     * 测试不符合条件的情况：低于最低等级要求
     */
    public function testIsEligibleForLevelBelowMinRequirement(): void
    {
        // Arrange
        $entity = new DirectUpgradeRule();
        $targetLevel = self::createDistributorLevel('钻石分销员', 5);
        $currentLevel = self::createDistributorLevel('普通分销员', 1);

        $entity->setTargetLevel($targetLevel);
        $entity->setMinLevelRequirement(2); // 要求至少达到等级2

        // Act & Assert
        $this->assertFalse($entity->isEligibleForLevel($currentLevel), '等级1不满足最低等级要求2，不应该符合直升条件');
    }

    /**
     * TC-007：验证 isEligibleForLevel 方法 - 满足最低等级要求
     *
     * 测试符合条件的情况：满足最低等级要求
     */
    public function testIsEligibleForLevelMeetsMinRequirement(): void
    {
        // Arrange
        $entity = new DirectUpgradeRule();
        $targetLevel = self::createDistributorLevel('钻石分销员', 5);
        $currentLevel = self::createDistributorLevel('黄金分销员', 3);

        $entity->setTargetLevel($targetLevel);
        $entity->setMinLevelRequirement(2); // 要求至少达到等级2

        // Act & Assert
        $this->assertTrue($entity->isEligibleForLevel($currentLevel), '等级3满足最低等级要求2，应该符合直升条件');
    }

    /**
     * TC-008：验证 isEligibleForLevel 方法 - 边界情况：刚好满足最低等级要求
     *
     * 测试边界条件
     */
    public function testIsEligibleForLevelExactMinRequirement(): void
    {
        // Arrange
        $entity = new DirectUpgradeRule();
        $targetLevel = self::createDistributorLevel('钻石分销员', 5);
        $currentLevel = self::createDistributorLevel('银牌分销员', 2);

        $entity->setTargetLevel($targetLevel);
        $entity->setMinLevelRequirement(2); // 要求至少达到等级2

        // Act & Assert
        $this->assertTrue($entity->isEligibleForLevel($currentLevel), '等级2刚好满足最低等级要求2，应该符合直升条件');
    }

    /**
     * TC-009：验证属性设置器的返回值
     *
     * 测试 setter 方法不支持链式调用（与 DistributorLevelUpgradeRule 不同）
     */
    public function testSettersReturnVoid(): void
    {
        // Arrange
        $reflectionClass = new \ReflectionClass(DirectUpgradeRule::class);

        // Act & Assert - setters 返回 void
        $setters = [
            'setTargetLevel',
            'setUpgradeExpression',
            'setPriority',
            'setIsEnabled',
            'setDescription',
            'setMinLevelRequirement',
        ];

        foreach ($setters as $setter) {
            $method = $reflectionClass->getMethod($setter);
            $returnType = $method->getReturnType();
            $this->assertNotNull($returnType, sprintf('%s 必须声明返回类型', $setter));
            $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
            $this->assertSame('void', $returnType->getName(), sprintf('%s 必须返回 void', $setter));
        }
    }

    /**
     * TC-010：验证优先级范围
     *
     * 测试优先级的有效范围
     */
    public function testPriorityRange(): void
    {
        // Arrange
        $entity = new DirectUpgradeRule();

        // Test: 最小值
        $entity->setPriority(0);
        $this->assertSame(0, $entity->getPriority());

        // Test: 最大值
        $entity->setPriority(999);
        $this->assertSame(999, $entity->getPriority());

        // Test: 中间值
        $entity->setPriority(500);
        $this->assertSame(500, $entity->getPriority());
    }
}
