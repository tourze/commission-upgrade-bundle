<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AsyncContracts\AsyncMessageInterface;
use Tourze\CommissionUpgradeBundle\Message\DistributorUpgradeCheckMessage;

/**
 * 契约测试：验证 DistributorUpgradeCheckMessage 实现 AsyncMessageInterface
 *
 * 对应任务：T009 [P] [US1]
 * 测试目标：确保消息类正确实现 AsyncMessageInterface 标记接口
 */
#[CoversClass(DistributorUpgradeCheckMessage::class)]
final class AsyncMessageInterfaceContractTest extends TestCase
{
    /**
     * TC-005：接口实现验证
     *
     * 验证 DistributorUpgradeCheckMessage 实现了 AsyncMessageInterface 接口
     */
    public function testDistributorUpgradeCheckMessageImplementsAsyncMessageInterface(): void
    {
        // Arrange
        $message = new DistributorUpgradeCheckMessage(
            distributorId: '12345'
        );

        // Assert
        $this->assertInstanceOf(
            AsyncMessageInterface::class,
            $message,
            'DistributorUpgradeCheckMessage 必须实现 AsyncMessageInterface 接口'
        );
    }

    /**
     * 验证接口实现的完整性（接口存在且是标记接口）
     */
    public function testAsyncMessageInterfaceIsMarkerInterface(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(AsyncMessageInterface::class);

        // Assert
        $this->assertTrue(
            $reflection->isInterface(),
            'AsyncMessageInterface 必须是接口'
        );

        $this->assertCount(
            0,
            $reflection->getMethods(),
            'AsyncMessageInterface 应该是标记接口（无方法定义）'
        );
    }
}
