<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tourze\CommissionDistributorBundle\Entity\Distributor;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\DTO\UpgradeEligibilityResult;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeHistory;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeRule;
use Tourze\CommissionUpgradeBundle\Entity\DirectUpgradeRule;
use Tourze\CommissionUpgradeBundle\Repository\DistributorLevelUpgradeRuleRepository;
use Tourze\CommissionUpgradeBundle\Repository\DirectUpgradeRuleRepository;
use Tourze\CommissionWithdrawBundle\Entity\WithdrawLedger;

/**
 * 分销员等级升级核心服务.
 *
 * 负责升级检查、升级执行、等级查询和幂等性保障
 */
#[WithMonologChannel(channel: 'commission_upgrade')]
class DistributorUpgradeService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UpgradeContextProvider $contextProvider,
        private UpgradeExpressionEvaluator $expressionEvaluator,
        private DistributorLevelUpgradeRuleRepository $upgradeRuleRepository,
        private DirectUpgradeRuleRepository $directUpgradeRuleRepository,
        private ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * 检查分销员是否满足升级条件（仅检查不执行）.
     *
     * @param Distributor $distributor 待检查的分销员
     *
     * @return UpgradeEligibilityResult|null 满足升级条件时返回结果对象，否则返回null
     */
    public function checkUpgradeEligibility(Distributor $distributor): ?UpgradeEligibilityResult
    {
        $currentLevel = $distributor->getLevel();
        $upgradeRule = $this->findNextLevelRule($currentLevel);

        if (null === $upgradeRule) {
            $this->logger->debug('未找到升级规则,可能已达最高等级', [
                'distributor_id' => $distributor->getId(),
                'current_level' => $currentLevel->getName(),
            ]);

            return null;
        }

        $context = $this->contextProvider->buildContext($distributor);

        try {
            $satisfied = $this->expressionEvaluator->evaluate(
                $upgradeRule->getUpgradeExpression(),
                $context
            );
        } catch (\RuntimeException $e) {
            $this->logger->error('升级条件评估失败', [
                'distributor_id' => $distributor->getId(),
                'current_level' => $currentLevel->getName(),
                'next_level' => $upgradeRule->getTargetLevel()->getName(),
                'expression' => $upgradeRule->getUpgradeExpression(),
                'context' => $context,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (!$satisfied) {
            $this->logger->debug('升级条件不满足', [
                'distributor_id' => $distributor->getId(),
                'current_level' => $currentLevel->getName(),
                'next_level' => $upgradeRule->getTargetLevel()->getName(),
                'expression' => $upgradeRule->getUpgradeExpression(),
                'context' => $context,
            ]);

            return null;
        }

        return new UpgradeEligibilityResult(
            $upgradeRule->getTargetLevel(),
            $upgradeRule,
            $context
        );
    }

    /**
     * 检查分销员是否满足升级条件,如果满足则执行升级.
     *
     * @param Distributor         $distributor       待检查的分销员
     * @param WithdrawLedger|null $triggeringLedger  触发升级检查的提现流水(可选,用于记录)
     *
     * @return DistributorLevelUpgradeHistory|null 升级成功时返回升级历史记录,否则返回null
     *
     * @throws OptimisticLockException 并发冲突时抛出(调用方应重试)
     * @throws \RuntimeException                     表达式执行失败或其他运行时错误
     */
    public function checkAndUpgrade(
        Distributor $distributor,
        ?WithdrawLedger $triggeringLedger = null,
    ): ?DistributorLevelUpgradeHistory {
        // 1. 获取当前等级
        $currentLevel = $distributor->getLevel();

        // 2. 查找下一级别的升级规则
        $upgradeRule = $this->findNextLevelRule($currentLevel);
        if (null === $upgradeRule) {
            $this->logger->debug('未找到升级规则,可能已达最高等级', [
                'distributor_id' => $distributor->getId(),
                'current_level' => $currentLevel->getName(),
            ]);

            return null; // 已达最高等级或未配置升级规则
        }

        // 3. 构建上下文变量
        $context = $this->contextProvider->buildContext($distributor);

        // 4. 评估升级条件表达式
        try {
            $satisfied = $this->expressionEvaluator->evaluate(
                $upgradeRule->getUpgradeExpression(),
                $context
            );
        } catch (\RuntimeException $e) {
            $this->logger->error('升级条件评估失败', [
                'distributor_id' => $distributor->getId(),
                'current_level' => $currentLevel->getName(),
                'next_level' => $upgradeRule->getTargetLevel()->getName(),
                'expression' => $upgradeRule->getUpgradeExpression(),
                'context' => $context,
                'error' => $e->getMessage(),
            ]);

            return null; // 表达式执行失败,视为条件不满足
        }

        if (!$satisfied) {
            $this->logger->debug('升级条件不满足', [
                'distributor_id' => $distributor->getId(),
                'current_level' => $currentLevel->getName(),
                'next_level' => $upgradeRule->getTargetLevel()->getName(),
                'expression' => $upgradeRule->getUpgradeExpression(),
                'context' => $context,
            ]);

            return null; // 条件不满足
        }

        // 5. 执行升级操作(事务)
        return $this->performUpgrade(
            $distributor,
            $currentLevel,
            $upgradeRule->getTargetLevel(),
            $upgradeRule,
            $context,
            $triggeringLedger
        );
    }

    /**
     * 查找当前等级对应的升级规则(下一级别).
     *
     * @param DistributorLevel $currentLevel 当前等级
     *
     * @return DistributorLevelUpgradeRule|null 下一级别的升级规则,如已达最高等级则返回null
     */
    public function findNextLevelRule(DistributorLevel $currentLevel): ?DistributorLevelUpgradeRule
    {
        return $this->upgradeRuleRepository->findBySourceLevel($currentLevel);
    }

    /**
     * 智能升级检查：优先检查直升规则，如果不满足则检查常规升级规则.
     *
     * @param Distributor         $distributor       待检查的分销员
     * @param WithdrawLedger|null $triggeringLedger  触发升级检查的提现流水(可选)
     *
     * @return DistributorLevelUpgradeHistory|null 升级成功时返回升级历史记录,否则返回null
     *
     * @throws OptimisticLockException 并发冲突时抛出(调用方应重试)
     * @throws \RuntimeException                     表达式执行失败或其他运行时错误
     */
    public function checkAndUpgradeWithIntelligentRules(
        Distributor $distributor,
        ?WithdrawLedger $triggeringLedger = null,
    ): ?DistributorLevelUpgradeHistory {
        // 1. 构建上下文变量（只需要构建一次）
        $context = $this->contextProvider->buildContext($distributor);

        // 2. 优先检查直升规则
        $directUpgrade = $this->findBestDirectUpgrade($distributor, $context);
        if (null !== $directUpgrade) {
            return $this->performUpgradeWithRuleType(
                $distributor,
                $distributor->getLevel(),
                $directUpgrade['targetLevel'],
                $directUpgrade['expression'],
                'direct',
                $context,
                $triggeringLedger
            );
        }

        // 3. 直升规则不满足，检查常规升级规则
        return $this->checkAndUpgrade($distributor, $triggeringLedger);
    }

    /**
     * 查找最佳直升规则.
     *
     * @param Distributor $distributor 分销员
     * @param array<string, mixed> $context 上下文变量
     *
     * @return array{targetLevel: DistributorLevel, expression: string}|null 最佳直升规则信息
     */
    private function findBestDirectUpgrade(Distributor $distributor, array $context): ?array
    {
        $currentLevel = $distributor->getLevel();
        $eligibleRules = $this->directUpgradeRuleRepository->findEligibleRulesForLevel($currentLevel);

        foreach ($eligibleRules as $rule) {
            try {
                $satisfied = $this->expressionEvaluator->evaluate(
                    $rule->getUpgradeExpression(),
                    $context
                );

                if ($satisfied) {
                    $this->logger->debug('找到满足条件的直升规则', [
                        'distributor_id' => $distributor->getId(),
                        'current_level' => $currentLevel->getName(),
                        'target_level' => $rule->getTargetLevel()->getName(),
                        'expression' => $rule->getUpgradeExpression(),
                        'priority' => $rule->getPriority(),
                    ]);

                    return [
                        'targetLevel' => $rule->getTargetLevel(),
                        'expression' => $rule->getUpgradeExpression(),
                    ];
                }
            } catch (\RuntimeException $e) {
                $this->logger->warning('直升规则表达式评估失败', [
                    'distributor_id' => $distributor->getId(),
                    'rule_target' => $rule->getTargetLevel()->getName(),
                    'expression' => $rule->getUpgradeExpression(),
                    'error' => $e->getMessage(),
                ]);
                // 继续检查下一个规则
                continue;
            }
        }

        $this->logger->debug('未找到满足条件的直升规则', [
            'distributor_id' => $distributor->getId(),
            'current_level' => $currentLevel->getName(),
            'checked_rules_count' => count($eligibleRules),
        ]);

        return null;
    }

    /**
     * 执行升级操作(原子事务)，支持规则类型标记.
     *
     * @param Distributor         $distributor       分销员
     * @param DistributorLevel    $previousLevel     升级前等级
     * @param DistributorLevel    $newLevel          升级后等级
     * @param string              $expression        升级表达式
     * @param string              $ruleType          规则类型 'regular' | 'direct'
     * @param array<string, mixed> $context          上下文变量快照
     * @param WithdrawLedger|null $triggeringLedger  触发的提现流水(可选)
     *
     * @return DistributorLevelUpgradeHistory 升级历史记录
     *
     * @throws OptimisticLockException 并发冲突
     * @throws \RuntimeException                     其他运行时错误
     */
    private function performUpgradeWithRuleType(
        Distributor $distributor,
        DistributorLevel $previousLevel,
        DistributorLevel $newLevel,
        string $expression,
        string $ruleType,
        array $context,
        ?WithdrawLedger $triggeringLedger,
    ): DistributorLevelUpgradeHistory {
        $this->entityManager->beginTransaction();

        try {
            // 1. 更新分销员等级
            $distributor->setLevel($newLevel);
            $this->entityManager->persist($distributor);

            // 2. 创建升级历史记录
            $history = new DistributorLevelUpgradeHistory();
            $history->setDistributor($distributor);
            $history->setPreviousLevel($previousLevel);
            $history->setNewLevel($newLevel);
            $history->setSatisfiedExpression($expression);
            $history->setContextSnapshot($context);
            $history->setTriggeringWithdrawLedger($triggeringLedger);
            $history->setUpgradeTime(new \DateTimeImmutable());

            $this->entityManager->persist($history);

            // 3. 提交事务
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('分销员升级成功', [
                'distributor_id' => $distributor->getId(),
                'previous_level' => $previousLevel->getName(),
                'new_level' => $newLevel->getName(),
                'rule_type' => $ruleType,
                'expression' => $expression,
                'context' => $context,
            ]);

            return $history;
        } catch (\Throwable $e) {
            $this->entityManager->rollback();

            $this->logger->error('分销员升级失败', [
                'distributor_id' => $distributor->getId(),
                'previous_level' => $previousLevel->getName(),
                'new_level' => $newLevel->getName(),
                'rule_type' => $ruleType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * 执行升级操作(原子事务).
     *
     * @param Distributor                   $distributor       分销员
     * @param DistributorLevel              $previousLevel     升级前等级
     * @param DistributorLevel              $newLevel          升级后等级
     * @param DistributorLevelUpgradeRule   $rule              升级规则
     * @param array<string, mixed>          $context           上下文变量快照
     * @param WithdrawLedger|null           $triggeringLedger  触发的提现流水(可选)
     *
     * @return DistributorLevelUpgradeHistory 升级历史记录
     *
     * @throws OptimisticLockException 并发冲突
     * @throws \RuntimeException                     其他运行时错误
     */
    private function performUpgrade(
        Distributor $distributor,
        DistributorLevel $previousLevel,
        DistributorLevel $newLevel,
        DistributorLevelUpgradeRule $rule,
        array $context,
        ?WithdrawLedger $triggeringLedger,
    ): DistributorLevelUpgradeHistory {
        return $this->performUpgradeWithRuleType(
            $distributor,
            $previousLevel,
            $newLevel,
            $rule->getUpgradeExpression(),
            'regular',
            $context,
            $triggeringLedger
        );
    }
}
