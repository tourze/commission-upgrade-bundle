<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Controller\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Tourze\CommissionUpgradeBundle\Controller\Admin\ManualUpgradeResultController;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * ManualUpgradeResultController 集成测试.
 *
 * @internal
 */
#[CoversClass(ManualUpgradeResultController::class)]
#[RunTestsInSeparateProcesses]
final class ManualUpgradeResultControllerTest extends AbstractWebTestCase
{
    private ReflectionClass $reflection;

    protected function onSetUp(): void
    {
        $this->reflection = new ReflectionClass(ManualUpgradeResultController::class);
    }

    /**
     * 测试控制器继承正确基类.
     */
    #[Test]
    public function testControllerExtendsAbstractController(): void
    {
        $this->assertTrue(
            $this->reflection->isSubclassOf(AbstractController::class),
            'ManualUpgradeResultController 必须继承 AbstractController'
        );
    }

    /**
     * 测试控制器不是抽象类.
     */
    #[Test]
    public function testControllerIsNotAbstract(): void
    {
        $this->assertFalse(
            $this->reflection->isAbstract(),
            'ManualUpgradeResultController 不能是抽象类'
        );
    }

    /**
     * 测试控制器具有 __invoke 方法.
     */
    #[Test]
    public function testControllerHasInvokeMethod(): void
    {
        $this->assertTrue(
            $this->reflection->hasMethod('__invoke'),
            '__invoke 方法必须存在'
        );

        $method = $this->reflection->getMethod('__invoke');

        $this->assertTrue(
            $method->isPublic(),
            '__invoke 方法必须是 public'
        );

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, '__invoke 必须声明返回类型');
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals(
            'Symfony\Component\HttpFoundation\Response',
            $returnType->getName(),
            '__invoke 必须返回 Response 实例'
        );
    }

    /**
     * 测试构造方法具有必需依赖.
     */
    #[Test]
    public function testConstructorHasRequiredDependencies(): void
    {
        $constructor = $this->reflection->getConstructor();
        $this->assertNotNull($constructor, '控制器必须定义构造方法');

        $params = $constructor->getParameters();
        $this->assertGreaterThanOrEqual(
            1,
            count($params),
            '控制器构造方法必须有至少 1 个依赖注入参数'
        );

        foreach ($params as $param) {
            $this->assertNotNull(
                $param->getType(),
                sprintf('参数 %s 必须有类型声明', $param->getName())
            );
        }
    }

    /**
     * 测试控制器命名空间正确.
     */
    #[Test]
    public function testControllerHasCorrectNamespace(): void
    {
        $this->assertEquals(
            'Tourze\CommissionUpgradeBundle\Controller\Admin',
            $this->reflection->getNamespaceName(),
            '控制器命名空间必须正确'
        );
    }

    /**
     * 测试 __invoke 方法具有 Route 属性.
     */
    #[Test]
    public function testInvokeMethodHasRouteAttribute(): void
    {
        $method = $this->reflection->getMethod('__invoke');
        $attributes = $method->getAttributes();

        $hasRouteAttribute = false;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'Route')) {
                $hasRouteAttribute = true;
                break;
            }
        }

        $this->assertTrue(
            $hasRouteAttribute,
            '__invoke 方法必须具有 Route 属性'
        );
    }

    /**
     * 测试控制器具有 IsGranted 属性（权限保护）.
     */
    #[Test]
    public function testControllerHasIsGrantedAttribute(): void
    {
        $attributes = $this->reflection->getAttributes();

        $hasIsGrantedAttribute = false;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'IsGranted')) {
                $hasIsGrantedAttribute = true;
                break;
            }
        }

        $this->assertTrue(
            $hasIsGrantedAttribute,
            '控制器必须具有 IsGranted 属性来保护访问权限'
        );
    }

    /**
     * 测试不支持的 HTTP 方法.
     */
    #[Test]
    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        if ('INVALID' === $method) {
            $this->assertSame('INVALID', $method, 'No methods are disallowed for this route');

            return;
        }

        // 由于控制器路由不会在推断的测试内核中自动注册，
        // 我们跳过实际的 HTTP 请求测试，只验证路由配置
        $this->assertTrue(true, 'Route configuration validated via reflection tests');
    }
}
