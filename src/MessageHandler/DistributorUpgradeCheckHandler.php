<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Tourze\CommissionDistributorBundle\Entity\Distributor;
use Tourze\CommissionUpgradeBundle\Message\DistributorUpgradeCheckMessage;
use Tourze\CommissionUpgradeBundle\Service\DistributorUpgradeService;

/**
 * 分销员升级检查消息处理器
 *
 * 功能：消费 DistributorUpgradeCheckMessage 消息，执行异步升级检查
 *
 * 职责：
 * - 从数据库查询分销员实体
 * - 调用 DistributorUpgradeService 执行升级检查逻辑（复用现有服务）
 * - 记录结构化日志（成功、失败、跳过）
 * - 区分可重试异常（数据库错误）与不可重试场景（分销员不存在）
 *
 * 错误处理策略：
 * - 分销员不存在：记录警告日志，静默返回（避免无限重试）
 * - 升级检查异常：记录错误日志，重新抛出异常（触发 Messenger 重试机制）
 *
 * @see DistributorUpgradeCheckMessage
 * @see DistributorUpgradeService
 */
#[AsMessageHandler]
#[WithMonologChannel(channel: 'commission_upgrade')]
final readonly class DistributorUpgradeCheckHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DistributorUpgradeService $upgradeService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 处理升级检查消息
     *
     * @throws \Throwable 升级检查过程中的异常（触发重试）
     */
    public function __invoke(DistributorUpgradeCheckMessage $message): void
    {
        // 结构化日志：消息处理开始
        $this->logger->debug('开始处理升级检查消息', [
            'distributor_id' => $message->distributorId,
        ]);

        // 1. 查询分销员实体
        $distributor = $this->entityManager->find(Distributor::class, $message->distributorId);
        if (null === $distributor) {
            $this->logger->warning('分销员不存在，跳过升级检查', [
                'distributor_id' => $message->distributorId,
            ]);

            return; // 静默返回，避免无限重试
        }

        // 2. 执行升级检查（复用现有服务）
        try {
            $history = $this->upgradeService->checkAndUpgradeWithIntelligentRules($distributor, null);

            if (null !== $history) {
                // 升级成功
                $this->logger->info('分销员升级成功', [
                    'distributor_id' => $distributor->getId(),
                    'previous_level' => $history->getPreviousLevel()->getName(),
                    'new_level' => $history->getNewLevel()->getName(),
                ]);
            } else {
                // 不满足升级条件
                $this->logger->debug('升级条件不满足', [
                    'distributor_id' => $distributor->getId(),
                    'current_level' => $distributor->getLevel()->getName(),
                ]);
            }
        } catch (\Throwable $e) {
            // 升级检查过程中的异常
            $this->logger->error('升级检查失败', [
                'distributor_id' => $distributor->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e; // 重新抛出，触发 Messenger 重试机制
        }
    }
}
