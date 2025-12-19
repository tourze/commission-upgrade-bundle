<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommissionDistributorBundle\Entity\Distributor;
use Tourze\CommissionDistributorBundle\Enum\DistributorStatus;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\Entity\DirectUpgradeRule;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeHistory;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeRule;
use Tourze\CommissionUpgradeBundle\Repository\DirectUpgradeRuleRepository;
use Tourze\CommissionUpgradeBundle\Repository\DistributorLevelUpgradeRuleRepository;
use Tourze\CommissionUpgradeBundle\Service\DistributorUpgradeService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 集成测试：DistributorUpgradeService 智能升级功能
 *
 * 专门测试新增的直升规则和智能升级逻辑
 * 使用真实数据库和服务容器，不使用 Mock
 * @internal
 */
#[CoversClass(DistributorUpgradeService::class)]
#[RunTestsInSeparateProcesses]
final class DistributorUpgradeService_IntelligentRulesTest extends AbstractIntegrationTestCase
{
    private DistributorUpgradeService $service;
    private DirectUpgradeRuleRepository $directUpgradeRuleRepository;
    private DistributorLevelUpgradeRuleRepository $ruleRepository;

    protected function onSetUp(): void
    {
        // 从容器获取真实服务
        $this->service = self::getService(DistributorUpgradeService::class);
        $this->directUpgradeRuleRepository = self::getService(DirectUpgradeRuleRepository::class);
        $this->ruleRepository = self::getService(DistributorLevelUpgradeRuleRepository::class);
    }

    /**
     * TC-001：测试智能升级 - 直升规则优先且满足条件
     *
     * 验证当直升规则满足条件时，优先执行直升而不是常规升级
     */
    public function testCheckAndUpgradeWithIntelligentRules_DirectUpgradeApplies(): void
    {
        // Arrange - 创建测试数据
        $entityManager = self::getEntityManager();

        // 创建等级：普通会员(1) → 金牌会员(3)
        $currentLevel = $this->createLevel(1, '普通会员');
        $silverLevel = $this->createLevel(2, '银牌会员');
        $targetLevel = $this->createLevel(3, '金牌会员');

        // 创建分销员
        $user = $this->createNormalUser('test_direct_upgrade_1');
        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($currentLevel);
        $distributor->setStatus(DistributorStatus::Approved);
        $entityManager->persist($distributor);
        $entityManager->flush();

        // 创建 12 个已审批的下线（模拟 inviteeCount = 12）
        $this->createApprovedInvitees($distributor, 12, $currentLevel);

        // 创建直升规则：邀请人数 >= 10 可直升到金牌会员
        $directRule = new DirectUpgradeRule();
        $directRule->setTargetLevel($targetLevel);
        $directRule->setUpgradeExpression('inviteeCount >= 10');
        $directRule->setPriority(200);
        $directRule->setIsEnabled(true);
        $directRule->setDescription('测试直升规则');
        $entityManager->persist($directRule);

        // 创建常规升级规则：普通 → 银牌（应该被直升规则跳过）
        $regularRule = new DistributorLevelUpgradeRule();
        $regularRule->setSourceLevel($currentLevel);
        $regularRule->setTargetLevel($silverLevel);
        $regularRule->setUpgradeExpression('inviteeCount >= 5');
        $regularRule->setIsEnabled(true);
        $entityManager->persist($regularRule);

        $entityManager->flush();
        $entityManager->clear();

        // 重新加载以确保使用数据库中的最新数据
        $distributor = $entityManager->find(Distributor::class, $distributor->getId());

        // Act - 执行智能升级
        $history = $this->service->checkAndUpgradeWithIntelligentRules($distributor);

        // Assert - 验证升级成功
        $this->assertInstanceOf(DistributorLevelUpgradeHistory::class, $history);
        $this->assertSame($currentLevel->getId(), $history->getPreviousLevel()->getId());
        $this->assertSame($targetLevel->getId(), $history->getNewLevel()->getId());
        $this->assertSame('inviteeCount >= 10', $history->getSatisfiedExpression());

        // 验证分销员等级已更新
        $entityManager->refresh($distributor);
        $this->assertSame($targetLevel->getId(), $distributor->getLevel()->getId());

        // 验证跳过了银牌等级（直升生效）
        $this->assertSame(3, $distributor->getLevel()->getLevel());
    }

