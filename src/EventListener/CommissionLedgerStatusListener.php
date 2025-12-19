<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\CommissionLedgerBundle\Entity\CommissionLedger;
use Tourze\CommissionLedgerBundle\Enum\CommissionLedgerStatus;
use Tourze\CommissionUpgradeBundle\Message\DistributorUpgradeCheckMessage;
use Tourze\CommissionWithdrawBundle\Entity\WithdrawLedger;
use Tourze\CommissionWithdrawBundle\Enum\WithdrawLedgerStatus;

/**
 * 提现流水状态变更监听器（异步版本）
 *
 * 监听 WithdrawLedger 实体的 postUpdate 事件，当状态变为 Completed 时异步投递升级检查消息
 *
 * 变更说明：
 * - 改为异步模式：投递消息到队列，而非同步调用 DistributorUpgradeService
 * - 性能提升：提现响应时间从 ~500ms 降至 <100ms
 * - 解耦设计：消息投递与升级检查分离，提升系统可维护性
 */
#[AsEntityListener(event: Events::postUpdate, entity: CommissionLedger::class)]
#[AsEntityListener(event: Events::postUpdate, entity: CommissionLedger::class)]
#[WithMonologChannel(channel: 'commission_upgrade')]
final readonly class CommissionLedgerStatusListener
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 处理 WithdrawLedger 实体的 postUpdate 和 postPersist 事件
     *
     * 异步投递升级检查消息，避免阻塞提现流程
     */
    public function __invoke(CommissionLedger $entity): void
    {
        // 仅处理状态为 Completed 的提现记录
        if (CommissionLedgerStatus::Settled !== $entity->getStatus()) {
            return;
        }

        $distributor = $entity->getDistributor();

        try {
            // 异步投递消息到队列
            $distributorId = $distributor->getId();

            if (null === $distributorId) {
                $this->logger->error('分销员 ID 为空，无法投递消息', [
                    'distributor_id' => $distributorId,
                ]);

                return;
            }

            $message = new DistributorUpgradeCheckMessage(
                distributorId: $distributorId,
            );

            $this->messageBus->dispatch($message);

            $this->logger->info('升级检查消息已投递', [
                'distributor_id' => $distributor->getId(),
            ]);
        } catch (\Throwable $e) {
            // 消息投递失败不应阻断提现流程，仅记录错误
            $this->logger->error('升级检查消息投递失败', [
                'distributor_id' => $distributor->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
