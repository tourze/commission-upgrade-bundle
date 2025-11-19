<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeHistory;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeRule;
use Tourze\CommissionUpgradeBundle\Repository\DistributorLevelUpgradeRuleRepository;
use Tourze\CommissionUpgradeBundle\Service\DistributorUpgradeService;
use Tourze\CommissionUpgradeBundle\Service\UpgradeContextProvider;
use Tourze\CommissionUpgradeBundle\Service\UpgradeExpressionEvaluator;
use Tourze\OrderCommissionBundle\Entity\Distributor;
use Tourze\OrderCommissionBundle\Entity\DistributorLevel;
use Tourze\OrderCommissionBundle\Entity\WithdrawLedger;

/**
 * T020: DistributorUpgradeService 单元测试
 *
 * 测试升级核心逻辑
 */
#[CoversClass(DistributorUpgradeService::class)]
final class DistributorUpgradeServiceTest extends TestCase
{
    private DistributorUpgradeService $service;
    private DistributorLevelUpgradeRuleRepository $ruleRepository;
    private UpgradeExpressionEvaluator $expressionEvaluator;
    private UpgradeContextProvider $contextProvider;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->createMock(DistributorLevelUpgradeRuleRepository::class);
        $this->expressionEvaluator = $this->createMock(UpgradeExpressionEvaluator::class);
        $this->contextProvider = $this->createMock(UpgradeContextProvider::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new DistributorUpgradeService(
            $this->entityManager,
            $this->contextProvider,
            $this->expressionEvaluator,
            $this->ruleRepository
        );
    }

