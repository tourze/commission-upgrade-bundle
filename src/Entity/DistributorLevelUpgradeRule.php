<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\CommissionUpgradeBundle\Repository\DistributorLevelUpgradeRuleRepository;
use Tourze\CommissionUpgradeBundle\Validator\Constraints as CustomAssert;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\OrderCommissionBundle\Entity\DistributorLevel;

/**
 * 分销员等级升级规则.
 *
 * 管理从源等级到目标等级的升级条件表达式
 */
#[ORM\Entity(repositoryClass: DistributorLevelUpgradeRuleRepository::class)]
#[ORM\Table(
    name: 'commission_upgrade_distributor_level_upgrade_rule',
    options: ['comment' => '分销员等级升级规则']
)]
#[ORM\UniqueConstraint(
    name: 'uniq_source_level',
    columns: ['source_level_id']
)]
#[ORM\Index(name: 'commission_upgrade_distributor_level_upgrade_rule_idx_target_level', columns: ['target_level_id'])]
class DistributorLevelUpgradeRule implements \Stringable
{
    use SnowflakeKeyAware;
    use TimestampableAware;

    /**
     * 源等级（升级前等级）.
     *
     * 例如：1级分销员
     */
    #[ORM\ManyToOne(targetEntity: DistributorLevel::class)]
    #[ORM\JoinColumn(name: 'source_level_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: '源等级不能为空')]
    private DistributorLevel $sourceLevel;

    /**
     * 目标等级（升级后等级）.
     *
     * 例如：2级分销员
     */
    #[ORM\ManyToOne(targetEntity: DistributorLevel::class)]
    #[ORM\JoinColumn(name: 'target_level_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: '目标等级不能为空')]
    private DistributorLevel $targetLevel;

    #[ORM\Column(
        name: 'upgrade_expression',
        type: Types::TEXT,
        nullable: false,
        options: ['comment' => '升级条件表达式（Symfony Expression Language语法）']
    )]
    #[Assert\NotBlank(message: '升级条件表达式不能为空')]
    #[Assert\Length(max: 5000, maxMessage: '表达式长度不能超过5000字符')]
    #[CustomAssert\ValidUpgradeExpression]
    private string $upgradeExpression;

    #[ORM\Column(
        name: 'is_enabled',
        type: Types::BOOLEAN,
        nullable: false,
        options: ['comment' => '是否启用（禁用后该升级规则不生效）', 'default' => true]
    )]
    #[Assert\Type(type: 'bool', message: '是否启用必须为布尔值')]
    private bool $isEnabled = true;

    #[ORM\Column(
        name: 'description',
        type: Types::TEXT,
        nullable: true,
        options: ['comment' => '备注说明（用于记录规则用途、变更原因等）']
    )]
    #[Assert\Length(max: 10000, maxMessage: '备注说明长度不能超过10000字符')]
    private ?string $description = null;

    public function getSourceLevel(): DistributorLevel
    {
        return $this->sourceLevel;
    }

    public function setSourceLevel(DistributorLevel $sourceLevel): self
    {
        $this->sourceLevel = $sourceLevel;

        return $this;
    }

    public function getTargetLevel(): DistributorLevel
    {
        return $this->targetLevel;
    }

    public function setTargetLevel(DistributorLevel $targetLevel): self
    {
        $this->targetLevel = $targetLevel;

        return $this;
    }

    public function getUpgradeExpression(): string
    {
        return $this->upgradeExpression;
    }

    public function setUpgradeExpression(string $upgradeExpression): self
    {
        $this->upgradeExpression = $upgradeExpression;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf(
            'UpgradeRule{%s→%s: %s}',
            $this->sourceLevel->getName(),
            $this->targetLevel->getName(),
            $this->upgradeExpression
        );
    }
}
