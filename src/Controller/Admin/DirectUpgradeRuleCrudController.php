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
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\CommissionUpgradeBundle\Entity\DirectUpgradeRule;

/**
 * 直升规则后台管理控制器.
 */
#[AdminCrud(routePath: '/commission-upgrade/direct-upgrade-rule', routeName: 'commission_upgrade_direct_upgrade_rule')]
final class DirectUpgradeRuleCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DirectUpgradeRule::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('直升规则')
            ->setEntityLabelInPlural('直升规则')
            ->setPageTitle('index', '直升规则列表')
            ->setPageTitle('new', '新增直升规则')
            ->setPageTitle('edit', '编辑直升规则')
            ->setPageTitle('detail', '查看直升规则')
            ->setHelp('index', '管理分销员直升规则，支持跨等级升级')
            ->setSearchFields(['upgradeExpression', 'description'])
            ->setDefaultSort(['priority' => 'DESC', 'targetLevel' => 'DESC'])
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

        yield AssociationField::new('targetLevel', '目标等级')
            ->setRequired(true)
            ->setHelp('直升的目标等级')
            ->formatValue(static fn ($value) => $value ? sprintf('%s (等级%d)', $value->getName(), $value->getLevel()) : '')
            ->setFormTypeOption('choice_label', function ($distributorLevel) {
                return sprintf('%s (等级%d)', $distributorLevel->getName(), $distributorLevel->getLevel());
            })
        ;

//        yield IntegerField::new('priority', '优先级')
//            ->setRequired(true)
//            ->setHelp('数值越大优先级越高，范围：0-999')
//            ->setFormTypeOption('attr', [
//                'min' => 0,
//                'max' => 999,
//            ])
//        ;

//        yield IntegerField::new('minLevelRequirement', '最低等级要求')
//            ->setRequired(false)
//            ->setHelp('可选，限制只有指定等级以上的分销员才能使用此直升规则')
//            ->setFormTypeOption('attr', [
//                'min' => 0,
//            ])
//        ;

        yield TextareaField::new('upgradeExpression', '直升条件表达式')
            ->setRequired(true)
            ->setHelp('支持变量: withdrawnAmount：已提现佣金, settledCommissionAmount：已结算总佣金, inviteeCount：邀请人数, orderCount：产生佣金的订单数, activeInviteeCount：最近 30 天有订单的活跃邀请数<br>示例: settledCommissionAmount >= 10000 and inviteeCount >= 50')
            ->setFormTypeOption('attr', [
                'rows' => 5,
                'placeholder' => 'settledCommissionAmount >= 10000 and inviteeCount >= 50',
            ])
            ->hideOnIndex()
        ;

        yield TextField::new('upgradeExpression', '直升条件')
            ->onlyOnIndex()
            ->setMaxLength(50)
        ;

        yield BooleanField::new('isEnabled', '是否启用')
            ->setHelp('禁用后该直升规则不生效')
        ;

        yield TextareaField::new('description', '备注说明')
            ->setRequired(false)
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
            ->add(TextFilter::new('targetLevel', '目标等级名称'))
//            ->add(NumericFilter::new('priority', '优先级'))
//            ->add(NumericFilter::new('minLevelRequirement', '最低等级要求'))
            ->add(BooleanFilter::new('isEnabled', '是否启用'))
            ->add(TextFilter::new('upgradeExpression', '升级条件'))
        ;
    }
}