    /**
     * @test
     * 测试满足条件时执行升级
     */
    public function testCheckAndUpgrade(): void
    {
        $sourceLevel = $this->createMockLevel(1, '普通会员');
        $targetLevel = $this->createMockLevel(2, '银牌会员');
        $distributor = $this->createMockDistributor($sourceLevel);
        $withdrawLedger = $this->createMock(WithdrawLedger::class);

        $rule = $this->createMockRule($sourceLevel, $targetLevel, 'withdrawnAmount >= 5000');

        // Mock findNextLevelRule 返回规则
        $this->ruleRepository
            ->expects($this->once())
            ->method('findBySourceLevel')
            ->with($sourceLevel)
            ->willReturn($rule);

        // Mock buildContext 返回上下文
        $context = ['withdrawnAmount' => 6000.00];
        $this->contextProvider
            ->expects($this->once())
            ->method('buildContext')
            ->with($distributor)
            ->willReturn($context);

        // Mock evaluate 返回 true（条件满足）
        $this->expressionEvaluator
            ->expects($this->once())
            ->method('evaluate')
            ->with('withdrawnAmount >= 5000', $context)
            ->willReturn(true);

        // Mock EntityManager 事务
        $this->entityManager
            ->expects($this->once())
            ->method('beginTransaction');

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                static $callCount = 0;
                ++$callCount;
                if (1 === $callCount) {
                    $this->assertInstanceOf(Distributor::class, $entity, 'First persist should be Distributor');
                } elseif (2 === $callCount) {
                    $this->assertInstanceOf(DistributorLevelUpgradeHistory::class, $entity, 'Second persist should be DistributorLevelUpgradeHistory');
                }
            });

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->entityManager
            ->expects($this->once())
            ->method('commit');

        $history = $this->service->checkAndUpgrade($distributor, $withdrawLedger);

        $this->assertInstanceOf(DistributorLevelUpgradeHistory::class, $history);
    }

    /**
     * @test
     * 测试条件不满足时返回 null
     */
    public function it_returns_null_when_condition_not_met(): void
    {
        $sourceLevel = $this->createMockLevel(1, '普通会员');
        $targetLevel = $this->createMockLevel(2, '银牌会员');
        $distributor = $this->createMockDistributor($sourceLevel);

        $rule = $this->createMockRule($sourceLevel, $targetLevel, 'withdrawnAmount >= 5000');

        $this->ruleRepository
            ->method('findBySourceLevel')
            ->willReturn($rule);

        // Mock buildContext 返回上下文
        $context = ['withdrawnAmount' => 4500.00]; // 不满足条件
        $this->contextProvider
            ->method('buildContext')
            ->willReturn($context);

        // Mock evaluate 返回 false（条件不满足）
        $this->expressionEvaluator
            ->method('evaluate')
            ->willReturn(false);

        // 不应该触发事务
        $this->entityManager
            ->expects($this->never())
            ->method('beginTransaction');

        $history = $this->service->checkAndUpgrade($distributor);

        $this->assertNull($history, '条件不满足时应该返回 null');
    }

    /**
     * @test
     * 测试已达最高等级时返回 null
     */
    public function it_returns_null_when_max_level_reached(): void
    {
        $maxLevel = $this->createMockLevel(4, '钻石会员');
        $distributor = $this->createMockDistributor($maxLevel);

        // Mock findNextLevelRule 返回 null（无下一级别规则）
        $this->ruleRepository
            ->expects($this->once())
            ->method('findBySourceLevel')
            ->with($maxLevel)
            ->willReturn(null);

        // 不应该继续执行
        $this->contextProvider
            ->expects($this->never())
            ->method('buildContext');

        $history = $this->service->checkAndUpgrade($distributor);

        $this->assertNull($history, '已达最高等级时应该返回 null');
    }

    /**
     * @test
     * 测试查找下一级别规则
     */
    public function testFindNextLevelRule(): void
    {
        $sourceLevel = $this->createMockLevel(1, '普通会员');
        $targetLevel = $this->createMockLevel(2, '银牌会员');
        $rule = $this->createMockRule($sourceLevel, $targetLevel, 'withdrawnAmount >= 5000');

        $this->ruleRepository
            ->method('findBySourceLevel')
            ->with($sourceLevel)
            ->willReturn($rule);

        $result = $this->service->findNextLevelRule($sourceLevel);

        $this->assertSame($rule, $result);
    }

    /**
     * @test
     * 测试原子事务保障：失败时回滚
     */
    public function it_rolls_back_transaction_on_failure(): void
    {
        $sourceLevel = $this->createMockLevel(1, '普通会员');
        $targetLevel = $this->createMockLevel(2, '银牌会员');
        $distributor = $this->createMockDistributor($sourceLevel);

        $rule = $this->createMockRule($sourceLevel, $targetLevel, 'withdrawnAmount >= 5000');

        $this->ruleRepository
            ->method('findBySourceLevel')
            ->willReturn($rule);

        $this->contextProvider
            ->method('buildContext')
            ->willReturn(['withdrawnAmount' => 6000.00]);

        $this->expressionEvaluator
            ->method('evaluate')
            ->willReturn(true);

        // Mock EntityManager 抛出异常
        $this->entityManager
            ->expects($this->once())
            ->method('beginTransaction');

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->willThrowException(new \RuntimeException('Database error'));

        // 应该回滚事务
        $this->entityManager
            ->expects($this->once())
            ->method('rollback');

        $this->entityManager
            ->expects($this->never())
            ->method('commit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database error');

        $this->service->checkAndUpgrade($distributor);
    }

    // Helper methods

    private function createMockLevel(int $level, string $name): DistributorLevel
    {
        $mock = $this->createMock(DistributorLevel::class);
        $mock->method('getName')->willReturn($name);

        return $mock;
    }

    private function createMockDistributor(DistributorLevel $level): Distributor
    {
        $mock = $this->createMock(Distributor::class);
        $mock->method('getLevel')->willReturn($level);
        $mock->method('setLevel')->willReturnSelf();

        return $mock;
    }

    private function createMockRule(
        DistributorLevel $sourceLevel,
        DistributorLevel $targetLevel,
        string $expression
    ): DistributorLevelUpgradeRule {
        $mock = $this->createMock(DistributorLevelUpgradeRule::class);
        $mock->method('getSourceLevel')->willReturn($sourceLevel);
        $mock->method('getTargetLevel')->willReturn($targetLevel);
        $mock->method('getUpgradeExpression')->willReturn($expression);
        $mock->method('isEnabled')->willReturn(true);

        return $mock;
    }
}
