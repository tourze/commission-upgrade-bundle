# 数据模型：后台手动升级检测与执行

**Feature**: `manual-upgrade-check`
**日期**: 2025-11-19
**关联文档**: [spec.md](./spec.md) | [plan.md](./plan.md)

## 概述

本文档定义手动升级功能涉及的数据实体、DTO（数据传输对象）、以及 Session 数据结构。核心原则：
- **最小化数据库变更**：仅扩展现有 `DistributorLevelUpgradeHistory` 实体
- **复用现有实体**：`Distributor`、`DistributorLevel`、`DistributorLevelUpgradeRule` 保持不变
- **临时数据用 Session**：检测结果不持久化，使用 Session 在两步操作间传递

## 实体模型（Entity）

### 1. DistributorLevelUpgradeHistory（扩展）

**用途**：记录分销员等级升级历史，支持审计、统计和问题排查

**变更说明**：扩展现有实体，新增两个字段以区分手动/自动升级并记录操作人

**PHP 实体定义**（新增字段）：

```php
namespace Tourze\CommissionUpgradeBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\OrderCommissionBundle\Entity\BizUser; // 假设运营人员也是BizUser

#[ORM\Entity]
#[ORM\Table(name: 'commission_upgrade_distributor_level_upgrade_history')]
class DistributorLevelUpgradeHistory
{
    // ... 现有字段（id, distributor, previousLevel, newLevel, satisfiedExpression, contextSnapshot, triggeringWithdrawLedger, upgradeTime, createTime, updateTime）...

    /**
     * 触发类型：'auto'（自动升级）或 'manual'（手动升级）
     */
    #[ORM\Column(
        name: 'trigger_type',
        type: Types::STRING,
        length: 20,
        nullable: false,
        options: ['default' => 'auto', 'comment' => '触发类型：auto=自动升级，manual=手动升级']
    )]
    private string $triggerType = 'auto';

    /**
     * 操作人（手动升级时记录，自动升级时为 null）
     */
    #[ORM\ManyToOne(targetEntity: BizUser::class)]
    #[ORM\JoinColumn(name: 'operator_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?BizUser $operator = null;

    public function getTriggerType(): string
    {
        return $this->triggerType;
    }

    public function setTriggerType(string $triggerType): self
    {
        if (!\in_array($triggerType, ['auto', 'manual'], true)) {
            throw new \InvalidArgumentException('Invalid trigger type. Must be "auto" or "manual".');
        }
        $this->triggerType = $triggerType;

        return $this;
    }

    public function getOperator(): ?BizUser
    {
        return $this->operator;
    }

    public function setOperator(?BizUser $operator): self
    {
        $this->operator = $operator;

        return $this;
    }

    public function isManualUpgrade(): bool
    {
        return 'manual' === $this->triggerType;
    }
}
```

**字段说明**：

| 字段名 | 类型 | 约束 | 说明 |
|--------|------|------|------|
| `trigger_type` | string(20) | NOT NULL, DEFAULT 'auto' | 触发类型，枚举值：'auto', 'manual' |
| `operator_id` | bigint | NULL, FK → biz_user.id, ON DELETE SET NULL | 操作人ID，手动升级时必填 |

**索引建议**（用于查询优化）：

```sql
-- 查询某个运营人员的操作历史
CREATE INDEX idx_operator_time ON commission_upgrade_distributor_level_upgrade_history(operator_id, upgrade_time DESC);

-- 查询所有手动升级记录
CREATE INDEX idx_trigger_type_time ON commission_upgrade_distributor_level_upgrade_history(trigger_type, upgrade_time DESC);
```

**业务规则**：
- `trigger_type = 'manual'` 时，`operator` 必须非空（应用层验证）
- `trigger_type = 'auto'` 时，`operator` 应为 null
- 不允许修改已创建的历史记录（只读，通过 EasyAdmin 配置禁止编辑）

---

## DTO（数据传输对象）

### 2. ManualUpgradeCheckRequest

**用途**：接收前端输入的用户ID，用于检测升级条件

**PHP DTO 定义**：

```php
namespace Tourze\CommissionUpgradeBundle\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ManualUpgradeCheckRequest
{
    #[Assert\NotBlank(message: '用户ID不能为空')]
    #[Assert\Type(type: 'int', message: '用户ID必须是整数')]
    #[Assert\Positive(message: '用户ID必须大于0')]
    private int $distributorId;

    public function __construct(int $distributorId)
    {
        $this->distributorId = $distributorId;
    }

    public function getDistributorId(): int
    {
        return $this->distributorId;
    }

    public function setDistributorId(int $distributorId): self
    {
        $this->distributorId = $distributorId;

        return $this;
    }
}
```

