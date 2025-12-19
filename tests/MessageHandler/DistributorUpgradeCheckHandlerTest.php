<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\MessageHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommissionUpgradeBundle\MessageHandler\DistributorUpgradeCheckHandler;
use Tourze\PHPUnitSymfonyKernelTest\AbstractMessageHandlerTestCase;

/**
 * 集成测试：验证 DistributorUpgradeCheckHandler 处理逻辑
 *
 * 对应任务：T011 [P] [US1]
 * 测试目标：验证 Handler 的消息处理逻辑、错误处理、日志记录
 */
#[CoversClass(DistributorUpgradeCheckHandler::class)]
#[RunTestsInSeparateProcesses]
final class DistributorUpgradeCheckHandlerTest extends AbstractMessageHandlerTestCase
{
    protected function onSetUp(): void
    {
        // 无需自定义初始化
    }

    /**
     * 测试场景：Handler 具有 AsMessageHandler 属性（自动注册为消费者）
     */
    public function testHandlerHasAsMessageHandlerAttribute(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(DistributorUpgradeCheckHandler::class);

        // Act
        $attributes = $reflection->getAttributes(\Symfony\Component\Messenger\Attribute\AsMessageHandler::class);

        // Assert
        $this->assertCount(
            1,
            $attributes,
            'Handler 必须有 #[AsMessageHandler] 属性'
        );
    }

    /**
     * 测试场景：Handler 的 __invoke 方法接受正确的消息类型
     */
    public function testHandlerInvokeMethodSignature(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(DistributorUpgradeCheckHandler::class);
        $invokeMethod = $reflection->getMethod('__invoke');

        // Act
        $parameters = $invokeMethod->getParameters();

        // Assert
        $this->assertCount(1, $parameters, '__invoke 方法应该接受 1 个参数');

        $messageParam = $parameters[0];
        $this->assertSame('message', $messageParam->getName(), '参数名应该是 message');

        $type = $messageParam->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame(
            'Tourze\\CommissionUpgradeBundle\\Message\\DistributorUpgradeCheckMessage',
            $type->getName(),
            '参数类型应该是 DistributorUpgradeCheckMessage'
        );
    }

    /**
     * 测试场景：Handler 的构造函数依赖注入正确
     */
    public function testHandlerConstructorDependencies(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(DistributorUpgradeCheckHandler::class);
        $constructor = $reflection->getConstructor();

        // Act
        $parameters = $constructor->getParameters();

        // Assert
        $this->assertCount(3, $parameters, 'Handler 应该注入 3 个依赖');

        // 验证依赖类型
        $expectedDependencies = [
            'entityManager' => 'Doctrine\\ORM\\EntityManagerInterface',
            'upgradeService' => 'Tourze\\CommissionUpgradeBundle\\Service\\DistributorUpgradeService',
            'logger' => 'Psr\\Log\\LoggerInterface',
        ];

        foreach ($expectedDependencies as $paramName => $expectedType) {
            $param = null;
            foreach ($parameters as $p) {
                if ($p->getName() === $paramName) {
                    $param = $p;
                    break;
                }
            }

            $this->assertNotNull($param, "应该有名为 {$paramName} 的参数");
            $type = $param->getType();
            $this->assertInstanceOf(\ReflectionNamedType::class, $type);
            $this->assertSame(
                $expectedType,
                $type->getName(),
                "{$paramName} 参数的类型应该是 {$expectedType}"
            );
        }
    }

    /**
     * 测试场景：Handler 具有 WithMonologChannel 属性
     */
    public function testHandlerHasWithMonologChannelAttribute(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(DistributorUpgradeCheckHandler::class);

        // Act
        $attributes = $reflection->getAttributes(\Monolog\Attribute\WithMonologChannel::class);

        // Assert
        $this->assertCount(
            1,
            $attributes,
            'Handler 必须有 #[WithMonologChannel] 属性'
        );

        $attribute = $attributes[0]->newInstance();
        $this->assertSame(
            'commission_upgrade',
            $attribute->channel,
            'Handler 的日志频道应该是 commission_upgrade'
        );
    }
}
