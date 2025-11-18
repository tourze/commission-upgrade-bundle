# 实体设计：DistributorLevelUpgradeRule

**实体名称**：`DistributorLevelUpgradeRule`
**命名空间**：`Tourze\CommissionUpgradeBundle\Entity`
**职责**：存储分销员等级升级规则配置

---

## 1. 用途说明

DistributorLevelUpgradeRule 实体负责：

1. **存储升级条件**：管理从源等级到目标等级的升级表达式
2. **解耦配置管理**：在 CommissionUpgradeBundle 独立维护升级逻辑，不修改 OrderCommissionBundle
3. **支持灵活配置**：后台管理员可通过 EasyAdmin 编辑升级规则

---

## 2. 实体定义

```php
<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\OrderCommissionBundle\Entity\DistributorLevel;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \Tourze\CommissionUpgradeBundle\Repository\DistributorLevelUpgradeRuleRepository::class)]
#[ORM\Table(
    name: 'commission_upgrade_distributor_level_upgrade_rule',
    options: ['comment' => '分销员等级升级规则']
)]
#[ORM\UniqueConstraint(
    name: 'uniq_source_level',
    columns: ['source_level_id']
)]
#[ORM\Index(name: 'idx_target_level', columns: ['target_level_id'])]
class DistributorLevelUpgradeRule implements \Stringable
{
    use SnowflakeKeyAware;
    use TimestampableAware;

    /**
     * 源等级（升级前等级）
     *
     * 例如：1级分销员
     */
    #[ORM\ManyToOne(targetEntity: DistributorLevel::class)]
    #[ORM\JoinColumn(name: 'source_level_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: '源等级不能为空')]
    private DistributorLevel $sourceLevel;

    /**
     * 目标等级（升级后等级）
     *
     * 例如：2级分销员
     */
    #[ORM\ManyToOne(targetEntity: DistributorLevel::class)]
    #[ORM\JoinColumn(name: 'target_level_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: '目标等级不能为空')]
    private DistributorLevel $targetLevel;

    /**
     * 升级条件表达式（Symfony Expression Language 语法）
     *
     * 示例：
     * - 简单条件："withdrawnAmount >= 5000"
     * - 复杂条件："withdrawnAmount >= 5000 and inviteeCount >= 10"
     * - OR 条件："withdrawnAmount >= 10000 or (inviteeCount >= 20 and orderCount >= 100)"
     */
    #[ORM\Column(
        name: 'upgrade_expression',
        type: Types::TEXT,
        nullable: false,
        options: ['comment' => '升级条件表达式']
    )]
    #[Assert\NotBlank(message: '升级条件表达式不能为空')]
    #[Assert\Length(max: 5000, maxMessage: '表达式长度不能超过5000字符')]
    private string $upgradeExpression;

    /**
     * 是否启用
     *
     * 禁用后该升级规则不生效（用于临时关闭某个升级路径）
     */
    #[ORM\Column(
        name: 'is_enabled',
        type: Types::BOOLEAN,
        nullable: false,
        options: ['comment' => '是否启用', 'default' => true]
    )]
    private bool $isEnabled = true;

    /**
     * 备注说明
     *
     * 用于记录规则用途、变更原因等
     */
    #[ORM\Column(
        name: 'description',
        type: Types::TEXT,
        nullable: true,
        options: ['comment' => '备注说明']
    )]
    private ?string $description = null;

    // ==================== Getters and Setters ====================

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
```

---

## 3. 字段说明

| 字段 | 类型 | 必填 | 说明 | 约束 |
|------|------|------|------|------|
| `id` | snowflake | 是 | 主键（雪花ID） | PRIMARY KEY |
| `source_level_id` | snowflake | 是 | 源等级ID | UNIQUE, FK to DistributorLevel |
| `target_level_id` | snowflake | 是 | 目标等级ID | FK to DistributorLevel |
| `upgrade_expression` | TEXT | 是 | 升级条件表达式 | 最大5000字符 |
| `is_enabled` | BOOLEAN | 是 | 是否启用 | 默认 true |
| `description` | TEXT | 否 | 备注说明 | - |
| `create_time` | timestamp | 是 | 创建时间 | 自动填充 |
| `update_time` | timestamp | 是 | 更新时间 | 自动更新 |

