<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\CommissionLevelBundle\DataFixtures\DistributorLevelFixtures;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeRule;

/**
 * 分销员等级升级规则测试数据.
 *
 * 该Fixture提供基础的升级规则测试数据。
 * 需要在关联的DistributorLevel的Fixtures加载后执行。
 */
#[When(env: 'test')]
#[When(env: 'dev')]
class DistributorLevelUpgradeRuleFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * Fixture参考常量.
     */
    public const RULE_LEVEL_1_TO_2 = 'upgrade-rule-level-1-to-2';
    public const RULE_LEVEL_2_TO_3 = 'upgrade-rule-level-2-to-3';

    public function load(ObjectManager $manager): void
    {
        // 获取依赖的 fixtures 数据
        $level1 = $this->getReference(DistributorLevelFixtures::LEVEL_1, DistributorLevel::class);
        $level2 = $this->getReference(DistributorLevelFixtures::LEVEL_2, DistributorLevel::class);
        $level3 = $this->getReference(DistributorLevelFixtures::LEVEL_3, DistributorLevel::class);

        // 创建等级1到等级2的升级规则
        $rule1to2 = new DistributorLevelUpgradeRule();
        $rule1to2->setSourceLevel($level1);
        $rule1to2->setTargetLevel($level2);
        $rule1to2->setUpgradeExpression('settledCommissionAmount >= 5000 and inviteeCount >= 5');
        $rule1to2->setIsEnabled(true);
        $rule1to2->setDescription('从等级1升级到等级2的规则：需要结算佣金达到5000元且邀请人数达到5人');
        $manager->persist($rule1to2);
        $this->addReference(self::RULE_LEVEL_1_TO_2, $rule1to2);

        // 创建等级2到等级3的升级规则
        $rule2to3 = new DistributorLevelUpgradeRule();
        $rule2to3->setSourceLevel($level2);
        $rule2to3->setTargetLevel($level3);
        $rule2to3->setUpgradeExpression('settledCommissionAmount >= 20000 and inviteeCount >= 20');
        $rule2to3->setIsEnabled(true);
        $rule2to3->setDescription('从等级2升级到等级3的规则：需要结算佣金达到20000元且邀请人数达到20人');
        $manager->persist($rule2to3);
        $this->addReference(self::RULE_LEVEL_2_TO_3, $rule2to3);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            DistributorLevelFixtures::class,
        ];
    }
}
