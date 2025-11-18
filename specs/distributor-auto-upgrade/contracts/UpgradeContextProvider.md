# 服务契约：UpgradeContextProvider

**服务名称**：`UpgradeContextProvider`
**命名空间**：`Tourze\CommissionUpgradeBundle\Service`
**职责**：构建升级判断所需的上下文变量

---

## 1. 职责描述

UpgradeContextProvider 负责：

1. **计算上下文变量**：基于分销员数据计算 `withdrawnAmount`、`inviteeCount` 等变量
2. **数据聚合**：从多个数据源（WithdrawLedger、Distributor 等）聚合统计指标
3. **性能优化**：避免重复查询，复用已加载的关联数据

---

## 2. 公开接口

### 2.1 buildContext(Distributor $distributor): array<string, mixed>

**用途**：构建升级判断所需的完整上下文变量

**参数**：
- `$distributor` (Distributor): 待升级的分销员实体

**返回值**：array<string, mixed>（包含所有可用变量的关联数组）

**前置条件**：
- `$distributor` 已持久化到数据库（有有效的 ID）

**后置条件**：
- 返回包含所有 `ALLOWED_VARIABLES` 的数组
- 所有变量值为实时计算结果

**示例**：

```php
$provider = new UpgradeContextProvider($withdrawLedgerRepository, $distributorRepository);
$distributor = $distributorRepository->find(123);

$context = $provider->buildContext($distributor);
// 返回:
// [
//     'withdrawnAmount' => 5100.50,
//     'inviteeCount' => 12,
//     'orderCount' => 58,
//     'activeInviteeCount' => 8,
// ]
```

---

### 2.2 calculateWithdrawnAmount(Distributor $distributor): float

**用途**：计算已提现佣金总额（仅统计 WithdrawLedger.Completed 状态）

**参数**：
- `$distributor` (Distributor): 分销员实体

**返回值**：float（已提现佣金总额，单位：元）

**业务规则**：
- 仅统计 `WithdrawLedger.status = Completed` 的记录
- 排除 `Pending`、`Failed`、`Refunded` 等状态

**示例**：

```php
$amount = $provider->calculateWithdrawnAmount($distributor);
// 返回: 5100.50
```

**SQL 等价**：

```sql
SELECT SUM(amount) FROM order_commission_withdraw_ledger
WHERE distributor_id = ? AND status = 'Completed';
```

---

### 2.3 calculateInviteeCount(Distributor $distributor): int

**用途**：计算邀请人数（一级下线数量）

**参数**：
- `$distributor` (Distributor): 分销员实体

**返回值**：int（邀请人数）

**业务规则**：
- 统计 `Distributor.parent_id = $distributor->getId()` 的记录数
- 仅统计状态为 `Approved` 的分销员

**示例**：

```php
$count = $provider->calculateInviteeCount($distributor);
// 返回: 12
```

**SQL 等价**：

```sql
SELECT COUNT(*) FROM order_commission_distributor
WHERE parent_id = ? AND status = 'Approved';
```

---

### 2.4 calculateOrderCount(Distributor $distributor): int

**用途**：计算订单数（关联该分销员的订单总数）

**参数**：
- `$distributor` (Distributor): 分销员实体

**返回值**：int（订单数）

**业务规则**：
- 统计 `CommissionLedger.distributor_id = $distributor->getId()` 关联的订单数
- 使用 `DISTINCT order_id` 去重（避免多笔佣金记录重复计数）

**示例**：

```php
$count = $provider->calculateOrderCount($distributor);
// 返回: 58
```

**SQL 等价**：

```sql
SELECT COUNT(DISTINCT order_id) FROM order_commission_commission_ledger
WHERE distributor_id = ?;
```

---

### 2.5 calculateActiveInviteeCount(Distributor $distributor, int $days = 30): int

**用途**：计算活跃邀请人数（最近N天内有订单的下线）