**验证规则**：
- `distributorId` 必填
- 必须为正整数
- 后端需验证用户是否存在（在 Service 层）

---

### 3. ManualUpgradeCheckResult

**用途**：检测结果数据，用于在检测和升级之间传递，也用于前端展示

**PHP DTO 定义**：

```php
namespace Tourze\CommissionUpgradeBundle\DTO;

use Tourze\CommissionDistributorBundle\Entity\Distributor;use Tourze\CommissionLevelBundle\Entity\DistributorLevel;use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeRule;

class ManualUpgradeCheckResult
{
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

    public static function fromArray(array $data): self
    {
        // 用于从 Session 反序列化（仅存储基础数据）
        return new self(
            distributor: null, // 需要从数据库重新加载
            currentLevel: null,
            canUpgrade: $data['can_upgrade'],
            targetLevel: null,
            upgradeRule: null,
            context: $data['context'] ?? [],
            checkTime: new \DateTimeImmutable($data['check_time']),
            failureReason: $data['failure_reason'] ?? null,
        );
    }
}
```

**字段说明**：

| 字段 | 类型 | 说明 |
|------|------|------|
| `distributor` | Distributor | 分销员实体 |
| `currentLevel` | DistributorLevel | 当前等级 |
| `canUpgrade` | bool | 是否满足升级条件 |
| `targetLevel` | ?DistributorLevel | 目标等级（满足条件时） |
| `upgradeRule` | ?DistributorLevelUpgradeRule | 升级规则（满足条件时） |
| `context` | array | 上下文变量快照（如消费金额、订单数等） |
| `checkTime` | \DateTimeImmutable | 检测时间戳 |
| `failureReason` | ?string | 不满足条件的原因（如"当前已是最高等级"） |

---

## Session 数据结构

### 4. 检测结果 Session 数据

**用途**：在"检测"和"升级"两步操作之间临时存储检测结果

**Session Key**: `manual_upgrade_check_result_{distributorId}`

**数据结构**（JSON 序列化）：

```json
{
  "distributor_id": 12345,
  "current_level_id": 1,
  "current_level_name": "普通会员",
  "can_upgrade": true,
  "target_level_id": 2,
  "target_level_name": "银牌会员",
  "upgrade_expression": "total_amount >= 1000 and order_count >= 10",
  "context": {
    "total_amount": 1200.50,
    "order_count": 15,
    "active_days": 30
  },
  "check_time": "2025-11-19 14:30:00",
  "failure_reason": null
}
```

**字段说明**：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `distributor_id` | int | 是 | 分销员ID |
| `current_level_id` | int | 是 | 当前等级ID |
| `current_level_name` | string | 是 | 当前等级名称（用于展示） |
| `can_upgrade` | bool | 是 | 是否满足升级条件 |
| `target_level_id` | int | 否 | 目标等级ID（可升级时必填） |
| `target_level_name` | string | 否 | 目标等级名称 |
| `upgrade_expression` | string | 否 | 升级条件表达式 |
| `context` | object | 是 | 上下文变量快照 |
| `check_time` | string (ISO 8601) | 是 | 检测时间戳 |
| `failure_reason` | string | 否 | 失败原因（不可升级时） |

**过期策略**：
- Session 超时时间：30分钟（通过 Symfony session 配置）
- 升级成功后立即清除对应的 Session 数据
- 用户再次检测同一分销员时覆盖旧的 Session 数据

---

## 数据流图

### 检测升级条件流程

```
┌─────────────┐          ┌──────────────────┐          ┌─────────────────┐
│ 用户输入ID   │  ──────> │ ManualUpgrade    │  ──────> │ Distributor     │
│ (Form)      │          │ Controller       │          │ UpgradeService  │
└─────────────┘          └──────────────────┘          └─────────────────┘
                                 │                               │
                                 │                               ↓
                                 │                       ┌──────────────────┐
                                 │                       │ 查询 Distributor │
                                 │                       │ 查询 UpgradeRule │
                                 │                       │ 评估表达式       │
                                 │                       └──────────────────┘
                                 │                               │
                                 ↓                               ↓
                         ┌──────────────────┐          ┌─────────────────┐
                         │ Session Storage  │  <────── │ Check Result    │
                         └──────────────────┘          │ (DTO)           │
                                 │                     └─────────────────┘
                                 ↓
                         ┌──────────────────┐
                         │ 展示检测结果页面  │
                         └──────────────────┘
```

### 执行升级流程

