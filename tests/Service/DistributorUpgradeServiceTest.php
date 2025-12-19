<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommissionDistributorBundle\Entity\Distributor;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\DTO\UpgradeEligibilityResult;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeHistory;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeRule;
use Tourze\CommissionUpgradeBundle\Service\DistributorUpgradeService;
use Tourze\CommissionWithdrawBundle\Entity\WithdrawLedger;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * T020: DistributorUpgradeService 集成测试
 *
 * 测试升级核心逻辑
 * @internal
 */
#[CoversClass(DistributorUpgradeService::class)]
#[RunTestsInSeparateProcesses]
final class DistributorUpgradeServiceTest extends AbstractIntegrationTestCase
{
    private DistributorUpgradeService $service;

    protected function onSetUp(): void
    {
        $this->service = self::getService(DistributorUpgradeService::class);
    }

    /**
     * @test
     * 测试满足条件时执行升级
     */
    public function testCheckAndUpgrade(): void
    {
        // 创建等级
        $sourceLevel = $this->createLevel('普通会员');
        $targetLevel = $this->createLevel('银牌会员');

        // 创建升级规则
        $rule = $this->createUpgradeRule($sourceLevel, $targetLevel, 'withdrawnAmount >= 5000');

        // 创建分销员
        $user = $this->createUser('test-user-' . uniqid(), 'password', ['ROLE_USER']);
        $distributor = $this->createDistributor($user, $sourceLevel);

        // 创建提现流水（用于上下文计算，可以为null）
        $withdrawLedger = null;

        // 由于 UpgradeContextProvider 依赖统计服务，在真实环境中会返回 0
        // 但表达式 'withdrawnAmount >= 5000' 会评估为 false
        // 为了测试升级成功的场景，我们修改表达式为简单条件
        $rule->setUpgradeExpression('1 == 1'); // 总是为真
        self::getEntityManager()->flush();

        $history = $this->service->checkAndUpgrade($distributor, $withdrawLedger);

        $this->assertInstanceOf(DistributorLevelUpgradeHistory::class, $history);
        $this->assertSame($targetLevel->getId(), $history->getNewLevel()->getId());
        $this->assertSame($sourceLevel->getId(), $history->getPreviousLevel()->getId());

        // 验证分销员等级已更新
        self::getEntityManager()->refresh($distributor);
        $this->assertSame($targetLevel->getId(), $distributor->getLevel()->getId());
    }

    /**
     * @test
     * 测试条件不满足时返回 null
     */
    public function itReturnsNullWhenConditionNotMet(): void
    {
        // 创建等级
        $sourceLevel = $this->createLevel('普通会员');
        $targetLevel = $this->createLevel('银牌会员');

        // 创建升级规则 - 使用一个永远为假的条件
        $rule = $this->createUpgradeRule($sourceLevel, $targetLevel, '1 == 0');

        // 创建分销员
        $user = $this->createUser('test-user-' . uniqid(), 'password', ['ROLE_USER']);
        $distributor = $this->createDistributor($user, $sourceLevel);

        $history = $this->service->checkAndUpgrade($distributor);

        $this->assertNull($history, '条件不满足时应该返回 null');

        // 验证分销员等级未变更
        self::getEntityManager()->refresh($distributor);
        $this->assertSame($sourceLevel->getId(), $distributor->getLevel()->getId());
    }

    /**
     * @test
     * 测试已达最高等级时返回 null
     */
    public function itReturnsNullWhenMaxLevelReached(): void
    {
        // 创建最高等级（没有对应的升级规则）
        $maxLevel = $this->createLevel('钻石会员');

        // 创建分销员
        $user = $this->createUser('test-user-' . uniqid(), 'password', ['ROLE_USER']);
        $distributor = $this->createDistributor($user, $maxLevel);

        $history = $this->service->checkAndUpgrade($distributor);

        $this->assertNull($history, '已达最高等级时应该返回 null');
    }

    /**
     * @test
     * 测试查找下一级别规则
     */
    public function testFindNextLevelRule(): void
    {
        // 创建等级
        $sourceLevel = $this->createLevel('普通会员');
        $targetLevel = $this->createLevel('银牌会员');

        // 创建升级规则
        $rule = $this->createUpgradeRule($sourceLevel, $targetLevel, 'withdrawnAmount >= 5000');

        $result = $this->service->findNextLevelRule($sourceLevel);

        $this->assertNotNull($result);
        $this->assertSame($rule->getId(), $result->getId());
        $this->assertSame($sourceLevel->getId(), $result->getSourceLevel()->getId());
        $this->assertSame($targetLevel->getId(), $result->getTargetLevel()->getId());
    }