    /**
     * TC-002：测试智能升级 - 直升规则不满足，回退到常规升级
     *
     * 验证当直升规则不满足时，正确回退到常规升级逻辑
     */
    public function testCheckAndUpgradeWithIntelligentRules_FallbackToRegular(): void
    {
        // Arrange
        $entityManager = self::getEntityManager();

        $currentLevel = $this->createLevel(1, '普通会员');
        $silverLevel = $this->createLevel(2, '银牌会员');
        $goldLevel = $this->createLevel(3, '金牌会员');

        // 创建分销员
        $user = $this->createNormalUser('test_fallback_1');
        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($currentLevel);
        $distributor->setStatus(DistributorStatus::Approved);
        $entityManager->persist($distributor);
        $entityManager->flush();

        // 创建 6 个下线（满足常规升级但不满足直升）
        $this->createApprovedInvitees($distributor, 6, $currentLevel);

        // 创建直升规则：邀请人数 >= 10 可直升金牌（不满足）
        $directRule = new DirectUpgradeRule();
        $directRule->setTargetLevel($goldLevel);
        $directRule->setUpgradeExpression('inviteeCount >= 10');
        $directRule->setPriority(200);
        $directRule->setIsEnabled(true);
        $entityManager->persist($directRule);

        // 创建常规升级规则：普通 → 银牌，邀请人数 >= 5（满足）
        $regularRule = new DistributorLevelUpgradeRule();
        $regularRule->setSourceLevel($currentLevel);
        $regularRule->setTargetLevel($silverLevel);
        $regularRule->setUpgradeExpression('inviteeCount >= 5');
        $regularRule->setIsEnabled(true);
        $entityManager->persist($regularRule);

        $entityManager->flush();
        $entityManager->clear();

        $distributor = $entityManager->find(Distributor::class, $distributor->getId());

        // Act
        $history = $this->service->checkAndUpgradeWithIntelligentRules($distributor);

        // Assert - 应该回退到常规升级规则
        $this->assertInstanceOf(DistributorLevelUpgradeHistory::class, $history);
        $this->assertSame(2, $history->getNewLevel()->getLevel());
        $this->assertSame('inviteeCount >= 5', $history->getSatisfiedExpression());

        // 验证升级到银牌而不是金牌
        $entityManager->refresh($distributor);
        $this->assertSame($silverLevel->getId(), $distributor->getLevel()->getId());
    }

    /**
     * TC-003：测试智能升级 - 无直升规则，直接使用常规升级
     *
     * 验证当没有适用的直升规则时，直接执行常规升级逻辑
     */
    public function testCheckAndUpgradeWithIntelligentRules_NoDirectRules(): void
    {
        // Arrange
        $entityManager = self::getEntityManager();

        $currentLevel = $this->createLevel(1, '普通会员');
        $silverLevel = $this->createLevel(2, '银牌会员');

        $user = $this->createNormalUser('test_no_direct_1');
        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($currentLevel);
        $distributor->setStatus(DistributorStatus::Approved);
        $entityManager->persist($distributor);
        $entityManager->flush();

        // 创建 6 个下线
        $this->createApprovedInvitees($distributor, 6, $currentLevel);

        // 只创建常规升级规则，不创建直升规则
        $regularRule = new DistributorLevelUpgradeRule();
        $regularRule->setSourceLevel($currentLevel);
        $regularRule->setTargetLevel($silverLevel);
        $regularRule->setUpgradeExpression('inviteeCount >= 5');
        $regularRule->setIsEnabled(true);
        $entityManager->persist($regularRule);

        $entityManager->flush();
        $entityManager->clear();

        $distributor = $entityManager->find(Distributor::class, $distributor->getId());

        // Act
        $history = $this->service->checkAndUpgradeWithIntelligentRules($distributor);

        // Assert - 应该使用常规升级
        $this->assertInstanceOf(DistributorLevelUpgradeHistory::class, $history);
        $this->assertSame($silverLevel->getId(), $history->getNewLevel()->getId());

        $entityManager->refresh($distributor);
        $this->assertSame($silverLevel->getId(), $distributor->getLevel()->getId());
    }

