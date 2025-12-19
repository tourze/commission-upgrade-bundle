<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeHistory;

/**
 * 分销员等级升级历史后台管理控制器（只读）.
 */
#[AdminCrud(routePath: '/commission-upgrade/level-upgrade-history', routeName: 'commission_upgrade_level_upgrade_history')]
final class DistributorLevelUpgradeHistoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DistributorLevelUpgradeHistory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('升级历史')
            ->setEntityLabelInPlural('升级历史')
            ->setSearchFields(['id', 'satisfiedExpression'])
            ->setDefaultSort(['upgradeTime' => 'DESC'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('distributor')
            ->add('previousLevel')
            ->add('newLevel')
            ->add('upgradeTime')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID');

        yield AssociationField::new('distributor', '分销员')
            ->formatValue(static fn ($value) => sprintf('#%s', $value?->getId()))
        ;

        yield AssociationField::new('previousLevel', '原等级')
            ->formatValue(static fn ($value) => $value?->getName())
        ;

        yield AssociationField::new('newLevel', '新等级')
            ->formatValue(static fn ($value) => $value?->getName())
        ;

        yield TextField::new('satisfiedExpression', '满足的条件')
            ->setMaxLength(50)
            ->hideOnDetail()
        ;

        yield TextField::new('satisfiedExpression', '满足的条件表达式')
            ->onlyOnDetail()
        ;

        yield ArrayField::new('contextSnapshot', '上下文快照')
            ->onlyOnDetail()
            ->setHelp('升级时的变量值快照')
        ;

        yield AssociationField::new('triggeringWithdrawLedger', '触发的提现流水')
            ->formatValue(static fn ($value) => $value ? sprintf('#%s', $value->getId()) : '-')
            ->onlyOnDetail()
        ;

        yield DateTimeField::new('upgradeTime', '升级时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;

        yield DateTimeField::new('createTime', '创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->onlyOnDetail()
        ;
    }
}