---

## 4. 索引与约束

### 4.1 唯一约束

**`uniq_source_level` (source_level_id)**：
- **用途**：确保每个源等级只有一条升级规则
- **业务语义**：1级分销员只能升级到2级（不能同时配置1→2和1→3）

### 4.2 外键约束

| 外键 | 约束行为 | 理由 |
|------|---------|------|
| `source_level_id` | CASCADE | 源等级删除时同步删除升级规则 |
| `target_level_id` | CASCADE | 目标等级删除时同步删除升级规则 |

### 4.3 普通索引

**`idx_target_level` (target_level_id)**：
- **用途**：支持查询哪些规则升级到某个目标等级（运营报表）

---

## 5. 业务规则

### 5.1 等级顺序性

**规则**：升级必须逐级进行，禁止跳级。

**验证**：
```php
public function validate(): void
{
    $sourceSort = $this->sourceLevel->getSort();
    $targetSort = $this->targetLevel->getSort();

    if ($targetSort <= $sourceSort) {
        throw new \LogicException('目标等级必须高于源等级');
    }

    // 检查是否跳级（可选，严格模式）
    $levelRepository = /* 注入 */;
    $intermediateLevels = $levelRepository->findBetweenSorts($sourceSort, $targetSort);
    if (count($intermediateLevels) > 0) {
        throw new \LogicException(sprintf(
            '升级规则不允许跳级：%s (sort=%d) 到 %s (sort=%d) 之间存在中间等级',
            $this->sourceLevel->getName(),
            $sourceSort,
            $this->targetLevel->getName(),
            $targetSort
        ));
    }
}
```

### 5.2 表达式验证

**规则**：保存前必须验证表达式语法和变量合法性。

**实现**：通过 Symfony Validator 自定义约束

```php
use Tourze\CommissionUpgradeBundle\Validator\Constraints as CustomAssert;

#[CustomAssert\ValidUpgradeExpression]
#[ORM\Column(...)]
private string $upgradeExpression;
```

**验证器实现**：

```php
namespace Tourze\CommissionUpgradeBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Tourze\CommissionUpgradeBundle\Service\UpgradeExpressionEvaluator;

class ValidUpgradeExpressionValidator extends ConstraintValidator
{
    public function __construct(
        private UpgradeExpressionEvaluator $expressionEvaluator
    ) {}

    public function validate($value, Constraint $constraint): void
    {
        if (empty($value)) {
            return; // @NotBlank 已验证非空
        }

        try {
            $this->expressionEvaluator->validate($value);
        } catch (\InvalidArgumentException $e) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ error }}', $e->getMessage())
                ->addViolation();
        }
    }
}
```

---

## 6. 数据示例

### 6.1 简单条件

**场景**：1级→2级，仅依据已提现金额

```sql
INSERT INTO commission_upgrade_distributor_level_upgrade_rule
(id, source_level_id, target_level_id, upgrade_expression, is_enabled, description)
VALUES
(1, 1, 2, 'withdrawnAmount >= 5000', true, '一级升二级：已提现佣金达到5000元');
```

### 6.2 复杂条件

**场景**：2级→3级，需同时满足金额和邀请人数

```sql
INSERT INTO commission_upgrade_distributor_level_upgrade_rule
(id, source_level_id, target_level_id, upgrade_expression, is_enabled, description)
VALUES
(2, 2, 3, 'withdrawnAmount >= 10000 and inviteeCount >= 10', true, '二级升三级：已提现佣金达到10000元且邀请人数>=10');
```

### 6.3 OR 条件

**场景**：3级→4级，满足任一条件即可

```sql
INSERT INTO commission_upgrade_distributor_level_upgrade_rule
(id, source_level_id, target_level_id, upgrade_expression, is_enabled, description)
VALUES
(3, 3, 4, 'withdrawnAmount >= 50000 or (inviteeCount >= 50 and activeInviteeCount >= 30)', true, '三级升四级：已提现5万元或邀请50人且活跃30人');
```

---

## 7. 数据迁移

### 7.1 迁移文件

