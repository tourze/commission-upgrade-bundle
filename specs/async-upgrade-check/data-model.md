# 数据模型：异步升级检查

**Feature**: async-upgrade-check | **日期**: 2025-11-19

## 概述

本功能主要引入**消息对象**和**消息处理器**，不涉及新的持久化实体（数据库表）。所有数据操作复用现有实体和服务。

---

## 核心消息对象

### DistributorUpgradeCheckMessage

**用途**：分销员升级检查的异步消息，投递到消息队列后由 Handler 消费。

**类型**：值对象（Value Object），不可变，仅用于数据传递。

**属性**：

| 属性名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| `distributorId` | `int` | 是 | 分销员唯一标识（主键），用于在 Handler 中查询 Distributor 实体 |
| `triggeringWithdrawLedgerId` | `?int` | 否 | 触发升级检查的提现流水 ID，用于日志追溯和历史记录关联 |

**设计约束**：

1. **不可变性**：使用 `readonly` 关键字（PHP 8.2+），确保消息在传递过程中不被修改
2. **仅传递 ID**：不传递完整实体对象，避免：
   - 序列化/反序列化复杂对象的性能开销
   - 实体状态在消息队列中过期（如消息延迟处理时，实体已被其他流程修改）
   - 循环引用导致的序列化失败
3. **轻量化**：消息大小 < 1KB，确保序列化和网络传输高效

**序列化**：Symfony Messenger 默认使用 PHP 原生序列化，支持简单对象自动序列化。

**示例**：

```php
$message = new DistributorUpgradeCheckMessage(
    distributorId: 12345,
    triggeringWithdrawLedgerId: 67890,
);
```

---

## 复用的持久化实体

本功能不新增实体，复用以下现有实体：

### Distributor（分销员）

**来源**：`tourze/order-commission-bundle`

**用途**：
- Handler 通过 `distributorId` 查询分销员实体
- 传递给 `DistributorUpgradeService::checkAndUpgrade()` 执行升级检查

**关键属性**（与本功能相关）：
- `id`：分销员唯一标识
- `level`：当前等级（DistributorLevel 实体）
- 其他业务属性（由 `UpgradeContextProvider` 查询）

### WithdrawLedger（提现流水）

**来源**：`tourze/order-commission-bundle`

**用途**：
- 触发升级检查的提现流水记录
- 通过 `triggeringWithdrawLedgerId` 查询，传递给升级服务用于历史记录关联

**关键属性**（与本功能相关）：
- `id`：提现流水唯一标识
- `status`：提现状态（Completed 时触发升级检查）
- `distributor`：关联的分销员

### DistributorLevelUpgradeHistory（升级历史）

**来源**：本 Bundle（现有实体）

**用途**：
- 记录升级成功的历史记录
- 用于幂等性检查（避免重复升级）

**关键属性**（与本功能相关）：
- `distributor`：升级的分销员
- `previousLevel`：升级前等级
- `newLevel`：升级后等级
- `triggeringWithdrawLedger`：触发升级的提现流水（可选）
- `upgradeTime`：升级时间

---

## 消息处理器（Handler）

### DistributorUpgradeCheckHandler

**类型**：服务类（Service），由 Symfony Messenger 自动注册为消息消费者。

**职责**：
1. 从消息队列中接收 `DistributorUpgradeCheckMessage`
2. 根据 `distributorId` 查询 Distributor 实体
3. 根据 `triggeringWithdrawLedgerId` 查询 WithdrawLedger 实体（如果提供）
4. 调用 `DistributorUpgradeService::checkAndUpgrade()` 执行升级检查
5. 记录日志（成功/失败/跳过）

**依赖**：
- `EntityManagerInterface`（查询实体）
- `DistributorUpgradeService`（执行升级逻辑）
- `LoggerInterface`（记录日志）

**幂等性保障**：
- 复用 `DistributorUpgradeService` 的幂等性逻辑（检查当前等级是否已升级）
- 不需要额外的去重机制

**错误处理**：
- 分销员不存在：记录警告日志，不抛异常（消息标记为成功，避免无限重试）
- 升级逻辑失败：抛出异常，触发 Symfony Messenger 重试机制
- 数据库临时不可用：抛出异常，触发重试

---

## 消息队列传输层（基础设施）

### Doctrine Transport（数据库队列）