```
┌─────────────┐          ┌──────────────────┐          ┌─────────────────┐
│ 用户点击升级 │  ──────> │ ManualUpgrade    │  ──────> │ 验证 Session    │
│ (Button)    │          │ Controller       │          │ 数据有效性       │
└─────────────┘          └──────────────────┘          └─────────────────┘
                                 │                               │ OK
                                 │                               ↓
                                 │                       ┌─────────────────┐
                                 │                       │ Distributor     │
                                 │                       │ UpgradeService  │
                                 │                       │ checkAndUpgrade │
                                 │                       └─────────────────┘
                                 │                               │
                                 │                               ↓
                                 │                       ┌─────────────────┐
                                 │                       │ 乐观锁验证       │
                                 │                       │ 条件重新评估     │
                                 │                       │ 更新 Distributor│
                                 │                       └─────────────────┘
                                 │                               │
                                 ↓                               ↓
                         ┌──────────────────┐          ┌─────────────────────┐
                         │ 清除 Session     │          │ DistributorLevel    │
                         └──────────────────┘          │ UpgradeHistory      │
                                 │                     │ (新增记录)          │
                                 │                     │ - trigger_type:     │
                                 │                     │   'manual'          │
                                 │                     │ - operator: current │
                                 │                     │   user              │
                                 │                     └─────────────────────┘
                                 ↓
                         ┌──────────────────┐
                         │ 展示升级成功页面  │
                         └──────────────────┘
```

---

## 数据一致性约束

| 约束 | 说明 | 实现方式 |
|------|------|---------|
| 手动升级必须记录操作人 | `trigger_type = 'manual'` 时 `operator` 不能为 null | 应用层验证 + 数据库约束 |
| Session 数据有效期 | 检测结果仅保留30分钟 | Symfony Session 配置 |
| 升级幂等性 | 同一用户不能重复升级到相同等级 | Doctrine 乐观锁 + 业务逻辑检查 |
| 历史记录不可修改 | 已创建的升级历史记录只读 | EasyAdmin 禁用编辑/删除操作 |
| 用户ID必须存在 | 输入的用户ID必须在 Distributor 表中存在 | Service 层查询验证 |

---

## 数据迁移（Migration）

根据宪章规定，本项目不维护 Migration 文件。数据库表结构变更通过以下方式处理：

**开发环境**：
```bash
# 验证实体定义与数据库一致性
bin/console doctrine:schema:validate

# 生成 DDL 语句（仅查看，不执行）
bin/console doctrine:schema:update --dump-sql

# 同步表结构（开发环境可用）
bin/console doctrine:schema:update --force
```

**生产环境**：
由运维团队基于实体定义手动执行以下 SQL（或通过自动化工具）：

```sql
-- 新增字段到现有表
ALTER TABLE commission_upgrade_distributor_level_upgrade_history
ADD COLUMN trigger_type VARCHAR(20) NOT NULL DEFAULT 'auto' COMMENT '触发类型：auto=自动升级，manual=手动升级',
ADD COLUMN operator_id BIGINT NULL COMMENT '操作人ID（手动升级时记录）',
ADD CONSTRAINT fk_upgrade_history_operator FOREIGN KEY (operator_id) REFERENCES biz_user(id) ON DELETE SET NULL;

-- 新增索引
CREATE INDEX idx_operator_time
ON commission_upgrade_distributor_level_upgrade_history(operator_id, upgrade_time DESC);

CREATE INDEX idx_trigger_type_time
ON commission_upgrade_distributor_level_upgrade_history(trigger_type, upgrade_time DESC);
```

---

## 附录

### 相关实体定义（现有，无变更）

- `Distributor`：分销员实体（位于 `order-commission-bundle`）
- `DistributorLevel`：分销员等级（位于 `order-commission-bundle`）
- `DistributorLevelUpgradeRule`：升级规则（位于本Bundle）
- `BizUser`：业务用户（运营人员）（位于 `user-service-contracts`）

### 测试数据示例

```php
// Fixture 示例（用于测试）
$operator = ...; // 从 UserManagerInterface 获取测试用户
$distributor = ...; // 创建测试分销员
$previousLevel = ...; // 等级1
$newLevel = ...; // 等级2

$history = new DistributorLevelUpgradeHistory();
$history->setDistributor($distributor);
$history->setPreviousLevel($previousLevel);
$history->setNewLevel($newLevel);
$history->setTriggerType('manual');
$history->setOperator($operator);
$history->setSatisfiedExpression('total_amount >= 1000');
$history->setContextSnapshot(['total_amount' => 1200]);
$history->setUpgradeTime(new \DateTimeImmutable());

$entityManager->persist($history);
$entityManager->flush();
```

---

**下一步**：
1. ✅ 数据模型定义完成（本文档）
2. → 进入 contracts/ 生成服务契约文档
3. → 开发实体扩展（新增字段）并编写单元测试
