<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Tourze\CommissionUpgradeBundle\DTO\ManualUpgradeCheckRequest;
use Tourze\CommissionUpgradeBundle\Form\ManualUpgradeCheckType;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ManualUpgradeCheckType::class)]
#[RunTestsInSeparateProcesses]
final class ManualUpgradeCheckTypeTest extends AbstractIntegrationTestCase
{
    private FormFactoryInterface $formFactory;

    protected function onSetUp(): void
    {
        $this->formFactory = self::getService(FormFactoryInterface::class);
    }

    public function testFormTypeCanBuildForm(): void
    {
        $formData = [
            'distributorId' => 123,
        ];

        $model = new ManualUpgradeCheckRequest();
        $form = $this->formFactory->create(ManualUpgradeCheckType::class, $model);

        $expected = new ManualUpgradeCheckRequest(123);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($expected->getDistributorId(), $form->getData()->getDistributorId());

        $view = $form->createView();
        $children = $view->children;

        foreach (array_keys($formData) as $key) {
            $this->assertArrayHasKey($key, $children);
        }
    }

    public function testFormTypeConfiguresOptionsCorrectly(): void
    {
        $form = $this->formFactory->create(ManualUpgradeCheckType::class);

        $this->assertInstanceOf(FormInterface::class, $form);
        $this->assertSame(ManualUpgradeCheckRequest::class, $form->getConfig()->getDataClass());
    }

    public function testFormTypeIsNotAbstract(): void
    {
        $reflection = new \ReflectionClass(ManualUpgradeCheckType::class);
        $this->assertFalse($reflection->isAbstract());
    }

    public function testBuildFormShouldAddDistributorIdField(): void
    {
        $form = $this->formFactory->create(ManualUpgradeCheckType::class);

        $this->assertTrue($form->has('distributorId'));
        $this->assertTrue($form->has('submit'));

        $distributorIdConfig = $form->get('distributorId')->getConfig();
        $this->assertSame(IntegerType::class, $distributorIdConfig->getType()->getInnerType()::class);
        $this->assertTrue($distributorIdConfig->getRequired());
        $this->assertSame('分销员ID', $distributorIdConfig->getOption('label'));

        $submitConfig = $form->get('submit')->getConfig();
        $this->assertSame(SubmitType::class, $submitConfig->getType()->getInnerType()::class);
        $this->assertSame('检测升级条件', $submitConfig->getOption('label'));
    }

    public function testConfigureOptionsShouldSetDataClass(): void
    {
        $form = $this->formFactory->create(ManualUpgradeCheckType::class);

        $config = $form->getConfig();
        $this->assertSame(ManualUpgradeCheckRequest::class, $config->getDataClass());
        $this->assertTrue($config->getOption('csrf_protection'));
        $this->assertSame('_token', $config->getOption('csrf_field_name'));
        $this->assertSame('manual_upgrade_check', $config->getOption('csrf_token_id'));
    }
}
