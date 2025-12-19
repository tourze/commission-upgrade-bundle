<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\CommissionUpgradeBundle\DTO\ManualUpgradeCheckRequest;

/**
 * 手动升级检测表单类型.
 *
 * 用于用户输入分销员ID的表单,用于手动升级功能的第一步"检测升级条件"
 */
class ManualUpgradeCheckType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('distributorId', IntegerType::class, [
                'label' => '分销员ID',
                'required' => true,
                'attr' => [
                    'placeholder' => '请输入分销员ID（例如：12345）',
                    'class' => 'form-control',
                    'min' => 1,
                    'autofocus' => true,
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => '分销员ID不能为空',
                    ]),
                    new Assert\Type([
                        'type' => 'integer',
                        'message' => '分销员ID必须是整数',
                    ]),
                    new Assert\Positive([
                        'message' => '分销员ID必须大于0',
                    ]),
                ],
                'help' => '提示：可以从分销员列表页面复制ID',
            ])
            ->add('submit', SubmitType::class, [
                'label' => '检测升级条件',
                'attr' => [
                    'class' => 'btn btn-primary',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ManualUpgradeCheckRequest::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'manual_upgrade_check',
        ]);
    }
}
