<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommissionUpgradeBundle\Controller\Admin\DirectUpgradeRuleCrudController;
use Tourze\CommissionUpgradeBundle\Entity\DirectUpgradeRule;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * DirectUpgradeRuleCrudController 集成测试.
 *
 * @internal
 */
#[CoversClass(DirectUpgradeRuleCrudController::class)]
#[RunTestsInSeparateProcesses]
final class DirectUpgradeRuleCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(DirectUpgradeRuleCrudController::class);
    }

    /**
     * 验证控制器继承正确的基类.
     */
    public function testControllerExtendsAbstractCrudController(): void
    {
        $reflection = new \ReflectionClass(DirectUpgradeRuleCrudController::class);

        $this->assertTrue(
            $reflection->isSubclassOf(AbstractCrudController::class),
            'DirectUpgradeRuleCrudController 必须继承 AbstractCrudController'
        );
    }

    /**
     * 验证 configureFields 方法存在并返回 iterable.
     */
    public function testConfigureFieldsReturnsFields(): void
    {
        $reflection = new \ReflectionClass(DirectUpgradeRuleCrudController::class);

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
        $entityFqcn = DirectUpgradeRuleCrudController::getEntityFqcn();

        $this->assertEquals(
            DirectUpgradeRule::class,
            $entityFqcn,
            'getEntityFqcn 必须返回 DirectUpgradeRule 的完全限定名'
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '目标等级' => ['目标等级'];
        yield '直升条件' => ['直升条件'];
        yield '是否启用' => ['是否启用'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
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
