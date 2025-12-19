<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\CommissionUpgradeBundle\Message\DistributorUpgradeCheckMessage;

/**
 * 单元测试：验证 DistributorUpgradeCheckMessage 消息创建与序列化
 *
 * 测试目标：验证消息对象的创建、属性访问、序列化/反序列化、不可变性
 */
#[CoversClass(DistributorUpgradeCheckMessage::class)]
final class DistributorUpgradeCheckMessageTest extends TestCase
{
    /**
     * TC-001：正常消息创建
     */
    public function testCreateMessage(): void
    {
        // Arrange & Act
        $message = new DistributorUpgradeCheckMessage(
            distributorId: '12345'
        );

        // Assert
        $this->assertSame('12345', $message->distributorId, '分销员 ID 应该正确存储');
    }

    /**
     * TC-002：消息序列化与反序列化
     */
    public function testMessageSerializationAndDeserialization(): void
    {
        // Arrange
        $original = new DistributorUpgradeCheckMessage(
            distributorId: '12345'
        );

        // Act
        $serialized = serialize($original);
        $restored = unserialize($serialized);

        // Assert
        $this->assertInstanceOf(
            DistributorUpgradeCheckMessage::class,
            $restored,
            '反序列化后应该是相同类型'
        );
        $this->assertSame(
            $original->distributorId,
            $restored->distributorId,
            '反序列化后 distributorId 应该一致'
        );
    }

    /**
     * TC-003：消息不可变性（readonly 验证）
     *
     * 注意：PHP 8.2+ readonly 属性会在运行时强制不可变性
     * 尝试修改会抛出 Error
     */
    public function testMessageImmutability(): void
    {
        // Arrange
        $message = new DistributorUpgradeCheckMessage(
            distributorId: '12345'
        );

        // Assert
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        // Act - 尝试修改 readonly 属性应该抛出异常
        $message->distributorId = '99999';
    }

    /**
     * TC-004：验证消息的轻量化（仅包含标量类型）
     */
    public function testMessageIsLightweight(): void
    {
        // Arrange
        $message = new DistributorUpgradeCheckMessage(
            distributorId: '12345'
        );

        // Act
        $reflection = new \ReflectionClass($message);
        $properties = $reflection->getProperties();

        // Assert
        $this->assertCount(1, $properties, '消息应该只包含 1 个属性');

        foreach ($properties as $property) {
            $type = $property->getType();
            $this->assertNotNull($type, "属性 {$property->getName()} 必须有类型声明");

            // 验证类型是标量或可空标量
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : '';
            $this->assertTrue(
                in_array($typeName, ['int', 'string', 'bool', 'float'], true),
                "属性 {$property->getName()} 必须是标量类型，当前是 {$typeName}"
            );
        }
    }

    /**
     * TC-005：验证传入空字符串 ID 时抛出异常
     */
    public function testThrowsExceptionWhenDistributorIdIsEmpty(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Distributor ID must not be empty');

        // Act
        new DistributorUpgradeCheckMessage(distributorId: '');
    }

    /**
     * TC-006：验证传入零字符串 ID 时抛出异常
     */
    public function testThrowsExceptionWhenDistributorIdIsZeroString(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Distributor ID must not be empty');

        // Act
        new DistributorUpgradeCheckMessage(distributorId: '0');
    }
}
