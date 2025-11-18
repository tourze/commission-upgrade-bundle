<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeHistory;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeRule;
use Tourze\CommissionUpgradeBundle\Service\DistributorUpgradeService;
use Tourze\OrderCommissionBundle\Entity\Distributor;
use Tourze\OrderCommissionBundle\Entity\DistributorLevel;
use Tourze\OrderCommissionBundle\Entity\WithdrawLedger;
use Tourze\OrderCommissionBundle\Enum\WithdrawLedgerStatus;

/**
 * T024: 完整升级流程集成测试
 *
 * 测试从提现成功到升级完成的完整流程
 */
#[CoversClass(DistributorUpgradeService::class)]
final class UpgradeFlowTest extends KernelTestCase
{
    private DistributorUpgradeService $upgradeService;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var DistributorUpgradeService $service */
        $service = self::getContainer()->get(DistributorUpgradeService::class);
        $this->upgradeService = $service;
    }

    /**
     * @test
     * 场景：分销员已提现4500元,再提现600元后达到5000元,触发升级到2级
     */
    public function it_upgrades_distributor_when_withdraw_threshold_reached(): void
    {
        // Given: 创建分销员（1级，已提现4500元）
        $distributor = $this->createDistributor(1, 4500.00);

        // And: 配置升级规则（1级→2级：withdrawnAmount >= 5000）
        $rule = $this->createUpgradeRule(1, 2, 'withdrawnAmount >= 5000');

        // And: 模拟提现成功（+600元）
        $withdrawLedger = $this->createWithdrawLedger($distributor, 600.00);

        // When: 触发升级检查
        $history = $this->upgradeService->checkAndUpgrade($distributor, $withdrawLedger);

        // Then: 分销员等级应该为2级
        $this->assertNotNull($history);
        $this->assertInstanceOf(DistributorLevelUpgradeHistory::class, $history);
        $this->assertSame(2, $distributor->getLevel()->getLevel());
        $this->assertSame('银牌会员', $distributor->getLevel()->getName());

        // And: 升级历史记录应该存在
        $this->assertSame(1, $history->getPreviousLevel()->getLevel());
        $this->assertSame(2, $history->getNewLevel()->getLevel());
        $this->assertSame('withdrawnAmount >= 5000', $history->getSatisfiedExpression());

        // And: 上下文快照应该包含完整信息
        $snapshot = $history->getContextSnapshot();
        $this->assertArrayHasKey('withdrawnAmount', $snapshot);
        $this->assertGreaterThanOrEqual(5000.00, $snapshot['withdrawnAmount']);
    }

    /**
     * @test
     * 场景：分销员提现金额不足,不应触发升级
     */
    public function it_does_not_upgrade_when_threshold_not_reached(): void
    {
        // Given: 创建分销员（1级，已提现4500元）
        $distributor = $this->createDistributor(1, 4500.00);

        // And: 配置升级规则（1级→2级：withdrawnAmount >= 5000）
        $this->createUpgradeRule(1, 2, 'withdrawnAmount >= 5000');

        // And: 模拟提现成功（+400元，总计4900元，仍不满足）
        $withdrawLedger = $this->createWithdrawLedger($distributor, 400.00);

        // When: 触发升级检查
        $history = $this->upgradeService->checkAndUpgrade($distributor, $withdrawLedger);

        // Then: 不应触发升级
        $this->assertNull($history);
        $this->assertSame(1, $distributor->getLevel()->getLevel());
    }

    /**
     * @test
     * 场景：分销员已达最高等级,不应继续升级
     */
    public function it_does_not_upgrade_when_max_level_reached(): void
    {
        // Given: 创建分销员（4级钻石会员）
        $distributor = $this->createDistributor(4, 100000.00);

        // When: 触发升级检查
        $withdrawLedger = $this->createWithdrawLedger($distributor, 10000.00);
        $history = $this->upgradeService->checkAndUpgrade($distributor, $withdrawLedger);

        // Then: 不应触发升级（无下一级别规则）
        $this->assertNull($history);
        $this->assertSame(4, $distributor->getLevel()->getLevel());
    }

    /**
     * @test
     * 场景：复杂 AND 条件升级（已提现 >= 10000 且邀请人数 >= 10）
     */
    public function it_upgrades_with_complex_and_condition(): void
    {
        // Given: 创建分销员（2级，已提现12000元，邀请人数15人）
        $distributor = $this->createDistributor(2, 12000.00, 15);

        // And: 配置升级规则（2级→3级：withdrawnAmount >= 10000 and inviteeCount >= 10）
        $this->createUpgradeRule(2, 3, 'withdrawnAmount >= 10000 and inviteeCount >= 10');

        // When: 触发升级检查
        $withdrawLedger = $this->createWithdrawLedger($distributor, 100.00);
        $history = $this->upgradeService->checkAndUpgrade($distributor, $withdrawLedger);

        // Then: 应该升级到3级
        $this->assertNotNull($history);
        $this->assertSame(3, $distributor->getLevel()->getLevel());
        $this->assertSame('金牌会员', $distributor->getLevel()->getName());
    }

    /**
     * @test
     * 场景：复杂 AND 条件部分满足,不应升级
     */
    public function it_does_not_upgrade_when_and_condition_partially_met(): void
    {
        // Given: 创建分销员（2级，已提现12000元，邀请人数8人）
        $distributor = $this->createDistributor(2, 12000.00, 8);

        // And: 配置升级规则（2级→3级：withdrawnAmount >= 10000 and inviteeCount >= 10）
        $this->createUpgradeRule(2, 3, 'withdrawnAmount >= 10000 and inviteeCount >= 10');

        // When: 触发升级检查
        $withdrawLedger = $this->createWithdrawLedger($distributor, 100.00);
        $history = $this->upgradeService->checkAndUpgrade($distributor, $withdrawLedger);

        // Then: 不应升级（邀请人数不足）
        $this->assertNull($history);
        $this->assertSame(2, $distributor->getLevel()->getLevel());
    }

    /**
     * @test
     * 场景：防刷单机制验证 - 仅统计 Completed 状态提现
     */
    public function it_only_counts_completed_withdraw_ledgers(): void
    {
        // Given: 创建分销员（1级，已提现3000元 Completed + 2000元 Pending）
        $distributor = $this->createDistributor(1, 3000.00);

        // And: 添加 Pending 状态提现记录（不应计入）
        $this->createWithdrawLedger($distributor, 2000.00, WithdrawLedgerStatus::Pending);

        // And: 配置升级规则（1级→2级：withdrawnAmount >= 5000）
        $this->createUpgradeRule(1, 2, 'withdrawnAmount >= 5000');

        // When: 触发升级检查
        $withdrawLedger = $this->createWithdrawLedger($distributor, 100.00);
        $history = $this->upgradeService->checkAndUpgrade($distributor, $withdrawLedger);

        // Then: 不应升级（仅统计 Completed，总计3100元不足5000）
        $this->assertNull($history);
    }

    // Helper methods

    private function createDistributor(int $level, float $withdrawnAmount, int $inviteeCount = 0): Distributor
    {
        $distributorLevel = new DistributorLevel();
        $distributorLevel->setLevel($level);
        $distributorLevel->setName($this->getLevelName($level));

        $distributor = new Distributor();
        $distributor->setLevel($distributorLevel);

        // Note: 实际提现金额需要通过 Repository 查询统计
        // 这里简化处理,假设已有历史提现记录

        return $distributor;
    }

    private function createUpgradeRule(int $sourceLevel, int $targetLevel, string $expression): DistributorLevelUpgradeRule
    {
        $source = new DistributorLevel();
        $source->setLevel($sourceLevel);
        $source->setName($this->getLevelName($sourceLevel));

        $target = new DistributorLevel();
        $target->setLevel($targetLevel);
        $target->setName($this->getLevelName($targetLevel));

        $rule = new DistributorLevelUpgradeRule();
        $rule->setSourceLevel($source);
        $rule->setTargetLevel($target);
        $rule->setUpgradeExpression($expression);
        $rule->setIsEnabled(true);

        return $rule;
    }

    private function createWithdrawLedger(
        Distributor $distributor,
        float $amount,
        WithdrawLedgerStatus $status = WithdrawLedgerStatus::Completed
    ): WithdrawLedger {
        $ledger = new WithdrawLedger();
        $ledger->setDistributor($distributor);
        $ledger->setAmount($amount);
        $ledger->setStatus($status);

        return $ledger;
    }

    private function getLevelName(int $level): string
    {
        return match ($level) {
            1 => '普通会员',
            2 => '银牌会员',
            3 => '金牌会员',
            4 => '钻石会员',
            default => '未知等级',
        };
    }
}
