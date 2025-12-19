<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Service;

use Tourze\CommissionDistributorBundle\Entity\Distributor;
use Tourze\CommissionDistributorBundle\Service\DistributorStatsService;
use Tourze\CommissionLedgerBundle\Service\CommissionLedgerStatsService;
use Tourze\CommissionWithdrawBundle\Service\WithdrawLedgerStatsService;

/**
 * 升级上下文变量计算服务.
 *
 * 负责构建升级判断所需的上下文变量,从多个数据源聚合统计指标
 */
readonly class UpgradeContextProvider
{
    public function __construct(
        private WithdrawLedgerStatsService $withdrawLedgerStatsService,
        private DistributorStatsService $distributorStatsService,
        private CommissionLedgerStatsService $commissionLedgerStatsService,
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
            'settledCommissionAmount' => $this->calculateSettledCommissionAmount($distributor),
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
        return $this->withdrawLedgerStatsService->calculateWithdrawnAmount($distributor);
    }

    /**
     * 计算已结算佣金总额（仅统计 CommissionLedger.Settled 状态）.
     *
     * @param Distributor $distributor 分销员实体
     *
     * @return float 已结算佣金总额,单位:元
     */
    public function calculateSettledCommissionAmount(Distributor $distributor): float
    {
        return $this->commissionLedgerStatsService->calculateSettledCommissionAmount($distributor);
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
        return $this->distributorStatsService->calculateInviteeCount($distributor);
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
        return $this->commissionLedgerStatsService->calculateOrderCount($distributor);
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
        return $this->distributorStatsService->calculateActiveInviteeCount($distributor, $days);
    }
}
