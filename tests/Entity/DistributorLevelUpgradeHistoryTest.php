<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeHistory;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * 单元测试：DistributorLevelUpgradeHistory Entity.
 *
 * 验证分销员等级升级历史实体的基本功能。
 */
#[CoversClass(DistributorLevelUpgradeHistory::class)]
final class DistributorLevelUpgradeHistoryTest extends AbstractEntityTestCase
{
    /**
     * 创建被测实体的实例.
     */
    protected function createEntity(): object
    {
        return new DistributorLevelUpgradeHistory();
    }

    /**
     * 提供属性及其样本值的 Data Provider.
     *
     * @return array<array{string, mixed}>
     */
    public static function propertiesProvider(): array
    {
        return [
            ['satisfiedExpression', 'test_expression_string'],
            ['contextSnapshot', ['key' => 'value', 'nested' => ['data' => true]]],
            ['triggerType', 'manual'],
        ];
    }

    /**
     * 验证 isManualUpgrade 方法.
     */
    public function testIsManualUpgrade(): void
    {
        // Arrange
        $entity = $this->createEntity();

        // Act & Assert - 默认为 'auto'
        $this->assertFalse(
            $entity->isManualUpgrade(),
            '新创建的实体应该是自动升级（trigger_type=auto）'
        );

        // Act - 设置为 manual
        $entity->setTriggerType('manual');

        // Assert
        $this->assertTrue(
            $entity->isManualUpgrade(),
            '设置 trigger_type 为 manual 后，isManualUpgrade 应返回 true'
        );

        // Act - 设置回 auto
        $entity->setTriggerType('auto');

        // Assert
        $this->assertFalse(
            $entity->isManualUpgrade(),
            '设置 trigger_type 为 auto 后，isManualUpgrade 应返回 false'
        );
    }

    /**
     * 验证 setTriggerType 的参数验证.
     */
    public function testSetTriggerTypeValidation(): void
    {
        // Arrange
        $entity = $this->createEntity();

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid trigger type. Must be "auto" or "manual".');

        // Act
        $entity->setTriggerType('invalid_type');
    }

    /**
     * 验证 contextSnapshot 的默认值为空数组.
     */
    public function testContextSnapshotDefaultValue(): void
    {
        // Arrange
        $entity = $this->createEntity();

        // Act
        $snapshot = $entity->getContextSnapshot();

        // Assert
        $this->assertIsArray($snapshot);
        $this->assertEmpty($snapshot);
    }

    /**
     * 验证 contextSnapshot 支持设置和获取复杂数据.
     */
    public function testContextSnapshotComplexData(): void
    {
        // Arrange
        $entity = $this->createEntity();
        $complexData = [
            'user_id' => 12345,
            'commission_value' => 9999.99,
            'conditions' => ['is_active' => true, 'level_requirements' => ['min_sales' => 5000]],
            'timestamp' => '2024-01-01T12:00:00Z',
        ];

        // Act
        $entity->setContextSnapshot($complexData);

        // Assert
        $this->assertSame($complexData, $entity->getContextSnapshot());
    }

    /**
     * 验证 triggeringWithdrawLedger 的默认值为 null.
     */
    public function testTriggeringWithdrawLedgerDefaultValue(): void
    {
        // Arrange
        $entity = $this->createEntity();

        // Act
        $ledger = $entity->getTriggeringWithdrawLedger();

        // Assert
        $this->assertNull($ledger);
    }

    /**
     * 验证 operator 的默认值为 null.
     */
    public function testOperatorDefaultValue(): void
    {
        // Arrange
        $entity = $this->createEntity();

        // Act
        $operator = $entity->getOperator();

        // Assert
        $this->assertNull($operator);
    }

    /**
     * 验证 triggerType 的默认值为 'auto'.
     */
    public function testTriggerTypeDefaultValue(): void
    {
        // Arrange
        $entity = $this->createEntity();

        // Act
        $triggerType = $entity->getTriggerType();

        // Assert
        $this->assertSame('auto', $triggerType);
    }

    /**
     * TC-001：验证实体类存在.
     *
     * 验证 DistributorLevelUpgradeHistory 实体类存在并实现 Stringable 接口.
     */
    public function testEntityIsInstanceOfDistributorLevelUpgradeHistory(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(DistributorLevelUpgradeHistory::class);

        // Assert
        $this->assertTrue(
            $reflection->isInstantiable(),
            'DistributorLevelUpgradeHistory 必须是可实例化的类'
        );

        // 验证实现 Stringable 接口
        $this->assertTrue(
            $reflection->implementsInterface(\Stringable::class),
            'DistributorLevelUpgradeHistory 必须实现 Stringable 接口'
        );

        // 验证 __toString 方法存在
        $this->assertTrue(
            $reflection->hasMethod('__toString'),
            'DistributorLevelUpgradeHistory 必须有 __toString 方法'
        );
    }

    /**
     * TC-002：验证必需的属性存在.
     *
     * 验证 DistributorLevelUpgradeHistory 包含所有必需的私有属性.
     */
    public function testEntityHasRequiredProperties(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(DistributorLevelUpgradeHistory::class);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PRIVATE);

        // 预期的私有属性列表
        $expectedProperties = [
            'distributor',
            'previousLevel',
            'newLevel',
            'satisfiedExpression',
            'contextSnapshot',
            'triggeringWithdrawLedger',
            'upgradeTime',
            'triggerType',
            'operator',
        ];

        $propertyNames = array_map(fn ($prop) => $prop->getName(), $properties);

        // Assert
        foreach ($expectedProperties as $expectedProperty) {
            $this->assertContains(
                $expectedProperty,
                $propertyNames,
                sprintf('DistributorLevelUpgradeHistory 必须有私有属性 $%s', $expectedProperty)
            );
        }
    }
}
