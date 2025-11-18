<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\CommissionUpgradeBundle\Repository\DistributorLevelUpgradeHistoryRepository;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\OrderCommissionBundle\Entity\Distributor;
use Tourze\OrderCommissionBundle\Entity\DistributorLevel;
use Tourze\OrderCommissionBundle\Entity\WithdrawLedger;

/**
 * 分销员等级升级历史.
 *
 * 记录每次分销员等级升级事件的完整信息,支持审计、统计和问题排查
 */
#[ORM\Entity(repositoryClass: DistributorLevelUpgradeHistoryRepository::class)]
#[ORM\Table(
    name: 'commission_upgrade_distributor_level_upgrade_history',
    options: ['comment' => '分销员等级升级历史']
)]
#[ORM\Index(name: 'commission_upgrade_distributor_level_upgrade_history_idx_dist_time', columns: ['distributor_id', 'upgrade_time'])]
#[ORM\Index(name: 'commission_upgrade_distributor_level_upgrade_history_idx_time', columns: ['upgrade_time'])]
class DistributorLevelUpgradeHistory implements \Stringable
{
    use SnowflakeKeyAware;
    use TimestampableAware;

    /**
     * 所属分销员.
     */
    #[ORM\ManyToOne(targetEntity: Distributor::class)]
    #[ORM\JoinColumn(name: 'distributor_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Distributor $distributor;

    /**
     * 原等级（升级前）.
     */
    #[ORM\ManyToOne(targetEntity: DistributorLevel::class)]
    #[ORM\JoinColumn(name: 'previous_level_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private DistributorLevel $previousLevel;

    /**
     * 新等级（升级后）.
     */
    #[ORM\ManyToOne(targetEntity: DistributorLevel::class)]
    #[ORM\JoinColumn(name: 'new_level_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private DistributorLevel $newLevel;

    #[ORM\Column(
        name: 'satisfied_expression',
        type: Types::TEXT,
        nullable: false,
        options: ['comment' => '满足的升级条件表达式（触发升级的表达式字符串）']
    )]
    #[Assert\NotBlank(message: '满足的升级条件表达式不能为空')]
    private string $satisfiedExpression;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(
        name: 'context_snapshot',
        type: Types::JSON,
        nullable: false,
        options: ['comment' => '升级判断上下文快照（JSON格式，包含升级时的变量值）']
    )]
    #[Assert\Type(type: 'array', message: '上下文快照必须为数组')]
    private array $contextSnapshot = [];

    /**
     * 触发升级的提现流水（可选）.
     *
     * 记录是哪笔提现成功后触发的升级检查
     */
    #[ORM\ManyToOne(targetEntity: WithdrawLedger::class)]
    #[ORM\JoinColumn(name: 'triggering_withdraw_ledger_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?WithdrawLedger $triggeringWithdrawLedger = null;

    #[ORM\Column(
        name: 'upgrade_time',
        type: Types::DATETIME_IMMUTABLE,
        nullable: false,
        options: ['comment' => '升级时间']
    )]
    #[Assert\NotNull(message: '升级时间不能为空')]
    private \DateTimeImmutable $upgradeTime;

    public function getDistributor(): Distributor
    {
        return $this->distributor;
    }

    public function setDistributor(Distributor $distributor): self
    {
        $this->distributor = $distributor;

        return $this;
    }

    public function getPreviousLevel(): DistributorLevel
    {
        return $this->previousLevel;
    }

    public function setPreviousLevel(DistributorLevel $previousLevel): self
    {
        $this->previousLevel = $previousLevel;

        return $this;
    }

    public function getNewLevel(): DistributorLevel
    {
        return $this->newLevel;
    }

    public function setNewLevel(DistributorLevel $newLevel): self
    {
        $this->newLevel = $newLevel;

        return $this;
    }

    public function getSatisfiedExpression(): string
    {
        return $this->satisfiedExpression;
    }

    public function setSatisfiedExpression(string $satisfiedExpression): self
    {
        $this->satisfiedExpression = $satisfiedExpression;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContextSnapshot(): array
    {
        return $this->contextSnapshot;
    }

    /**
     * @param array<string, mixed> $contextSnapshot
     */
    public function setContextSnapshot(array $contextSnapshot): self
    {
        $this->contextSnapshot = $contextSnapshot;

        return $this;
    }

    public function getTriggeringWithdrawLedger(): ?WithdrawLedger
    {
        return $this->triggeringWithdrawLedger;
    }

    public function setTriggeringWithdrawLedger(?WithdrawLedger $triggeringWithdrawLedger): self
    {
        $this->triggeringWithdrawLedger = $triggeringWithdrawLedger;

        return $this;
    }

    public function getUpgradeTime(): \DateTimeImmutable
    {
        return $this->upgradeTime;
    }

    public function setUpgradeTime(\DateTimeImmutable $upgradeTime): self
    {
        $this->upgradeTime = $upgradeTime;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf(
            'DistributorLevelUpgradeHistory{id=%s, distributor=%s, %s→%s, time=%s}',
            $this->id ?? '0',
            $this->distributor->getId() ?? 'null',
            $this->previousLevel->getName(),
            $this->newLevel->getName(),
            $this->upgradeTime->format('Y-m-d H:i:s')
        );
    }
}
