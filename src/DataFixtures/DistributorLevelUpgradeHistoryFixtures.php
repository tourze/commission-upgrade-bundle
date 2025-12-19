<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\CommissionDistributorBundle\DataFixtures\DistributorFixtures;
use Tourze\CommissionDistributorBundle\Entity\Distributor;
use Tourze\CommissionLevelBundle\DataFixtures\DistributorLevelFixtures;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeHistory;

/**
 * 分销员等级升级历史测试数据.
 *
 * 该Fixture提供基础的升级历史记录测试数据。
 * 需要在关联的Distributor和DistributorLevel的Fixtures加载后执行。
 */
#[When(env: 'test')]
#[When(env: 'dev')]
class DistributorLevelUpgradeHistoryFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * Fixture参考常量.
     */
    public const HISTORY_AUTO_UPGRADE = 'upgrade-history-auto-upgrade';
    public const HISTORY_MANUAL_UPGRADE = 'upgrade-history-manual-upgrade';

    public function load(ObjectManager $manager): void
    {
        // 获取依赖的 fixtures 数据
        $distributor = $this->getReference(DistributorFixtures::DISTRIBUTOR_LEVEL1, Distributor::class);
        $previousLevel = $this->getReference(DistributorLevelFixtures::LEVEL_1, DistributorLevel::class);
        $newLevel = $this->getReference(DistributorLevelFixtures::LEVEL_2, DistributorLevel::class);
        $highestLevel = $this->getReference(DistributorLevelFixtures::LEVEL_3, DistributorLevel::class);

        // 创建自动升级历史记录
        $autoHistory = new DistributorLevelUpgradeHistory();
        $autoHistory->setDistributor($distributor);
        $autoHistory->setPreviousLevel($previousLevel);
        $autoHistory->setNewLevel($newLevel);
        $autoHistory->setSatisfiedExpression('distributor.performance >= 10000');
        $autoHistory->setContextSnapshot(['performance' => 12000, 'team_count' => 5]);
        $autoHistory->setUpgradeTime(new \DateTimeImmutable('-7 days'));
        $autoHistory->setTriggerType('auto');
        $manager->persist($autoHistory);
        $this->addReference(self::HISTORY_AUTO_UPGRADE, $autoHistory);

        // 创建手动升级历史记录
        $manualHistory = new DistributorLevelUpgradeHistory();
        $manualHistory->setDistributor($distributor);
        $manualHistory->setPreviousLevel($newLevel);
        $manualHistory->setNewLevel($highestLevel);
        $manualHistory->setSatisfiedExpression('manual_upgrade');
        $manualHistory->setContextSnapshot(['reason' => '管理员手动升级', 'operator' => 'admin']);
        $manualHistory->setUpgradeTime(new \DateTimeImmutable('-1 day'));
        $manualHistory->setTriggerType('manual');
        $manager->persist($manualHistory);
        $this->addReference(self::HISTORY_MANUAL_UPGRADE, $manualHistory);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            DistributorFixtures::class,
            DistributorLevelFixtures::class,
        ];
    }
}