**表结构**：由 Symfony Messenger 自动创建（通过 `auto_setup: true` 配置）

**表名**：`messenger_messages`（默认）

**关键字段**：

| 字段名 | 类型 | 说明 |
|--------|------|------|
| `id` | `BIGINT` | 主键（自增） |
| `body` | `LONGTEXT` | 序列化后的消息内容 |
| `headers` | `LONGTEXT` | 消息头（元数据，JSON 格式） |
| `queue_name` | `VARCHAR(190)` | 队列名称（如 `async_messages`） |
| `created_at` | `DATETIME` | 消息创建时间 |
| `available_at` | `DATETIME` | 消息可消费时间（延迟投递） |
| `delivered_at` | `DATETIME` | 消息消费时间（NULL 表示未消费） |

**索引**：
- `queue_name` + `available_at` + `delivered_at`（查询待消费消息）

**死信队列表**：`failed_messages`（结构相同）

---

## 数据流向

### 正常流程

```
1. 提现流水状态变更为 Completed
   ↓
2. WithdrawLedgerStatusListener 投递消息到队列
   DistributorUpgradeCheckMessage(distributorId, withdrawLedgerId)
   ↓
3. 消息写入数据库表 messenger_messages
   ↓
4. Worker 进程查询待消费消息
   ↓
5. DistributorUpgradeCheckHandler 消费消息
   - 查询 Distributor、WithdrawLedger 实体
   - 调用 DistributorUpgradeService::checkAndUpgrade()
   ↓
6a. 升级成功 → 写入 DistributorLevelUpgradeHistory
6b. 条件不满足 → 无操作，记录日志
6c. 失败 → 抛异常，消息重新入队（重试）
```

### 失败重试流程

```
1. Handler 抛出异常
   ↓
2. Symfony Messenger 捕获异常
   ↓
3. 根据 retry_strategy 配置重试
   - 第1次重试：延迟 1 秒
   - 第2次重试：延迟 5 秒
   - 第3次重试：延迟 25 秒
   ↓
4a. 重试成功 → 标记消息为已消费
4b. 达到最大重试次数 → 移入死信队列 failed_messages
```

---

## 数据一致性保障

### 事务边界

**提现状态更新 + 消息投递**：
- 在同一数据库事务中完成（利用 Doctrine Transport 的事务特性）
- 确保消息投递成功 ⇔ 状态更新成功（原子性）

**升级检查执行**：
- `DistributorUpgradeService::performUpgrade()` 内部使用事务（已有逻辑）
- 确保分销员等级更新 + 历史记录写入的原子性

### 幂等性

**场景**：同一分销员的升级检查消息被重复消费（重试或重复投递）

**保障机制**：
1. `DistributorUpgradeService::checkAndUpgrade()` 在执行升级前检查当前等级
2. 如果分销员已升级到目标等级，跳过升级操作，返回 `null`
3. 不会产生重复的升级历史记录

**验证方式**：集成测试 `IdempotencyTest.php`

---

## 性能考量

### 消息大小

- 单条消息：~100 字节（两个整数 ID）
- 序列化后：~200 字节（包含元数据）

### 队列容量

- 假设峰值流量 100 msg/s，单个消息处理时间 3 秒
- 队列积压量：300 条消息（可接受）
- 数据库表大小：<< 1MB（可忽略）

### 索引优化

- Symfony Messenger 自动创建的索引已优化查询性能
- 定期清理已消费消息（配置 `remove_after: 7 days`）

---

## 总结

### 新增对象

| 对象名 | 类型 | 持久化 | 用途 |
|--------|------|--------|------|
| `DistributorUpgradeCheckMessage` | 消息类 | 否（仅序列化到队列） | 异步消息载体 |
| `DistributorUpgradeCheckHandler` | 服务类 | 否 | 消息消费者 |

### 复用实体

- `Distributor`（tourze/order-commission-bundle）
- `WithdrawLedger`（tourze/order-commission-bundle）
- `DistributorLevelUpgradeHistory`（本 Bundle）

### 基础设施表

- `messenger_messages`（Symfony Messenger 自动创建）
- `failed_messages`（死信队列，Symfony Messenger 自动创建）

### 数据一致性

- 事务边界：提现更新 + 消息投递（原子性）
- 幂等性：复用现有升级逻辑的幂等性保障
- 错误处理：重试机制 + 死信队列
