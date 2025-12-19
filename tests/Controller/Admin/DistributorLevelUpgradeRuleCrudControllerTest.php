<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommissionUpgradeBundle\Controller\Admin\DistributorLevelUpgradeRuleCrudController;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeRule;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * DistributorLevelUpgradeRuleCrudController 集成测试.
 *
 * @internal
 */
#[CoversClass(DistributorLevelUpgradeRuleCrudController::class)]
#[RunTestsInSeparateProcesses]
final class DistributorLevelUpgradeRuleCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(DistributorLevelUpgradeRuleCrudController::class);
    }

    /**
     * 验证控制器继承正确的基类.
     */
    public function testControllerExtendsAbstractCrudController(): void
    {
        $reflection = new \ReflectionClass(DistributorLevelUpgradeRuleCrudController::class);

        $this->assertTrue(
            $reflection->isSubclassOf(AbstractCrudController::class),
            'DistributorLevelUpgradeRuleCrudController 必须继承 AbstractCrudController'
        );
    }

    /**
     * 验证 configureFields 方法存在并返回 iterable.
     */
    public function testConfigureFieldsReturnsFields(): void
    {
        $reflection = new \ReflectionClass(DistributorLevelUpgradeRuleCrudController::class);

        $this->assertTrue(
            $reflection->hasMethod('configureFields'),
            'configureFields 方法必须存在'
        );

        $method = $reflection->getMethod('configureFields');

        // 验证方法是 public
        $this->assertTrue(
            $method->isPublic(),
            'configureFields 方法必须是 public'
        );

        // 验证方法返回 iterable
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'configureFields 必须声明返回类型');
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('iterable', $returnType->getName(), 'configureFields 必须返回 iterable');
    }

    /**
     * 验证返回正确的实体 FQCN.
     */
    public function testGetEntityFqcnReturnsCorrectEntity(): void
    {
        $entityFqcn = DistributorLevelUpgradeRuleCrudController::getEntityFqcn();

        $this->assertEquals(
            DistributorLevelUpgradeRule::class,
            $entityFqcn,
            'getEntityFqcn 必须返回 DistributorLevelUpgradeRule 的完全限定名'
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '源等级' => ['源等级'];
        yield '目标等级' => ['目标等级'];
        yield '升级条件' => ['升级条件'];
        yield '是否启用' => ['是否启用'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'sourceLevel' => ['sourceLevel'];
        yield 'targetLevel' => ['targetLevel'];
        yield 'upgradeExpression' => ['upgradeExpression'];
        yield 'isEnabled' => ['isEnabled'];
        yield 'description' => ['description'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'sourceLevel' => ['sourceLevel'];
        yield 'targetLevel' => ['targetLevel'];
        yield 'upgradeExpression' => ['upgradeExpression'];
        yield 'isEnabled' => ['isEnabled'];
        yield 'description' => ['description'];
    }

    /**
     * 验证必填字段的验证错误.
     */
    public function testValidationErrors(): void
    {
        $client = $this->createAuthenticatedClient();

        // 访问创建页面
        $crawler = $client->request('GET', $this->generateAdminUrl('new'));
        $this->assertResponseIsSuccessful();

        // 获取表单并提交空内容
        $form = $crawler->selectButton('Create')->form();
        $crawler = $client->submit($form);

        // 验证返回 422 Unprocessable Entity（表单验证失败）
        $this->assertResponseStatusCodeSame(422);
    }
}