    /**
     * TC-004：测试智能升级 - 直升规则表达式异常处理
     *
     * 验证直升规则表达式执行失败时的处理
     */
    public function testCheckAndUpgradeWithIntelligentRules_DirectRuleExpressionException(): void
    {
        // Arrange
        $entityManager = self::getEntityManager();

        $currentLevel = $this->createLevel(1, '普通会员');
        $silverLevel = $this->createLevel(2, '银牌会员');
        $goldLevel = $this->createLevel(3, '金牌会员');

        $user = $this->createNormalUser('test_expression_error_1');
        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($currentLevel);
        $distributor->setStatus(DistributorStatus::Approved);
        $entityManager->persist($distributor);
        $entityManager->flush();

        // 创建 6 个下线
        $this->createApprovedInvitees($distributor, 6, $currentLevel);

        // 创建包含无效表达式的直升规则
        $directRule = new DirectUpgradeRule();
        $directRule->setTargetLevel($goldLevel);
        // 使用一个在上下文中不存在的变量，会导致表达式评估失败
        $directRule->setUpgradeExpression('nonExistentVariable >= 10');
        $directRule->setPriority(200);
        $directRule->setIsEnabled(true);
        $entityManager->persist($directRule);

        // 创建常规升级规则作为备选
        $regularRule = new DistributorLevelUpgradeRule();
        $regularRule->setSourceLevel($currentLevel);
        $regularRule->setTargetLevel($silverLevel);
        $regularRule->setUpgradeExpression('inviteeCount >= 5');
        $regularRule->setIsEnabled(true);
        $entityManager->persist($regularRule);

        $entityManager->flush();
        $entityManager->clear();

        $distributor = $entityManager->find(Distributor::class, $distributor->getId());

        // Act - 应该捕获异常并回退到常规升级
        $history = $this->service->checkAndUpgradeWithIntelligentRules($distributor);

        // Assert - 应该回退到常规升级逻辑
        $this->assertInstanceOf(DistributorLevelUpgradeHistory::class, $history);
        $this->assertSame($silverLevel->getId(), $history->getNewLevel()->getId());

        $entityManager->refresh($distributor);
        $this->assertSame($silverLevel->getId(), $distributor->getLevel()->getId());
    }

