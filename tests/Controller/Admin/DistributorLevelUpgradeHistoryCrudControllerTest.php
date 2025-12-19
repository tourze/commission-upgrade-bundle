<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ReflectionClass;
use Tourze\CommissionUpgradeBundle\Controller\Admin\DistributorLevelUpgradeHistoryCrudController;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeHistory;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * DistributorLevelUpgradeHistoryCrudController 集成测试
 *
 * 测试分销员等级升级历史管理后台控制器的基本功能。
 * 注意：该CRUD是只读的，不需要测试新增/编辑功能。
 *
 * @internal
 */
#[CoversClass(DistributorLevelUpgradeHistoryCrudController::class)]
#[RunTestsInSeparateProcesses]
final class DistributorLevelUpgradeHistoryCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(DistributorLevelUpgradeHistoryCrudController::class);
    }

    /**
     * 提供列表页面表头测试数据
     *
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '分销员' => ['分销员'];
        yield '原等级' => ['原等级'];
        yield '新等级' => ['新等级'];
        yield '满足的条件' => ['满足的条件'];
        yield '升级时间' => ['升级时间'];
    }

    /**
     * 提供新建页面字段测试数据
     * 由于该CRUD是只读的，不支持新增功能，但需要至少提供一个虚拟数据以通过基类测试
     *
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'id' => ['id'];
    }

    /**
     * 提供编辑页面字段测试数据
     * 由于该CRUD是只读的，不支持编辑功能，但需要至少提供一个虚拟数据以通过基类测试
     *
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'id' => ['id'];
    }

    /**
     * 测试控制器继承正确的基类
     */
    public function testControllerExtendsAbstractCrudController(): void
    {
        $reflection = new ReflectionClass(DistributorLevelUpgradeHistoryCrudController::class);

        $this->assertTrue(
            $reflection->isSubclassOf(AbstractCrudController::class),
            'DistributorLevelUpgradeHistoryCrudController必须继承AbstractCrudController'
        );
    }

    /**
     * 测试控制器是final的
     */
    public function testControllerIsFinal(): void
    {
        $reflection = new ReflectionClass(DistributorLevelUpgradeHistoryCrudController::class);

        $this->assertTrue(
            $reflection->isFinal(),
            'DistributorLevelUpgradeHistoryCrudController应该被声明为final'
        );
    }

    /**
     * 测试getEntityFqcn方法返回正确的实体FQCN
     */
    public function testGetEntityFqcnReturnsCorrectEntity(): void
    {
        $entityFqcn = DistributorLevelUpgradeHistoryCrudController::getEntityFqcn();

        $this->assertSame(
            DistributorLevelUpgradeHistory::class,
            $entityFqcn,
            'getEntityFqcn必须返回DistributorLevelUpgradeHistory的FQCN'
        );
    }

    /**
     * 测试configureFields方法存在并返回iterable
     */
    public function testConfigureFieldsReturnsFields(): void
    {
        $reflection = new ReflectionClass(DistributorLevelUpgradeHistoryCrudController::class);

        $this->assertTrue(
            $reflection->hasMethod('configureFields'),
            'configureFields方法必须存在'
        );

        $method = $reflection->getMethod('configureFields');
        $this->assertTrue(
            $method->isPublic(),
            'configureFields方法必须是public'
        );

        // 验证方法返回iterable
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'configureFields必须声明返回类型');
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('iterable', $returnType->getName(), 'configureFields必须返回iterable');
    }

    /**
     * 验证返回正确的实体FQCN
     */
    public function testGetEntityFqcnReturnsCorrectEntityClass(): void
    {
        $entityFqcn = DistributorLevelUpgradeHistoryCrudController::getEntityFqcn();

        $this->assertEquals(
            DistributorLevelUpgradeHistory::class,
            $entityFqcn,
            'getEntityFqcn必须返回DistributorLevelUpgradeHistory的完全限定名'
        );
    }

    /**
     * 测试configureCrud方法存在
     */
    public function testConfigureCrudMethodExists(): void
    {
        $reflection = new ReflectionClass(DistributorLevelUpgradeHistoryCrudController::class);

        $this->assertTrue(
            $reflection->hasMethod('configureCrud'),
            'configureCrud方法必须存在'
        );

        $method = $reflection->getMethod('configureCrud');
        $this->assertTrue(
            $method->isPublic(),
            'configureCrud方法必须是public'
        );
    }

    /**
     * 测试configureActions方法存在
     */
    public function testConfigureActionsMethodExists(): void
    {
        $reflection = new ReflectionClass(DistributorLevelUpgradeHistoryCrudController::class);

        $this->assertTrue(
            $reflection->hasMethod('configureActions'),
            'configureActions方法必须存在'
        );

        $method = $reflection->getMethod('configureActions');
        $this->assertTrue(
            $method->isPublic(),
            'configureActions方法必须是public'
        );
    }

    /**
     * 测试configureFilters方法存在
     */
    public function testConfigureFiltersMethodExists(): void
    {
        $reflection = new ReflectionClass(DistributorLevelUpgradeHistoryCrudController::class);

        $this->assertTrue(
            $reflection->hasMethod('configureFilters'),
            'configureFilters方法必须存在'
        );

        $method = $reflection->getMethod('configureFilters');
        $this->assertTrue(
            $method->isPublic(),
            'configureFilters方法必须是public'
        );
    }

    /**
     * 测试控制器有AdminCrud属性
     */
    public function testControllerHasAdminCrudAttribute(): void
    {
        $reflection = new ReflectionClass(DistributorLevelUpgradeHistoryCrudController::class);
        $attributes = $reflection->getAttributes();

        $hasAdminCrudAttribute = false;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'AdminCrud')) {
                $hasAdminCrudAttribute = true;
                break;
            }
        }

        $this->assertTrue(
            $hasAdminCrudAttribute,
            'Controller应该有AdminCrud属性'
        );
    }

    /**
     * 测试控制器正确命名空间
     */
    public function testControllerHasCorrectNamespace(): void
    {
        $reflection = new ReflectionClass(DistributorLevelUpgradeHistoryCrudController::class);

        $this->assertEquals(
            'Tourze\CommissionUpgradeBundle\Controller\Admin',
            $reflection->getNamespaceName(),
            '控制器命名空间必须正确'
        );
    }
}
