# 契约：DistributorUpgradeCheckHandler

**类型**：消息处理器（Message Handler）
**用途**：处理分销员升级检查异步消息
**实现路径**：`src/MessageHandler/DistributorUpgradeCheckHandler.php`

---

## 接口契约

### Symfony Messenger Handler

**约定**：实现 `__invoke(DistributorUpgradeCheckMessage $message): void` 方法

**注册方式**：通过 Symfony 依赖注入自动注册为消息处理器（无需手动配置）

**配置**：
```yaml
# services.yaml（Symfony 自动发现）
services:
    _defaults:
        autowire: true
        autoconfigure: true

    Tourze\CommissionUpgradeBundle\MessageHandler\:
        resource: '../MessageHandler/'
        tags: ['messenger.message_handler']
```

---

## 方法契约

### __invoke(DistributorUpgradeCheckMessage $message): void

**职责**：处理升级检查消息，执行分销员升级逻辑

**输入参数**：
- `$message`：`DistributorUpgradeCheckMessage` 消息对象

**返回值**：`void`（无返回值）

**行为约束**：

1. **查询分销员实体**：
   - 通过 `$message->distributorId` 查询 `Distributor` 实体
   - 如果分销员不存在：
     - 记录警告日志（包含 distributorId）
     - 不抛异常，正常返回（避免无限重试）

2. **查询触发源（可选）**：
   - 如果 `$message->triggeringWithdrawLedgerId` 不为 `null`：
     - 查询 `WithdrawLedger` 实体
     - 如果不存在：记录警告日志，但继续执行升级检查
   - 如果为 `null`：跳过触发源查询

3. **执行升级检查**：
   - 调用 `DistributorUpgradeService::checkAndUpgrade($distributor, $triggeringLedger)`
   - 根据返回值判断升级结果：
     - `DistributorLevelUpgradeHistory` 对象：升级成功
     - `null`：条件不满足或已达最高等级

4. **记录日志**：
   - 升级成功：记录 INFO 日志（包含分销员 ID、前后等级、触发源）
   - 条件不满足：记录 DEBUG 日志（包含分销员 ID、当前等级）
   - 执行失败：记录 ERROR 日志（包含异常堆栈、上下文快照），抛出异常触发重试

5. **异常处理**：
   - 分销员/触发源不存在：不抛异常（数据不一致场景，记录日志并跳过）
   - 升级逻辑失败（如数据库异常）：抛出异常，触发 Symfony Messenger 重试机制
   - 乐观锁冲突（`OptimisticLockException`）：抛出异常，触发重试

---

## 依赖契约

### EntityManagerInterface

**用途**：查询 `Distributor` 和 `WithdrawLedger` 实体

**方法调用**：
```php
$distributor = $this->entityManager->find(Distributor::class, $message->distributorId);
$withdrawLedger = $this->entityManager->find(WithdrawLedger::class, $message->triggeringWithdrawLedgerId);
```

### DistributorUpgradeService

**用途**：执行升级检查核心逻辑

**方法调用**：
```php
$history = $this->upgradeService->checkAndUpgrade($distributor, $withdrawLedger);
```

**约定**：
- 不修改 `DistributorUpgradeService` 逻辑（复用现有实现）
- 复用其幂等性保障（避免重复升级）

### LoggerInterface

**用途**：记录日志

**日志级别**：
- `DEBUG`：条件不满足，跳过升级
- `INFO`：升级成功
- `WARNING`：分销员/触发源不存在
- `ERROR`：升级失败，包含异常堆栈

**日志上下文**（结构化 JSON）：
```php
$this->logger->info('分销员升级成功', [
    'distributor_id' => $distributor->getId(),
    'previous_level' => $history->getPreviousLevel()->getName(),
    'new_level' => $history->getNewLevel()->getName(),
    'triggering_withdraw_ledger_id' => $message->triggeringWithdrawLedgerId,
]);
```

---

## 幂等性契约

### 约束

**多次执行相同消息，结果一致**：
- 同一分销员的升级检查消息被重复消费（重试或重复投递）
- 不会产生重复的升级历史记录
- 不会导致数据不一致

### 实现方式

**复用 DistributorUpgradeService 的幂等性逻辑**：
- `checkAndUpgrade()` 方法在执行升级前检查当前等级
- 如果分销员已升级到目标等级，返回 `null`，不执行升级操作

**Handler 无需额外去重**：
- 不维护消息处理记录表
- 不检查消息是否已处理

### 验证方式

**集成测试**（`tests/IdempotencyTest.php` - Bundle 级别）：
1. 投递相同的升级检查消息 3 次
2. 验证只产生 1 条升级历史记录
3. 验证分销员等级正确（升级 1 次）

---

## 错误场景与处理策略

### 场景 1：分销员不存在

