<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\DTO;

use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeRule;

/**
 * 升级资格检查结果 DTO.
 *
 * 用于在检测条件满足后返回升级相关信息
 */
class UpgradeEligibilityResult
{
    /**
     * @param DistributorLevel             $newLevel        目标等级
     * @param DistributorLevelUpgradeRule  $rule            升级规则
     * @param array<string, mixed>         $contextSnapshot 上下文快照
     */
    public function __construct(
        private DistributorLevel $newLevel,
        private DistributorLevelUpgradeRule $rule,
        private array $contextSnapshot,
    ) {
    }

    public function getNewLevel(): DistributorLevel
    {
        return $this->newLevel;
    }

    public function getRule(): DistributorLevelUpgradeRule
    {
        return $this->rule;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContextSnapshot(): array
    {
        return $this->contextSnapshot;
    }
}
