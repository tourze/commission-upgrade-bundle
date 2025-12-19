<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeRule;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * 单元测试：DistributorLevelUpgradeRule 实体类
 *
 * 验证分销员等级升级规则实体的基本属性和方法
 * @internal
 */
#[CoversClass(DistributorLevelUpgradeRule::class)]
class DistributorLevelUpgradeRuleTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new DistributorLevelUpgradeRule();
    }

    /**
     * @return array<array{string, mixed}>
     */
    public static function propertiesProvider(): array
    {
        $sourceLevel = self::createDistributorLevel('1级分销员');
        $targetLevel = self::createDistributorLevel('2级分销员');

        return [
            ['sourceLevel', $sourceLevel],
            ['targetLevel', $targetLevel],
            ['upgradeExpression', 'totalSales >= 10000 and orderCount >= 50'],
            ['description', '新增等级升级规则，用于刺激销售增长'],
        ];
    }

    /**
     * 创建真实的 DistributorLevel 实体对象
     */
    private static function createDistributorLevel(string $name): DistributorLevel
    {
        $level = new DistributorLevel();
        $level->setName($name);

        return $level;
    }

    /**
     * TC-002：验证实体实现 Stringable 接口
     *
     * 确保实体可以通过 __toString() 方法转换为字符串
     */
    public function testEntityImplementsStringable(): void
    {
        $this->assertTrue(
            is_a(DistributorLevelUpgradeRule::class, \Stringable::class, true),
            'DistributorLevelUpgradeRule 必须实现 Stringable 接口'
        );
    }

    /**
     * TC-010：验证 __toString() 方法的格式
     *
     * 确保实体的字符串表示包含源等级、目标等级和表达式信息
     */
    public function testToStringFormat(): void
    {
        // Arrange
        $entity = new DistributorLevelUpgradeRule();

        $sourceLevel = self::createDistributorLevel('1级分销员');
        $targetLevel = self::createDistributorLevel('2级分销员');
        $expression = 'totalSales >= 10000';

        $entity->setSourceLevel($sourceLevel);
        $entity->setTargetLevel($targetLevel);
        $entity->setUpgradeExpression($expression);

        // Act
        $stringRepresentation = (string) $entity;

        // Assert
        $this->assertStringContainsString('UpgradeRule', $stringRepresentation);
        $this->assertStringContainsString('1级分销员', $stringRepresentation);
        $this->assertStringContainsString('2级分销员', $stringRepresentation);
        $this->assertStringContainsString('totalSales >= 10000', $stringRepresentation);
    }

    /**
     * TC-011：验证方法链式调用
     *
     * 确保 setter 方法支持流畅接口（Fluent Interface）
     */
    public function testFluentInterfaceSupport(): void
    {
        // Arrange
        $entity = new DistributorLevelUpgradeRule();
        $sourceLevel = self::createDistributorLevel('1级分销员');
        $targetLevel = self::createDistributorLevel('2级分销员');
        $expression = 'sales >= 5000';
        $description = 'Test rule';

        // Act & Assert - 链式调用
        $result = $entity
            ->setSourceLevel($sourceLevel)
            ->setTargetLevel($targetLevel)
            ->setUpgradeExpression($expression)
            ->setIsEnabled(true)
            ->setDescription($description);

        $this->assertSame($entity, $result);
        $this->assertSame($sourceLevel, $entity->getSourceLevel());
        $this->assertSame($targetLevel, $entity->getTargetLevel());
        $this->assertSame($expression, $entity->getUpgradeExpression());
        $this->assertTrue($entity->isEnabled());
        $this->assertSame($description, $entity->getDescription());
    }

    /**
     * TC-012：验证默认值
     *
     * 测试实体创建时的默认值
     */
    public function testDefaultValues(): void
    {
        // Arrange
        $entity = new DistributorLevelUpgradeRule();

        // Assert - 默认值为 true
        $this->assertTrue($entity->isEnabled(), '默认情况下 isEnabled 应该为 true');

        // Assert - 默认值为 null
        $this->assertNull($entity->getDescription(), '默认情况下 description 应该为 null');
    }
}