**触发条件**：`$message->distributorId` 对应的 Distributor 实体不存在

**处理策略**：
1. 记录 WARNING 日志：`分销员不存在，跳过升级检查 [distributor_id=12345]`
2. 正常返回（不抛异常）
3. 消息标记为已消费（避免无限重试）

**理由**：数据不一致场景（可能因分销员被删除），重试无意义

---

### 场景 2：触发源不存在

**触发条件**：`$message->triggeringWithdrawLedgerId` 对应的 WithdrawLedger 实体不存在

**处理策略**：
1. 记录 WARNING 日志：`触发提现流水不存在 [withdraw_ledger_id=67890]`
2. 继续执行升级检查（传递 `null` 给 `checkAndUpgrade()`）
3. 不抛异常

**理由**：触发源仅用于日志追溯，缺失不影响升级逻辑

---

### 场景 3：数据库临时不可用

**触发条件**：查询实体时抛出数据库异常（如连接超时、锁等待超时）

**处理策略**：
1. 记录 ERROR 日志（包含异常堆栈）
2. 抛出异常（不捕获）
3. Symfony Messenger 触发重试机制（指数退避：1s、5s、25s）

**理由**：临时故障，重试可能成功

---

### 场景 4：升级逻辑执行失败

**触发条件**：`DistributorUpgradeService::checkAndUpgrade()` 抛出异常

**处理策略**：
1. 记录 ERROR 日志（包含分销员 ID、异常堆栈、上下文快照）
2. 抛出异常（不捕获）
3. 触发重试机制

**常见失败原因**：
- 乐观锁冲突（`OptimisticLockException`）：并发修改分销员等级
- 数据库约束违反（理论上不应发生）
- 其他业务逻辑异常

---

### 场景 5：达到最大重试次数

**触发条件**：消息连续失败 3 次（配置的最大重试次数）

**处理策略**：
1. Symfony Messenger 将消息移入死信队列（`failed_messages` 表）
2. 记录 ERROR 日志（标记为最终失败）
3. 触发监控告警（运营人员介入）

**运营人员操作**：
1. 查看死信队列：`bin/console messenger:failed:show`
2. 分析失败原因（检查日志）
3. 手动重试：`bin/console messenger:failed:retry <id>`
4. 或标记为无法恢复：`bin/console messenger:failed:remove <id>`

---

## 性能约束

### 执行时间

**目标**：单个消息处理时间 <= 5 秒（P95 分位）

**组成**：
- 查询实体：< 100ms（索引优化）
- 执行升级检查：< 3 秒（现有逻辑性能）
- 日志记录：< 10ms

**超时处理**：Symfony Messenger Worker 默认无超时限制，依赖操作系统或进程管理器（如 Supervisor）

### 并发处理

**策略**：启动多个 Worker 进程并发消费消息

**配置**：
```bash
# 启动 4 个并发 Worker
bin/console messenger:consume async --limit=100 &
bin/console messenger:consume async --limit=100 &
bin/console messenger:consume async --limit=100 &
bin/console messenger:consume async --limit=100 &
```

**注意**：
- Worker 进程间无共享状态（无竞争条件）
- 数据库乐观锁保障并发安全

---

## 测试用例

### TC-001：正常升级成功

**前置条件**：
- 分销员 ID=12345 存在，当前等级「初级」，已有提现金额 4500 元
- 提现流水 ID=67890 存在，金额 1000 元，状态 Completed
- 升级规则：withdrawnAmount >= 5000 → 「中级」

**输入消息**：
```php
new DistributorUpgradeCheckMessage(12345, 67890)
```

**期望行为**：
1. 查询分销员和提现流水成功
2. 调用 `checkAndUpgrade()`，满足升级条件
3. 分销员等级更新为「中级」
4. 写入升级历史记录
5. 记录 INFO 日志

**验证**：
- 分销员等级 = 「中级」
- 升级历史表新增 1 条记录
- 日志包含「分销员升级成功」

---

### TC-002：条件不满足，跳过升级

**前置条件**：
- 分销员 ID=12345 存在，当前等级「初级」，已有提现金额 3000 元
- 提现流水 ID=67890 存在，金额 500 元
- 升级规则：withdrawnAmount >= 5000 → 「中级」

**输入消息**：
```php
new DistributorUpgradeCheckMessage(12345, 67890)
```

**期望行为**：
1. 查询分销员和提现流水成功
2. 调用 `checkAndUpgrade()`，不满足升级条件
3. 返回 `null`，不执行升级
4. 记录 DEBUG 日志

**验证**：
- 分销员等级保持「初级」
- 升级历史表无新增记录
- 日志包含「升级条件不满足」

---

### TC-003：分销员不存在

**前置条件**：
- 分销员 ID=99999 不存在

