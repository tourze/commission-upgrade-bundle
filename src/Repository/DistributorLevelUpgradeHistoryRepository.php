<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeHistory;
use Tourze\OrderCommissionBundle\Entity\Distributor;

/**
 * 分销员等级升级历史仓储.
 *
 * @extends ServiceEntityRepository<DistributorLevelUpgradeHistory>
 */
#[Autoconfigure(public: true)]
final class DistributorLevelUpgradeHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DistributorLevelUpgradeHistory::class);
    }

    /**
     * 查询分销员的升级历史（按时间倒序）.
     *
     * @return DistributorLevelUpgradeHistory[]
     */
    public function findByDistributor(Distributor $distributor, int $limit = 20): array
    {
        /** @var array<DistributorLevelUpgradeHistory> */
        return $this->createQueryBuilder('history')
            ->where('history.distributor = :distributor')
            ->setParameter('distributor', $distributor)
            ->orderBy('history.upgradeTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 统计指定时间范围内的升级事件数量.
     *
     * @param \DateTimeImmutable $start
     * @param \DateTimeImmutable $end
     *
     * @return int
     */
    public function countByTimeRange(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return (int) $this->createQueryBuilder('history')
            ->select('COUNT(history.id)')
            ->where('history.upgradeTime BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function save(DistributorLevelUpgradeHistory $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DistributorLevelUpgradeHistory $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
