<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\DTO;

use Tourze\CommissionDistributorBundle\Entity\Distributor;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeRule;

/**
 * 手动升级检测结果 DTO.
 *
 * 用于在检测和升级之间传递数据,也用于前端展示检测结果
 */
class ManualUpgradeCheckResult
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private Distributor $distributor,
        private DistributorLevel $currentLevel,
        private bool $canUpgrade,
        private ?DistributorLevel $targetLevel = null,
        private ?DistributorLevelUpgradeRule $upgradeRule = null,
        private array $context = [],
        private ?\DateTimeImmutable $checkTime = null,
        private ?string $failureReason = null,
    ) {
        $this->checkTime = $checkTime ?? new \DateTimeImmutable();
    }

    public function getDistributor(): Distributor
    {
        return $this->distributor;
    }

    public function getCurrentLevel(): DistributorLevel
    {
        return $this->currentLevel;
    }

    public function canUpgrade(): bool
    {
        return $this->canUpgrade;
    }

    public function getTargetLevel(): ?DistributorLevel
    {
        return $this->targetLevel;
    }

    public function getUpgradeRule(): ?DistributorLevelUpgradeRule
    {
        return $this->upgradeRule;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getCheckTime(): \DateTimeImmutable
    {
        return $this->checkTime;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    /**
     * 转换为数组（用于 Session 存储）.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'distributor_id' => $this->distributor->getId(),
            'current_level_id' => $this->currentLevel->getId(),
            'current_level_name' => $this->currentLevel->getName(),
            'can_upgrade' => $this->canUpgrade,
            'target_level_id' => $this->targetLevel?->getId(),
            'target_level_name' => $this->targetLevel?->getName(),
            'upgrade_expression' => $this->upgradeRule?->getUpgradeExpression(),
            'context' => $this->context,
            'check_time' => $this->checkTime->format('Y-m-d H:i:s'),
            'failure_reason' => $this->failureReason,
        ];
    }

    /**
     * 从数组创建（用于从 Session 恢复）.
     *
     * 注意：仅恢复基础数据，实体对象需要从数据库重新加载
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        // 占位实现,实际使用时需要从数据库重新加载实体
        throw new \LogicException('fromArray() requires database access to reload entities. Use Service layer instead.');
    }
}