```php
<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251117_CreateUpgradeRule extends AbstractMigration
{
    public function getDescription(): string
    {
        return '创建分销员等级升级规则表';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE commission_upgrade_distributor_level_upgrade_rule (
                id BIGINT NOT NULL COMMENT '主键（雪花ID）',
                source_level_id BIGINT NOT NULL COMMENT '源等级ID',
                target_level_id BIGINT NOT NULL COMMENT '目标等级ID',
                upgrade_expression TEXT NOT NULL COMMENT '升级条件表达式',
                is_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
                description TEXT DEFAULT NULL COMMENT '备注说明',
                create_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                update_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                PRIMARY KEY (id),
                UNIQUE KEY uniq_source_level (source_level_id),
                INDEX idx_target_level (target_level_id),
                CONSTRAINT fk_upgrade_rule_source_level
                    FOREIGN KEY (source_level_id)
                    REFERENCES order_commission_distributor_level (id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_upgrade_rule_target_level
                    FOREIGN KEY (target_level_id)
                    REFERENCES order_commission_distributor_level (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分销员等级升级规则'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE commission_upgrade_distributor_level_upgrade_rule');
    }
}
```

---

## 8. EasyAdmin 配置

### 8.1 CRUD Controller

```php
<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeRule;
use Tourze\CommissionUpgradeBundle\Field\ExpressionEditorField;

class DistributorLevelUpgradeRuleCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DistributorLevelUpgradeRule::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('sourceLevel', '源等级');
        yield AssociationField::new('targetLevel', '目标等级');

        // 使用自定义表达式编辑器字段（支持简单模式和高级模式）
        yield ExpressionEditorField::new('upgradeExpression', '升级条件')
            ->hideOnIndex();

        // 或者使用简单的 TextareaField（仅高级模式）
        // yield TextareaField::new('upgradeExpression', '升级条件')
        //     ->setFormTypeOption('attr', ['rows' => 5])
        //     ->hideOnIndex();

        yield BooleanField::new('isEnabled', '启用');
        yield TextareaField::new('description', '备注说明')
            ->hideOnIndex();
    }
}
```

---

## 9. Repository 方法

```php
<?php

namespace Tourze\CommissionUpgradeBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeRule;
use Tourze\OrderCommissionBundle\Entity\DistributorLevel;

class DistributorLevelUpgradeRuleRepository extends ServiceEntityRepository
{
    /**
     * 查找源等级对应的升级规则
     */
    public function findBySourceLevel(DistributorLevel $sourceLevel): ?DistributorLevelUpgradeRule
    {
        return $this->createQueryBuilder('rule')
            ->where('rule.sourceLevel = :sourceLevel')
            ->andWhere('rule.isEnabled = true')
            ->setParameter('sourceLevel', $sourceLevel)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 查找所有已启用的升级规则
     *
     * @return DistributorLevelUpgradeRule[]
     */
    public function findAllEnabled(): array
    {
        return $this->createQueryBuilder('rule')
            ->where('rule.isEnabled = true')
            ->orderBy('rule.sourceLevel.sort', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
```

---

## 10. 测试要求

### 10.1 单元测试

**测试场景**：

1. **实体验证**：
   - 保存有效规则成功
   - 保存无效表达式时触发验证错误
   - 空表达式触发验证错误

2. **唯一约束**：
   - 同一源等级不能创建多条规则
   - 插入重复规则时抛出数据库异常

3. **外键约束**：
   - 删除源等级时同步删除规则
   - 删除目标等级时同步删除规则

### 10.2 集成测试

**场景**：通过 EasyAdmin 后台保存规则

**验证点**：
- 表达式验证在保存前触发
- 无效表达式显示错误提示
- 有效规则成功保存到数据库

---

## 11. 未来扩展

### 11.1 版本管理

**需求**：记录升级规则的历史版本

**实现方式**：
- 添加 `version` 字段
- 修改规则时创建新版本记录（软删除旧版本）
- 升级历史记录中关联规则版本

### 11.2 A/B 测试

**需求**：同一源等级配置多条规则，按比例分配分销员

**实现方式**：
- 移除 `uniq_source_level` 约束
- 添加 `weight` 字段（权重）
- 升级时基于权重随机选择规则

---

**文档完成日期**：2025-11-17
**审核状态**：待审核