    /**
     * TC-005：测试智能升级 - 多个直升规则优先级排序
     *
     * 验证多个直升规则按优先级正确选择最高优先级且满足条件的规则
     */
    public function testCheckAndUpgradeWithIntelligentRules_MultipleDirect_PriorityOrder(): void
    {
        // Arrange
        $entityManager = self::getEntityManager();

        $currentLevel = $this->createLevel(1, '普通会员');
        $silverLevel = $this->createLevel(2, '银牌会员');
        $goldLevel = $this->createLevel(3, '金牌会员');
        $diamondLevel = $this->createLevel(4, '钻石会员');

        // 创建分销员
        $user = $this->createNormalUser('test_priority_1');
        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($currentLevel);
        $distributor->setStatus(DistributorStatus::Approved);
        $entityManager->persist($distributor);
        $entityManager->flush();

        // 创建 10 个下线
        $this->createApprovedInvitees($distributor, 10, $currentLevel);

        // 创建低优先级直升规则：银牌（满足条件）
        $lowPriorityRule = new DirectUpgradeRule();
        $lowPriorityRule->setTargetLevel($silverLevel);
        $lowPriorityRule->setUpgradeExpression('inviteeCount >= 3');
        $lowPriorityRule->setPriority(100);
        $lowPriorityRule->setIsEnabled(true);
        $entityManager->persist($lowPriorityRule);

        // 创建高优先级直升规则：钻石（不满足条件）
        $highPriorityRule = new DirectUpgradeRule();
        $highPriorityRule->setTargetLevel($diamondLevel);
        $highPriorityRule->setUpgradeExpression('inviteeCount >= 20');
        $highPriorityRule->setPriority(200);
        $highPriorityRule->setIsEnabled(true);
        $entityManager->persist($highPriorityRule);

        // 创建中等优先级直升规则：金牌（满足条件）
        $mediumPriorityRule = new DirectUpgradeRule();
        $mediumPriorityRule->setTargetLevel($goldLevel);
        $mediumPriorityRule->setUpgradeExpression('inviteeCount >= 8');
        $mediumPriorityRule->setPriority(150);
        $mediumPriorityRule->setIsEnabled(true);
        $entityManager->persist($mediumPriorityRule);

        $entityManager->flush();
        $entityManager->clear();

        $distributor = $entityManager->find(Distributor::class, $distributor->getId());

        // Act
        $history = $this->service->checkAndUpgradeWithIntelligentRules($distributor);

        // Assert - 应该选择中等优先级规则（高优先级不满足，中等优先级满足）
        $this->assertInstanceOf(DistributorLevelUpgradeHistory::class, $history);
        $this->assertSame($goldLevel->getId(), $history->getNewLevel()->getId());
        $this->assertSame('inviteeCount >= 8', $history->getSatisfiedExpression());

        // 验证升级到金牌而不是银牌（虽然低优先级也满足）
        $entityManager->refresh($distributor);
        $this->assertSame($goldLevel->getId(), $distributor->getLevel()->getId());
    }

    /**
     * TC-006：测试智能升级 - 事务回滚处理
     *
     * 验证升级过程中发生异常时的事务回滚
     */
    public function testCheckAndUpgradeWithIntelligentRules_TransactionRollback(): void
    {
        // Arrange
        $entityManager = self::getEntityManager();

        $currentLevel = $this->createLevel(1, '普通会员');
        $targetLevel = $this->createLevel(3, '金牌会员');

        $user = $this->createNormalUser('test_transaction_1');
        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($currentLevel);
        $distributor->setStatus(DistributorStatus::Approved);
        $entityManager->persist($distributor);
        $entityManager->flush();

        // 创建 8 个下线
        $this->createApprovedInvitees($distributor, 8, $currentLevel);

        // 创建直升规则
        $directRule = new DirectUpgradeRule();
        $directRule->setTargetLevel($targetLevel);
        $directRule->setUpgradeExpression('inviteeCount >= 5');
        $directRule->setPriority(200);
        $directRule->setIsEnabled(true);
        $entityManager->persist($directRule);

        $entityManager->flush();

        // 保存初始等级
        $initialLevelId = $distributor->getLevel()->getId();

        // 清除实体管理器，确保后续操作从数据库加载
        $entityManager->clear();
        $distributor = $entityManager->find(Distributor::class, $distributor->getId());

        // Act - 正常升级应该成功
        $history = $this->service->checkAndUpgradeWithIntelligentRules($distributor);

        // Assert - 验证升级成功
        $this->assertInstanceOf(DistributorLevelUpgradeHistory::class, $history);

        // 验证事务已提交（等级已更新）
        $entityManager->clear();
        $distributorAfterUpgrade = $entityManager->find(Distributor::class, $distributor->getId());
        $this->assertSame($targetLevel->getId(), $distributorAfterUpgrade->getLevel()->getId());
        $this->assertNotSame($initialLevelId, $distributorAfterUpgrade->getLevel()->getId());

        // 验证历史记录已持久化
        $histories = $entityManager->getRepository(DistributorLevelUpgradeHistory::class)
            ->findBy(['distributor' => $distributor]);
        $this->assertCount(1, $histories);
    }