**参数**：
- `$distributor` (Distributor): 分销员实体
- `$days` (int): 活跃天数阈值（默认30天）

**返回值**：int（活跃邀请人数）

**业务规则**：
- 统计 `Distributor.parent_id = $distributor->getId()` 且最近N天有订单的分销员数量
- 订单时间基于 `CommissionLedger.create_time`

**示例**：

```php
$count = $provider->calculateActiveInviteeCount($distributor, 30);
// 返回: 8
```

**SQL 等价**：

```sql
SELECT COUNT(DISTINCT d.id)
FROM order_commission_distributor d
INNER JOIN order_commission_commission_ledger cl ON cl.distributor_id = d.id
WHERE d.parent_id = ?
  AND d.status = 'Approved'
  AND cl.create_time >= DATE_SUB(NOW(), INTERVAL 30 DAY);
```

---

## 3. 依赖关系

### 3.1 外部依赖

- `Tourze\OrderCommissionBundle\Repository\WithdrawLedgerRepository`（提现流水查询）
- `Tourze\OrderCommissionBundle\Repository\DistributorRepository`（分销员查询）
- `Tourze\OrderCommissionBundle\Repository\CommissionLedgerRepository`（佣金流水查询）

### 3.2 服务配置

```yaml
# config/services.yaml
services:
    Tourze\CommissionUpgradeBundle\Service\UpgradeContextProvider:
        arguments:
            - '@Tourze\OrderCommissionBundle\Repository\WithdrawLedgerRepository'
            - '@Tourze\OrderCommissionBundle\Repository\DistributorRepository'
            - '@Tourze\OrderCommissionBundle\Repository\CommissionLedgerRepository'
```

---

## 4. 性能优化

### 4.1 缓存策略

**问题**：高频查询分销员统计指标，每次重新计算浪费性能。

**优化方案**：

#### 选项A：应用层缓存（推荐初期方案）

```php
private array $contextCache = [];

public function buildContext(Distributor $distributor): array
{
    $cacheKey = 'distributor_context_' . $distributor->getId();

    if (isset($this->contextCache[$cacheKey])) {
        return $this->contextCache[$cacheKey];
    }

    $context = [
        'withdrawnAmount' => $this->calculateWithdrawnAmount($distributor),
        'inviteeCount' => $this->calculateInviteeCount($distributor),
        'orderCount' => $this->calculateOrderCount($distributor),
        'activeInviteeCount' => $this->calculateActiveInviteeCount($distributor),
    ];

    $this->contextCache[$cacheKey] = $context;
    return $context;
}
```

**限制**：缓存仅在单次请求内有效，适用于同一分销员多次升级检查场景。

#### 选项B：Redis 缓存（生产环境推荐）

```php
public function buildContext(Distributor $distributor): array
{
    $cacheKey = 'distributor_context:' . $distributor->getId();
    $cached = $this->redis->get($cacheKey);

    if ($cached !== null) {
        return json_decode($cached, true);
    }

    $context = [ /* 实时计算 */ ];

    // 缓存5分钟（提现成功后失效）
    $this->redis->setex($cacheKey, 300, json_encode($context));

    return $context;
}
```

**缓存失效时机**：
- 提现成功（WithdrawLedger.Completed）时清除缓存
- 分销员邀请新下线时清除缓存

**决策**：初期使用选项A（应用层缓存），待性能测试后决定是否引入 Redis。

---

### 4.2 批量查询优化

**问题**：多个分销员同时升级检查时，产生 N+1 查询。

**优化方案**：提供批量查询方法

