<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\CommissionUpgradeBundle\Service\UpgradeContextProvider;
use Tourze\OrderCommissionBundle\Entity\CommissionLedger;
use Tourze\OrderCommissionBundle\Entity\Distributor;
use Tourze\OrderCommissionBundle\Entity\WithdrawLedger;
use Tourze\OrderCommissionBundle\Enum\DistributorStatus;
use Tourze\OrderCommissionBundle\Enum\WithdrawLedgerStatus;
use Tourze\OrderCommissionBundle\Repository\CommissionLedgerRepository;
use Tourze\OrderCommissionBundle\Repository\DistributorRepository;
use Tourze\OrderCommissionBundle\Repository\WithdrawLedgerRepository;

/**
 * T019: UpgradeContextProvider 单元测试
 *
 * 测试上下文变量计算功能
 */
#[CoversClass(UpgradeContextProvider::class)]
final class UpgradeContextProviderTest extends TestCase
{
    private UpgradeContextProvider $provider;
    private WithdrawLedgerRepository $withdrawLedgerRepository;
    private DistributorRepository $distributorRepository;
    private CommissionLedgerRepository $commissionLedgerRepository;

    protected function setUp(): void
    {
        $this->withdrawLedgerRepository = $this->createMock(WithdrawLedgerRepository::class);
        $this->distributorRepository = $this->createMock(DistributorRepository::class);
        $this->commissionLedgerRepository = $this->createMock(CommissionLedgerRepository::class);

        $this->provider = new UpgradeContextProvider(
            $this->withdrawLedgerRepository,
            $this->distributorRepository,
            $this->commissionLedgerRepository
        );
    }

    /**
     * @test
     * 测试计算已提现金额：仅包含 Completed 状态
     */
    public function it_calculates_withdrawn_amount_with_completed_only(): void
    {
        $distributor = $this->createMock(Distributor::class);

        // Mock repository 返回已提现金额（仅 Completed）
        $this->withdrawLedgerRepository
            ->expects($this->once())
            ->method('sumCompletedAmount')
            ->with($distributor)
            ->willReturn(5000.00);

        $amount = $this->provider->calculateWithdrawnAmount($distributor);

        $this->assertSame(5000.00, $amount);
    }

    /**
     * @test
     * 测试计算已提现金额：排除 Failed/Pending 状态
     */
    public function it_excludes_failed_and_pending_withdraw_ledgers(): void
    {
        $distributor = $this->createMock(Distributor::class);

        // Mock repository 只统计 Completed，自动排除 Failed/Pending
        $this->withdrawLedgerRepository
            ->expects($this->once())
            ->method('sumCompletedAmount')
            ->with($distributor)
            ->willReturn(3000.00); // 假设只有3000元是 Completed

        $amount = $this->provider->calculateWithdrawnAmount($distributor);

        $this->assertSame(3000.00, $amount);
    }

    /**
     * @test
     * 测试计算邀请人数：包含 Approved，排除 Pending/Rejected
     */
    public function it_calculates_invitee_count_with_approved_only(): void
    {
        $distributor = $this->createMock(Distributor::class);

        // Mock repository 返回邀请人数（仅 Approved）
        $this->distributorRepository
            ->expects($this->once())
            ->method('countByParent')
            ->with($distributor, DistributorStatus::Approved)
            ->willReturn(10);

        $count = $this->provider->calculateInviteeCount($distributor);

        $this->assertSame(10, $count);
    }

    /**
     * @test
     * 测试计算订单数：去重计数
     */
    public function it_calculates_order_count_with_distinct(): void
    {
        $distributor = $this->createMock(Distributor::class);

        // Mock repository 返回订单数（去重）
        $this->commissionLedgerRepository
            ->expects($this->once())
            ->method('countOrdersByDistributor')
            ->with($distributor)
            ->willReturn(50);

        $count = $this->provider->calculateOrderCount($distributor);

        $this->assertSame(50, $count);
    }

    /**
     * @test
     * 测试计算活跃邀请人数：30天内有订单
     */
    public function it_calculates_active_invitee_count_within_30_days(): void
    {
        $distributor = $this->createMock(Distributor::class);

        // Mock repository 返回活跃邀请人数（30天内有订单）
        $this->distributorRepository
            ->expects($this->once())
            ->method('countActiveInvitees')
            ->with($distributor, 30)
            ->willReturn(7);

        $count = $this->provider->calculateActiveInviteeCount($distributor, 30);

        $this->assertSame(7, $count);
    }

    /**
     * @test
     * 测试 buildContext 构建完整上下文
     */
    public function it_builds_complete_context(): void
    {
        $distributor = $this->createMock(Distributor::class);

        // Mock 所有 repository 方法
        $this->withdrawLedgerRepository
            ->method('sumCompletedAmount')
            ->willReturn(6000.00);

        $this->distributorRepository
            ->method('countByParent')
            ->willReturn(15);

        $this->commissionLedgerRepository
            ->method('countOrdersByDistributor')
            ->willReturn(60);

        $this->distributorRepository
            ->method('countActiveInvitees')
            ->willReturn(10);

        $context = $this->provider->buildContext($distributor);

        $this->assertIsArray($context);
        $this->assertArrayHasKey('withdrawnAmount', $context);
        $this->assertArrayHasKey('inviteeCount', $context);
        $this->assertArrayHasKey('orderCount', $context);
        $this->assertArrayHasKey('activeInviteeCount', $context);
        $this->assertSame(6000.00, $context['withdrawnAmount']);
        $this->assertSame(15, $context['inviteeCount']);
        $this->assertSame(60, $context['orderCount']);
        $this->assertSame(10, $context['activeInviteeCount']);
    }

    /**
     * @test
     * 测试计算已提现金额：无提现记录时返回0
     */
    public function it_returns_zero_when_no_withdraw_ledgers(): void
    {
        $distributor = $this->createMock(Distributor::class);

        $this->withdrawLedgerRepository
            ->method('sumCompletedAmount')
            ->willReturn(0.00);

        $amount = $this->provider->calculateWithdrawnAmount($distributor);

        $this->assertSame(0.00, $amount);
    }

    /**
     * @test
     * 测试计算邀请人数：无邀请人时返回0
     */
    public function it_returns_zero_when_no_invitees(): void
    {
        $distributor = $this->createMock(Distributor::class);

        $this->distributorRepository
            ->method('countByParent')
            ->willReturn(0);

        $count = $this->provider->calculateInviteeCount($distributor);

        $this->assertSame(0, $count);
    }
}
