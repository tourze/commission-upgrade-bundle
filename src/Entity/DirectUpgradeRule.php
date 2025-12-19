<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\Attribute\ValidUpgradeExpression;
use Tourze\CommissionUpgradeBundle\Repository\DirectUpgradeRuleRepository;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

/**
 * 分销员直升规则.
 * 
 * 不依赖源等级，任何低于目标等级的分销员都可以通过此规则直接升级
 */
#[ORM\Entity(repositoryClass: DirectUpgradeRuleRepository::class)]
#[ORM\Table(
    name: 'commission_upgrade_direct_rule',
    options: ['comment' => '分销员直升规则']
)]
#[ORM\UniqueConstraint(
    name: 'uniq_target_level',
    columns: ['target_level_id']
)]
class DirectUpgradeRule implements \Stringable
{
    use SnowflakeKeyAware;
    use TimestampableAware;

    /**
     * 目标等级（直升目标）.
     * 
     * 任何当前等级低于此等级且满足条件的分销员都可以直升到此等级
     */
    #[ORM\ManyToOne(targetEntity: DistributorLevel::class)]
    #[ORM\JoinColumn(name: 'target_level_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: '目标等级不能为空')]
    private DistributorLevel $targetLevel;

    #[ORM\Column(
        name: 'upgrade_expression',
        type: Types::TEXT,
        nullable: false,
        options: ['comment' => '直升条件表达式（Symfony Expression Language语法）']
    )]
    #[Assert\NotBlank(message: '直升条件表达式不能为空')]
    #[Assert\Length(max: 5000, maxMessage: '表达式长度不能超过5000字符')]
    #[ValidUpgradeExpression]
    private string $upgradeExpression;

    #[ORM\Column(
        name: 'priority',
        type: Types::INTEGER,
        nullable: false,
        options: ['comment' => '优先级（数值越大优先级越高）', 'default' => 0]
    )]
    #[Assert\Type(type: 'int', message: '优先级必须为整数')]
    #[Assert\Range(min: 0, max: 999, notInRangeMessage: '优先级必须在0-999之间')]
    private int $priority = 0;

    #[ORM\Column(
        name: 'is_enabled',
        type: Types::BOOLEAN,
        nullable: false,
        options: ['comment' => '是否启用（禁用后该直升规则不生效）', 'default' => true]
    )]
    #[Assert\Type(type: 'bool', message: '是否启用必须为布尔值')]
    private bool $isEnabled = true;

    #[ORM\Column(
        name: 'description',
        type: Types::TEXT,
        nullable: true,
        options: ['comment' => '备注说明（用于记录规则用途、变更原因等）']
    )]
    #[Assert\Length(max: 1000, maxMessage: '备注说明不能超过1000字符')]
    private ?string $description = null;

    #[ORM\Column(
        name: 'min_level_requirement',
        type: Types::INTEGER,
        nullable: true,
        options: ['comment' => '最低等级要求（可选，限制只有特定等级以上才能使用此直升规则）']
    )]
    #[Assert\PositiveOrZero(message: '最低等级要求必须大于等于0')]
    private ?int $minLevelRequirement = null;

    public function getTargetLevel(): DistributorLevel
    {
        return $this->targetLevel;
    }

    public function setTargetLevel(DistributorLevel $targetLevel): void
    {
        $this->targetLevel = $targetLevel;
    }

    public function getUpgradeExpression(): string
    {
        return $this->upgradeExpression;
    }

    public function setUpgradeExpression(string $upgradeExpression): void
    {
        $this->upgradeExpression = $upgradeExpression;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): void
    {
        $this->isEnabled = $isEnabled;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getMinLevelRequirement(): ?int
    {
        return $this->minLevelRequirement;
    }

    public function setMinLevelRequirement(?int $minLevelRequirement): void
    {
        $this->minLevelRequirement = $minLevelRequirement;
    }

    /**
     * 检查指定分销员是否符合最低等级要求
     */
    public function isEligibleForLevel(DistributorLevel $currentLevel): bool
    {
        // 检查当前等级必须低于目标等级
        if ($currentLevel->getLevel() >= $this->targetLevel->getLevel()) {
            return false;
        }

        // 检查最低等级要求
        if (null !== $this->minLevelRequirement && $currentLevel->getLevel() < $this->minLevelRequirement) {
            return false;
        }

        return true;
    }

    public function __toString(): string
    {
        return sprintf(
            '直升至 %s (优先级:%d)',
            $this->targetLevel->getName(),
            $this->priority
        );
    }
}