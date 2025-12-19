<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\CommissionLevelBundle\DataFixtures\DistributorLevelFixtures;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\Entity\DirectUpgradeRule;

/**
 * 分销员直升规则测试数据.
 *
 * 该Fixture提供基础的直升规则测试数据。
 * 需要在关联的DistributorLevel的Fixtures加载后执行。
 */
#[When(env: 'test')]
#[When(env: 'dev')]
class DirectUpgradeRuleFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * Fixture参考常量.
     */
    public const DIRECT_RULE_TO_VIP = 'direct-upgrade-rule-to-vip';
    public const DIRECT_RULE_TO_LEVEL_1 = 'direct-upgrade-rule-to-level-1';

    public function load(ObjectManager $manager): void
    {
        // 获取依赖的 fixtures 数据
        $vipLevel = $this->getReference(DistributorLevelFixtures::LEVEL_VIP, DistributorLevel::class);
        $level1 = $this->getReference(DistributorLevelFixtures::LEVEL_1, DistributorLevel::class);

        // 创建直升到VIP等级的规则
        $directRuleToVip = new DirectUpgradeRule();
        $directRuleToVip->setTargetLevel($vipLevel);
        $directRuleToVip->setUpgradeExpression('settledCommissionAmount >= 50000 and inviteeCount >= 30');
        $directRuleToVip->setPriority(100);
        $directRuleToVip->setIsEnabled(true);
        $directRuleToVip->setDescription('直升到VIP等级的规则：需要结算佣金达到50000元且邀请人数达到30人');
        $directRuleToVip->setMinLevelRequirement(1);
        $manager->persist($directRuleToVip);
        $this->addReference(self::DIRECT_RULE_TO_VIP, $directRuleToVip);

        // 创建直升到一级分销员的规则
        $directRuleToLevel1 = new DirectUpgradeRule();
        $directRuleToLevel1->setTargetLevel($level1);
        $directRuleToLevel1->setUpgradeExpression('settledCommissionAmount >= 10000 and inviteeCount >= 10');
        $directRuleToLevel1->setPriority(50);
        $directRuleToLevel1->setIsEnabled(true);
        $directRuleToLevel1->setDescription('直升到一级分销员的规则：需要结算佣金达到10000元且邀请人数达到10人');
        $manager->persist($directRuleToLevel1);
        $this->addReference(self::DIRECT_RULE_TO_LEVEL_1, $directRuleToLevel1);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            DistributorLevelFixtures::class,
        ];
    }
}