    /**
     * @test
     * 测试当无符合条件的升级规则时返回 null
     */
    public function testCheckUpgradeEligibilityShouldReturnNullWhenNoEligibleRule(): void
    {
        // 创建最高等级（没有对应的升级规则）
        $maxLevel = $this->createLevel('钻石会员-check');

        // 创建分销员
        $user = $this->createUser('test-user-' . uniqid(), 'password', ['ROLE_USER']);
        $distributor = $this->createDistributor($user, $maxLevel);

        $result = $this->service->checkUpgradeEligibility($distributor);

        $this->assertNull($result, '无升级规则时应该返回 null');

        // 验证分销员等级未变更
        self::getEntityManager()->refresh($distributor);
        $this->assertSame($maxLevel->getId(), $distributor->getLevel()->getId());
    }

    /**
     * @test
     * 测试当符合升级条件时返回 UpgradeEligibilityResult
     */
    public function testCheckUpgradeEligibilityShouldReturnResultWhenEligible(): void
    {
        // 创建等级
        $sourceLevel = $this->createLevel('普通会员-check');
        $targetLevel = $this->createLevel('银牌会员-check');

        // 创建升级规则 - 使用一个永远为真的条件
        $rule = $this->createUpgradeRule($sourceLevel, $targetLevel, '1 == 1');

        // 创建分销员
        $user = $this->createUser('test-user-' . uniqid(), 'password', ['ROLE_USER']);
        $distributor = $this->createDistributor($user, $sourceLevel);

        $result = $this->service->checkUpgradeEligibility($distributor);

        $this->assertNotNull($result, '符合条件时应该返回 UpgradeEligibilityResult');
        $this->assertInstanceOf(UpgradeEligibilityResult::class, $result);
        $this->assertSame($targetLevel->getId(), $result->getNewLevel()->getId());
        $this->assertSame($rule->getId(), $result->getRule()->getId());
        $this->assertIsArray($result->getContextSnapshot());

        // 验证分销员等级未变更（checkUpgradeEligibility 只检查不执行升级）
        self::getEntityManager()->refresh($distributor);
        $this->assertSame($sourceLevel->getId(), $distributor->getLevel()->getId(), '仅检查资格时不应改变分销员等级');
    }

    /**
     * @test
     * 测试智能升级功能的基本功能
     */
    public function testCheckAndUpgradeWithIntelligentRules(): void
    {
        // 创建等级
        $sourceLevel = $this->createLevel('普通会员-智能升级');
        $targetLevel = $this->createLevel('银牌会员-智能升级');

        // 创建升级规则
        $rule = $this->createUpgradeRule($sourceLevel, $targetLevel, '1 == 1'); // 总是为真

        // 创建分销员
        $user = $this->createUser('test-user-' . uniqid(), 'password', ['ROLE_USER']);
        $distributor = $this->createDistributor($user, $sourceLevel);

        // 执行智能升级
        $history = $this->service->checkAndUpgradeWithIntelligentRules($distributor);

        // 验证升级成功
        $this->assertInstanceOf(DistributorLevelUpgradeHistory::class, $history);
        $this->assertSame($targetLevel->getId(), $history->getNewLevel()->getId());
        $this->assertSame($sourceLevel->getId(), $history->getPreviousLevel()->getId());

        // 验证分销员等级已更新
        self::getEntityManager()->refresh($distributor);
        $this->assertSame($targetLevel->getId(), $distributor->getLevel()->getId());
    }

    // Helper methods

    /**
     * 创建等级
     */
    private function createLevel(string $name): DistributorLevel
    {
        $level = new DistributorLevel();
        $level->setName($name);

        self::getEntityManager()->persist($level);
        self::getEntityManager()->flush();

        return $level;
    }

    /**
     * 创建分销员
     * @param mixed $user
     */
    private function createDistributor($user, DistributorLevel $level): Distributor
    {
        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($level);

        self::getEntityManager()->persist($distributor);
        self::getEntityManager()->flush();

        return $distributor;
    }

    /**
     * 创建升级规则
     */
    private function createUpgradeRule(
        DistributorLevel $sourceLevel,
        DistributorLevel $targetLevel,
        string $expression,
    ): DistributorLevelUpgradeRule {
        $rule = new DistributorLevelUpgradeRule();
        $rule->setSourceLevel($sourceLevel);
        $rule->setTargetLevel($targetLevel);
        $rule->setUpgradeExpression($expression);
        $rule->setIsEnabled(true);

        self::getEntityManager()->persist($rule);
        self::getEntityManager()->flush();

        return $rule;
    }
}