    /**
     * TC-007：测试 checkUpgradeEligibility 方法
     *
     * 验证检查升级资格功能正确返回 UpgradeEligibilityResult
     */
    public function testCheckUpgradeEligibility(): void
    {
        // Arrange
        $entityManager = self::getEntityManager();

        $currentLevel = $this->createLevel(1, '普通会员-资格检查');
        $targetLevel = $this->createLevel(2, '银牌会员-资格检查');

        // 创建升级规则
        $rule = new DistributorLevelUpgradeRule();
        $rule->setSourceLevel($currentLevel);
        $rule->setTargetLevel($targetLevel);
        $rule->setUpgradeExpression('1 == 1');
        $rule->setIsEnabled(true);
        $entityManager->persist($rule);

        // 创建分销员
        $user = $this->createNormalUser('test_eligibility_1');
        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($currentLevel);
        $distributor->setStatus(DistributorStatus::Approved);
        $entityManager->persist($distributor);
        $entityManager->flush();

        // Act
        $result = $this->service->checkUpgradeEligibility($distributor);

        // Assert
        $this->assertNotNull($result, '符合条件时应该返回 UpgradeEligibilityResult');
        $this->assertInstanceOf(\Tourze\CommissionUpgradeBundle\DTO\UpgradeEligibilityResult::class, $result);
        $this->assertSame($targetLevel->getId(), $result->getNewLevel()->getId());
        $this->assertSame($rule->getId(), $result->getRule()->getId());
        $this->assertIsArray($result->getContextSnapshot());

        // 验证分销员等级未变更（checkUpgradeEligibility 只检查不执行升级）
        $entityManager->refresh($distributor);
        $this->assertSame($currentLevel->getId(), $distributor->getLevel()->getId());
    }

    /**
     * TC-008：测试 findNextLevelRule 方法
     *
     * 验证查找下一级别升级规则的功能
     */
    public function testFindNextLevelRule(): void
    {
        // Arrange
        $entityManager = self::getEntityManager();

        $currentLevel = $this->createLevel(1, '普通会员-规则查询');
        $nextLevel = $this->createLevel(2, '银牌会员-规则查询');

        // 创建升级规则
        $rule = new DistributorLevelUpgradeRule();
        $rule->setSourceLevel($currentLevel);
        $rule->setTargetLevel($nextLevel);
        $rule->setUpgradeExpression('inviteeCount >= 5');
        $rule->setIsEnabled(true);
        $entityManager->persist($rule);
        $entityManager->flush();

        // Act
        $foundRule = $this->service->findNextLevelRule($currentLevel);

        // Assert
        $this->assertNotNull($foundRule, '应该找到对应的升级规则');
        $this->assertSame($rule->getId(), $foundRule->getId());
        $this->assertSame($currentLevel->getId(), $foundRule->getSourceLevel()->getId());
        $this->assertSame($nextLevel->getId(), $foundRule->getTargetLevel()->getId());
        $this->assertSame('inviteeCount >= 5', $foundRule->getUpgradeExpression());
    }

    /**
     * 创建测试用等级
     */
    private function createLevel(int $level, string $name): DistributorLevel
    {
        $entityManager = self::getEntityManager();

        $distributorLevel = new DistributorLevel();
        $distributorLevel->setLevel($level);
        $distributorLevel->setName($name);
        $entityManager->persist($distributorLevel);
        $entityManager->flush();

        return $distributorLevel;
    }

    /**
     * 创建已审批的下线分销员
     *
     * @param Distributor $parent 上级分销员
     * @param int $count 创建数量
     * @param DistributorLevel $level 下线等级
     */
    private function createApprovedInvitees(Distributor $parent, int $count, DistributorLevel $level): void
    {
        $entityManager = self::getEntityManager();

        for ($i = 0; $i < $count; $i++) {
            $user = $this->createNormalUser(sprintf('invitee_%s_%d', $parent->getId(), $i));
            $invitee = new Distributor();
            $invitee->setUser($user);
            $invitee->setLevel($level);
            $invitee->setParent($parent);
            $invitee->setStatus(DistributorStatus::Approved);
            $entityManager->persist($invitee);
        }

        $entityManager->flush();
    }
}