```php
/**
 * 批量构建上下文（用于批量升级检查）
 *
 * @param Distributor[] $distributors
 * @return array<int, array<string, mixed>> 键为分销员ID，值为上下文数组
 */
public function buildContextBatch(array $distributors): array
{
    $distributorIds = array_map(fn($d) => $d->getId(), $distributors);

    // 批量查询已提现金额
    $withdrawnAmounts = $this->withdrawLedgerRepository->sumCompletedAmountBatch($distributorIds);

    // 批量查询邀请人数
    $inviteeCounts = $this->distributorRepository->countInviteesBatch($distributorIds);

    // 组装上下文
    $contexts = [];
    foreach ($distributors as $distributor) {
        $id = $distributor->getId();
        $contexts[$id] = [
            'withdrawnAmount' => $withdrawnAmounts[$id] ?? 0.0,
            'inviteeCount' => $inviteeCounts[$id] ?? 0,
            // ...
        ];
    }

    return $contexts;
}
```

---

## 5. 错误处理

### 5.1 数据缺失

**场景**：分销员未持久化或关联数据缺失

**处理**：
- 对于未持久化的分销员，抛出 `\LogicException`
- 对于统计指标缺失（如无提现记录），返回默认值（0 或 0.0）

**示例**：

```php
public function buildContext(Distributor $distributor): array
{
    if ($distributor->getId() === null) {
        throw new \LogicException('Distributor must be persisted before building context');
    }

    return [
        'withdrawnAmount' => $this->calculateWithdrawnAmount($distributor) ?? 0.0,
        // ...
    ];
}
```

---

## 6. 测试要求

### 6.1 单元测试覆盖

**测试场景**：

1. **buildContext()**：
   - 正常分销员：返回包含4个变量的数组
   - 无提现记录的分销员：`withdrawnAmount = 0.0`
   - 无下线的分销员：`inviteeCount = 0`

2. **calculateWithdrawnAmount()**：
   - 有多笔 Completed 提现：返回总额
   - 有 Failed/Pending 提现：不计入总额
   - 无提现记录：返回 0.0

3. **calculateInviteeCount()**：
   - 有多个 Approved 下线：返回正确数量
   - 包含 Pending/Rejected 下线：不计入数量

4. **calculateOrderCount()**：
   - 有多笔佣金记录（同一订单多次分佣）：去重计数
   - 无佣金记录：返回 0

5. **calculateActiveInviteeCount()**：
   - 30天内有订单的下线：计入数量
   - 30天前有订单的下线：不计入

### 6.2 集成测试

**场景**：使用 Fixture 数据验证完整流程

**Fixture 示例**：

```yaml
Tourze\OrderCommissionBundle\Entity\Distributor:
  distributor_test:
    level: '@level_1'
    status: 'Approved'

Tourze\OrderCommissionBundle\Entity\WithdrawLedger:
  withdraw_1:
    distributor: '@distributor_test'
    amount: 3000.00
    status: 'Completed'
  withdraw_2:
    distributor: '@distributor_test'
    amount: 2100.50
    status: 'Completed'
  withdraw_3:
    distributor: '@distributor_test'
    amount: 500.00
    status: 'Failed' # 不计入
```

**验证**：

```php
$context = $provider->buildContext($distributorTest);
$this->assertEquals(5100.50, $context['withdrawnAmount']); // 3000 + 2100.50
```

---

## 7. 变更历史

| 版本 | 日期 | 变更内容 |
|------|------|---------|
| 1.0.0 | 2025-11-17 | 初始版本：支持4个基础变量 |

---

## 8. 未来扩展

### 8.1 新增变量

**需求**：支持 `teamPerformance`（团队业绩）变量

**实现步骤**：
1. 在 `UpgradeExpressionEvaluator::ALLOWED_VARIABLES` 添加 `teamPerformance`
2. 在 `UpgradeContextProvider` 添加 `calculateTeamPerformance()` 方法
3. 在 `buildContext()` 中调用新方法
4. 更新文档和测试用例

### 8.2 实时数据 vs 快照数据

**需求**：支持基于快照数据计算（如每日更新的统计表）

**实现方式**：
- 创建 `DistributorStatistics` 实体，通过定时任务更新
- 修改 `UpgradeContextProvider` 从快照表读取数据

---

**文档完成日期**：2025-11-17
**审核状态**：待审核
