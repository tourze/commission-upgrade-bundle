<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeRule;

/**
 * 分销员等级升级规则后台管理控制器.
 */
#[AdminCrud(routePath: '/commission-upgrade/level-upgrade-rule', routeName: 'commission_upgrade_level_upgrade_rule')]
final class DistributorLevelUpgradeRuleCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DistributorLevelUpgradeRule::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('升级规则')
            ->setEntityLabelInPlural('升级规则')
            ->setSearchFields(['id', 'upgradeExpression', 'description'])
            ->setDefaultSort(['sourceLevel' => 'ASC'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->hideOnForm()
        ;

        yield AssociationField::new('sourceLevel', '源等级')
            ->setRequired(true)
            ->setHelp('升级前的等级')
            ->formatValue(static fn ($value) => $value?->getName())
            ->setFormTypeOption('choice_label', 'name')
        ;

        yield AssociationField::new('targetLevel', '目标等级')
            ->setRequired(true)
            ->setHelp('升级后的等级（必须高于源等级）')
            ->formatValue(static fn ($value) => $value?->getName())
            ->setFormTypeOption('choice_label', 'name')
        ;

        yield TextareaField::new('upgradeExpression', '升级条件表达式')
            ->setRequired(true)
            ->setHelp('支持变量: withdrawnAmount：已提现佣金, settledCommissionAmount：已结算总佣金, inviteeCount：邀请人数, orderCount：产生佣金的订单数, activeInviteeCount：最近 30 天有订单的活跃邀请数<br>示例: settledCommissionAmount >= 5000 and inviteeCount >= 10')
            ->setFormTypeOption('attr', [
                'rows' => 5,
                'placeholder' => 'settledCommissionAmount >= 5000',
            ])
            ->hideOnIndex()
        ;

        yield TextField::new('upgradeExpression', '升级条件')
            ->onlyOnIndex()
            ->setMaxLength(50)
        ;

        yield BooleanField::new('isEnabled', '是否启用')
            ->setHelp('禁用后该升级规则不生效')
        ;

        yield TextareaField::new('description', '备注说明')
            ->setHelp('用于记录规则用途、变更原因等')
            ->setFormTypeOption('attr', [
                'rows' => 3,
            ])
            ->hideOnIndex()
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('sourceLevel')
            ->add('targetLevel')
            ->add('isEnabled')
        ;
    }
}