**输入消息**：
```php
new DistributorUpgradeCheckMessage(99999, null)
```

**期望行为**：
1. 查询分销员失败（返回 `null`）
2. 记录 WARNING 日志
3. 正常返回（不抛异常）

**验证**：
- 日志包含「分销员不存在，跳过升级检查」
- 消息标记为已消费（不进入重试）

---

### TC-004：幂等性验证

**前置条件**：
- 分销员 ID=12345 满足升级条件
- 相同消息投递 3 次

**输入消息**：
```php
// 投递 3 次
new DistributorUpgradeCheckMessage(12345, 67890)
new DistributorUpgradeCheckMessage(12345, 67890)
new DistributorUpgradeCheckMessage(12345, 67890)
```

**期望行为**：
1. 第 1 次消费：升级成功，等级更新为「中级」
2. 第 2 次消费：检测到已升级，返回 `null`，跳过
3. 第 3 次消费：同上

**验证**：
- 升级历史表仅 1 条记录
- 分销员等级 = 「中级」（未重复升级）
- 3 条消息均标记为已消费

---

### TC-005：数据库临时不可用，触发重试

**前置条件**：
- 数据库临时不可用（模拟连接超时）

**输入消息**：
```php
new DistributorUpgradeCheckMessage(12345, 67890)
```

**期望行为**：
1. 查询分销员抛出数据库异常
2. Handler 抛出异常（不捕获）
3. Messenger 捕获异常，消息重新入队
4. 延迟 1 秒后重试
5. 数据库恢复，重试成功

**验证**：
- 消息最终被成功消费
- 日志包含重试记录

---

## 示例实现框架

```php
<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;use Psr\Log\LoggerInterface;use Symfony\Component\Messenger\Attribute\AsMessageHandler;use Tourze\CommissionDistributorBundle\Entity\Distributor;use Tourze\CommissionUpgradeBundle\Message\DistributorUpgradeCheckMessage;use Tourze\CommissionUpgradeBundle\Service\DistributorUpgradeService;use Tourze\CommissionWithdrawBundle\Entity\WithdrawLedger;

#[AsMessageHandler]
final readonly class DistributorUpgradeCheckHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DistributorUpgradeService $upgradeService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DistributorUpgradeCheckMessage $message): void
    {
        // 1. 查询分销员
        $distributor = $this->entityManager->find(Distributor::class, $message->distributorId);
        if (null === $distributor) {
            $this->logger->warning('分销员不存在，跳过升级检查', [
                'distributor_id' => $message->distributorId,
            ]);
            return;
        }

        // 2. 查询触发源（可选）
        $triggeringLedger = null;
        if (null !== $message->triggeringWithdrawLedgerId) {
            $triggeringLedger = $this->entityManager->find(WithdrawLedger::class, $message->triggeringWithdrawLedgerId);
            if (null === $triggeringLedger) {
                $this->logger->warning('触发提现流水不存在', [
                    'withdraw_ledger_id' => $message->triggeringWithdrawLedgerId,
                ]);
            }
        }

        // 3. 执行升级检查
        try {
            $history = $this->upgradeService->checkAndUpgrade($distributor, $triggeringLedger);

            if (null !== $history) {
                $this->logger->info('分销员升级成功', [
                    'distributor_id' => $distributor->getId(),
                    'previous_level' => $history->getPreviousLevel()->getName(),
                    'new_level' => $history->getNewLevel()->getName(),
                    'triggering_withdraw_ledger_id' => $message->triggeringWithdrawLedgerId,
                ]);
            } else {
                $this->logger->debug('升级条件不满足', [
                    'distributor_id' => $distributor->getId(),
                    'current_level' => $distributor->getLevel()->getName(),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('分销员升级检查失败', [
                'distributor_id' => $distributor->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // 触发重试
        }
    }
}
```

---

## 总结

### 核心职责

1. ✅ 查询分销员和触发源实体
2. ✅ 调用 `DistributorUpgradeService` 执行升级检查
3. ✅ 记录结构化日志
4. ✅ 处理异常（区分可重试/不可重试场景）

### 关键约束

1. ✅ 幂等性（复用现有服务逻辑）
2. ✅ 错误处理（数据不一致 → 跳过，临时故障 → 重试）
3. ✅ 性能（单个消息 <= 5 秒）
4. ✅ 可观测性（结构化日志）

### 验证方式

- **单元测试**：`tests/MessageHandler/DistributorUpgradeCheckHandlerTest.php`（镜像 src/ 结构）
  - Mock 依赖，验证逻辑分支
- **集成测试**：`tests/AsyncUpgradeFlowTest.php`、`tests/IdempotencyTest.php`（Bundle 级别）
  - 端到端流程，验证幂等性
- **性能测试**：压测 Worker，验证吞吐量
