<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tourze\CommissionUpgradeBundle\Service\DistributorUpgradeService;
use Tourze\OrderCommissionBundle\Entity\WithdrawLedger;
use Tourze\OrderCommissionBundle\Enum\WithdrawLedgerStatus;

/**
 * 提现流水状态变更监听器.
 *
 * 监听WithdrawLedger实体的postUpdate事件,当状态变为Completed时触发升级检查
 */
#[AsEntityListener(event: Events::postUpdate, entity: WithdrawLedger::class)]
#[AsEntityListener(event: Events::postPersist, entity: WithdrawLedger::class)]
final class WithdrawLedgerStatusListener
{
    public function __construct(
        private DistributorUpgradeService $upgradeService,
        private ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * 处理WithdrawLedger实体的postUpdate和postPersist事件.
     *
     * @param LifecycleEventArgs<WithdrawLedger> $args
     */
    public function __invoke(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        // 类型安全检查（虽然AsEntityListener已限制类型，但保持防御性）
        if (!$entity instanceof WithdrawLedger) {
            return;
        }

        // 仅处理状态为Completed的提现记录
        if (WithdrawLedgerStatus::Completed !== $entity->getStatus()) {
            return;
        }

        $distributor = $entity->getDistributor();

        try {
            $history = $this->upgradeService->checkAndUpgrade($distributor, $entity);

            if (null !== $history) {
                $this->logger->info('提现成功触发分销员升级', [
                    'withdraw_ledger_id' => $entity->getId(),
                    'distributor_id' => $distributor->getId(),
                    'previous_level' => $history->getPreviousLevel()->getName(),
                    'new_level' => $history->getNewLevel()->getName(),
                ]);
            }
        } catch (\Throwable $e) {
            // 升级失败不应阻断提现流程,仅记录错误日志
            $this->logger->error('提现成功后升级检查失败', [
                'withdraw_ledger_id' => $entity->getId(),
                'distributor_id' => $distributor->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
