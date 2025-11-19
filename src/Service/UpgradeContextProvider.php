<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Service;

use Tourze\OrderCommissionBundle\Entity\Distributor;
use Tourze\OrderCommissionBundle\Enum\DistributorStatus;
use Tourze\OrderCommissionBundle\Enum\WithdrawLedgerStatus;
use Tourze\OrderCommissionBundle\Repository\CommissionLedgerRepository;
use Tourze\OrderCommissionBundle\Repository\DistributorRepository;
use Tourze\OrderCommissionBundle\Repository\WithdrawLedgerRepository;

/**
 * 升级上下文变量计算服务.
 *
 * 负责构建升级判断所需的上下文变量,从多个数据源聚合统计指标
 */
class UpgradeContextProvider
{
    public function __construct(
        private WithdrawLedgerRepository $withdrawLedgerRepository,
        private DistributorRepository $distributorRepository,
        private CommissionLedgerRepository $commissionLedgerRepository,
    ) {
    }

    /**
     * 构建升级判断所需的完整上下文变量.
     *
     * @param Distributor $distributor 待升级的分销员实体
     *
     * @return array<string, mixed> 包含所有可用变量的关联数组
     */
    public function buildContext(Distributor $distributor): array
    {
        return [
            'withdrawnAmount' => $this->calculateWithdrawnAmount($distributor),
            'inviteeCount' => $this->calculateInviteeCount($distributor),
            'orderCount' => $this->calculateOrderCount($distributor),
            'activeInviteeCount' => $this->calculateActiveInviteeCount($distributor),
        ];
    }

    /**
     * 计算已提现佣金总额（仅统计 WithdrawLedger.Completed 状态）.
     *
     * @param Distributor $distributor 分销员实体
     *
     * @return float 已提现佣金总额,单位:元
     */
    public function calculateWithdrawnAmount(Distributor $distributor): float
    {
        $qb = $this->withdrawLedgerRepository->createQueryBuilder('wl')
            ->select('SUM(wl.amount) as total')
            ->where('wl.distributor = :distributor')
            ->andWhere('wl.status = :status')
            ->setParameter('distributor', $distributor)
            ->setParameter('status', WithdrawLedgerStatus::Completed)
        ;

        $result = $qb->getQuery()->getSingleScalarResult();

        return (float) ($result ?? 0.0);
    }

    /**
     * 计算邀请人数（一级下线数量）.
     *
     * @param Distributor $distributor 分销员实体
     *
     * @return int 邀请人数
     */
    public function calculateInviteeCount(Distributor $distributor): int
    {
        $qb = $this->distributorRepository->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.parent = :parent')
            ->andWhere('d.status = :status')
            ->setParameter('parent', $distributor)
            ->setParameter('status', DistributorStatus::Approved)
        ;

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * 计算订单数（关联该分销员的订单总数）.
     *
     * @param Distributor $distributor 分销员实体
     *
     * @return int 订单数
     */
    public function calculateOrderCount(Distributor $distributor): int
    {
        $qb = $this->commissionLedgerRepository->createQueryBuilder('cl')
            ->select('COUNT(DISTINCT cl.order)')
            ->where('cl.distributor = :distributor')
            ->setParameter('distributor', $distributor)
        ;

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * 计算活跃邀请人数（最近N天内有订单的下线）.
     *
     * @param Distributor $distributor 分销员实体
     * @param int         $days        活跃天数阈值(默认30天)
     *
     * @return int 活跃邀请人数
     */
    public function calculateActiveInviteeCount(Distributor $distributor, int $days = 30): int
    {
        $threshold = new \DateTimeImmutable(sprintf('-%d days', $days));

        $qb = $this->distributorRepository->createQueryBuilder('d')
            ->select('COUNT(DISTINCT d.id)')
            ->innerJoin('Tourze\OrderCommissionBundle\Entity\CommissionLedger', 'cl', 'WITH', 'cl.distributor = d.id')
            ->where('d.parent = :parent')
            ->andWhere('d.status = :status')
            ->andWhere('cl.createTime >= :threshold')
            ->setParameter('parent', $distributor)
            ->setParameter('status', DistributorStatus::Approved)
            ->setParameter('threshold', $threshold)
        ;

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
