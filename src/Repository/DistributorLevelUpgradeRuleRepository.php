<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeRule;
use Tourze\OrderCommissionBundle\Entity\DistributorLevel;

/**
 * 分销员等级升级规则仓储.
 *
 * @extends ServiceEntityRepository<DistributorLevelUpgradeRule>
 */
#[Autoconfigure(public: true)]
class DistributorLevelUpgradeRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DistributorLevelUpgradeRule::class);
    }

    /**
     * 根据源等级查找升级规则.
     *
     * @return DistributorLevelUpgradeRule|null
     */
    public function findBySourceLevel(DistributorLevel $sourceLevel): ?DistributorLevelUpgradeRule
    {
        return $this->findOneBy([
            'sourceLevel' => $sourceLevel,
            'isEnabled' => true,
        ]);
    }

    /**
     * 查找所有启用的升级规则.
     *
     * @return DistributorLevelUpgradeRule[]
     */
    public function findAllEnabled(): array
    {
        /** @var array<DistributorLevelUpgradeRule> */
        return $this->createQueryBuilder('rule')
            ->where('rule.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('rule.sourceLevel', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function save(DistributorLevelUpgradeRule $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DistributorLevelUpgradeRule $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
