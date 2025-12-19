# 快速开始：异步升级检查

**Feature**: async-upgrade-check | **日期**: 2025-11-19

本指南帮助开发者快速理解和实施异步升级检查功能。

---

## 概述

### 功能目标

将分销员升级检查从同步改为异步，解决性能问题：
- **当前问题**：提现流水状态更新时同步执行升级检查，响应时间 ~500ms
- **改进后**：异步投递消息到队列，响应时间降至 <100ms
- **核心价值**：提升用户体验，避免阻塞提现流程

### 技术方案

- **消息队列**：Symfony Messenger + Doctrine Transport（数据库队列）
- **消息类**：`DistributorUpgradeCheckMessage`（实现 `AsyncMessageInterface`）
- **消费者**：`DistributorUpgradeCheckHandler`（复用 `DistributorUpgradeService`）
- **触发点**：`WithdrawLedgerStatusListener` 改为异步投递消息

---

## 前置准备

### 1. 确认依赖

检查 Monorepo 根目录 `composer.json` 是否包含以下依赖：

```bash
# 检查 Symfony Messenger
grep "symfony/messenger" composer.json

# 检查 Doctrine Messenger（数据库传输层）
grep "symfony/doctrine-messenger" composer.json
```

如果未安装，在**根目录**执行：

```bash
composer require symfony/messenger symfony/doctrine-messenger
```

### 2. 配置 Symfony Messenger

在应用层配置文件（如 `apps/your-app/config/packages/messenger.yaml` 或 `services/your-service/config/packages/messenger.yaml`）中添加：

```yaml
framework:
    messenger:
        # 定义传输层（使用数据库作为队列）
        transports:
            async:
                dsn: 'doctrine://default'  # 使用默认数据库连接
                options:
                    queue_name: 'async_messages'
                    auto_setup: true  # 自动创建队列表
                retry_strategy:
                    max_retries: 3
                    delay: 1000        # 1秒
                    multiplier: 5      # 指数退避：1s, 5s, 25s
                    max_delay: 30000   # 最大30秒

            # 死信队列
            failed:
                dsn: 'doctrine://default?queue_name=failed_messages'

        # 消息路由
        routing:
            'Tourze\CommissionUpgradeBundle\Message\DistributorUpgradeCheckMessage': async

        # 失败消息传输层
        failure_transport: failed
```

### 3. 创建队列表

执行数据库 schema 更新（开发环境）：

```bash
bin/console doctrine:schema:update --force
```

或查看 SQL（生产环境由运维执行）：

```bash
bin/console doctrine:schema:update --dump-sql
```

**预期输出**：创建 `messenger_messages` 和 `failed_messages` 表

---

## 实施步骤

### 第 1 步：创建消息类

**文件路径**：`packages/commission-upgrade-bundle/src/Message/DistributorUpgradeCheckMessage.php`

```php
<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Message;

use Tourze\AsyncContracts\AsyncMessageInterface;

/**
 * 分销员升级检查异步消息
 */
final readonly class DistributorUpgradeCheckMessage implements AsyncMessageInterface
{
    public function __construct(
        public int $distributorId,
        public ?int $triggeringWithdrawLedgerId = null,
    ) {
    }
}
```

**关键点**：
- ✅ 实现 `AsyncMessageInterface` 接口
- ✅ 使用 `readonly` 确保不可变性（PHP 8.2+）
- ✅ 仅传递 ID（而非完整实体）

---

### 第 2 步：创建消息处理器（Handler）

**文件路径**：`packages/commission-upgrade-bundle/src/MessageHandler/DistributorUpgradeCheckHandler.php`

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

        // 3. 执行升级检查（复用现有服务）
        try {
            $history = $this->upgradeService->checkAndUpgrade($distributor, $triggeringLedger);

            if (null !== $history) {
                $this->logger->info('分销员升级成功', [
                    'distributor_id' => $distributor->getId(),
                    'previous_level' => $history->getPreviousLevel()->getName(),
                    'new_level' => $history->getNewLevel()->getName(),
                ]);
            } else {
                $this->logger->debug('升级条件不满足', [
                    'distributor_id' => $distributor->getId(),
                    'current_level' => $distributor->getLevel()->getName(),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('升级检查失败', [
                'distributor_id' => $distributor->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e; // 触发重试
        }
    }
}
```

**关键点**：
- ✅ 使用 `#[AsMessageHandler]` 属性自动注册
- ✅ 复用 `DistributorUpgradeService`（不修改现有逻辑）
- ✅ 分销员不存在时不抛异常（避免无限重试）
- ✅ 其他异常抛出，触发重试机制

---

### 第 3 步：修改 EventListener 改为异步投递

**文件路径**：`packages/commission-upgrade-bundle/src/EventListener/WithdrawLedgerStatusListener.php`

**修改前（同步）**：

```php
public function __invoke(WithdrawLedger $entity): void
{
    if (WithdrawLedgerStatus::Completed !== $entity->getStatus()) {
        return;
    }

    $distributor = $entity->getDistributor();

    try {
        $history = $this->upgradeService->checkAndUpgrade($distributor, $entity);
        // ...
    } catch (\Throwable $e) {
        // ...
    }
}
```

**修改后（异步）**：

```php
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\CommissionUpgradeBundle\Message\DistributorUpgradeCheckMessage;

#[AsEntityListener(event: Events::postUpdate, entity: WithdrawLedger::class)]
#[AsEntityListener(event: Events::postPersist, entity: WithdrawLedger::class)]
#[WithMonologChannel(channel: 'commission_upgrade')]
final readonly class WithdrawLedgerStatusListener
{
    public function __construct(
        private MessageBusInterface $messageBus,  // ← 注入 MessageBus
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(WithdrawLedger $entity): void
    {
        // 仅处理状态为 Completed 的提现记录
        if (WithdrawLedgerStatus::Completed !== $entity->getStatus()) {
            return;
        }

        $distributor = $entity->getDistributor();

        try {
            // 异步投递消息到队列
            $message = new DistributorUpgradeCheckMessage(
                distributorId: $distributor->getId(),
                triggeringWithdrawLedgerId: $entity->getId(),
            );

            $this->messageBus->dispatch($message);

            $this->logger->info('升级检查消息已投递', [
                'distributor_id' => $distributor->getId(),
                'withdraw_ledger_id' => $entity->getId(),
            ]);
        } catch (\Throwable $e) {
            // 消息投递失败不应阻断提现流程，仅记录错误
            $this->logger->error('升级检查消息投递失败', [
                'distributor_id' => $distributor->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

**关键变更**：
- ✅ 移除 `DistributorUpgradeService` 依赖
- ✅ 注入 `MessageBusInterface`
- ✅ 投递消息而非直接调用服务
- ✅ 投递失败时记录日志但不抛异常（避免阻塞提现）

---

### 第 4 步：启动消息消费者（Worker）

在服务器或本地开发环境启动 Worker 进程：

```bash
# 单个 Worker
bin/console messenger:consume async --limit=100

# 多个并发 Worker（提升吞吐量）
bin/console messenger:consume async --limit=100 &
bin/console messenger:consume async --limit=100 &
bin/console messenger:consume async --limit=100 &
bin/console messenger:consume async --limit=100 &
```

**参数说明**：
- `async`：传输层名称（对应配置中的 `transports.async`）
- `--limit=100`：处理 100 条消息后重启 Worker（避免内存泄漏）

**生产环境部署**（使用 Supervisor）：

```ini
[program:messenger-consume]
command=php /path/to/bin/console messenger:consume async --limit=100 --time-limit=3600
user=www-data
numprocs=4
autostart=true
autorestart=true
process_name=%(program_name)s_%(process_num)02d
```

---

## 验证功能

### 1. 单元测试

测试消息类和 Handler（测试文件镜像 src/ 结构）：

```bash
# 测试消息类
./vendor/bin/phpunit packages/commission-upgrade-bundle/tests/Message/

# 测试 Handler
./vendor/bin/phpunit packages/commission-upgrade-bundle/tests/MessageHandler/
```

### 2. 集成测试

测试端到端流程（Bundle 级别测试）：

```bash
# 异步升级流程集成测试
./vendor/bin/phpunit packages/commission-upgrade-bundle/tests/AsyncUpgradeFlowTest.php

# 幂等性集成测试
./vendor/bin/phpunit packages/commission-upgrade-bundle/tests/IdempotencyTest.php
```

### 3. 契约测试

验证 AsyncMessageInterface 接口实现（Bundle 级别测试）：

```bash
./vendor/bin/phpunit packages/commission-upgrade-bundle/tests/AsyncMessageInterfaceContractTest.php
```

### 4. 手动验证

1. 创建一笔提现流水并标记为 Completed
2. 检查队列表：`SELECT * FROM messenger_messages WHERE queue_name = 'async_messages';`
3. 启动 Worker：`bin/console messenger:consume async`
4. 验证升级结果：检查分销员等级和升级历史

### 4. 监控队列

```bash
# 查看队列统计
bin/console messenger:stats

# 查看失败消息
bin/console messenger:failed:show

# 重试失败消息
bin/console messenger:failed:retry <id>
```

---

## 常见问题

### Q1: 如何批量触发升级检查？

**答**：创建批量命令（下一阶段实现）：

```bash
bin/console commission-upgrade:batch-check --level=1 --limit=1000
```

### Q2: 消息处理失败怎么办？

**答**：
1. 检查日志（`var/log/*.log`）
2. 查看死信队列：`bin/console messenger:failed:show`
3. 分析失败原因后手动重试：`bin/console messenger:failed:retry <id>`

### Q3: 如何提升处理速度？

**答**：
1. 增加并发 Worker 数量（4~8 个）
2. 优化数据库查询（确保索引已创建）
3. 监控 Worker 资源占用（CPU、内存）

### Q4: 异步化后如何保证可靠性？

**答**：
1. **事务一致性**：消息投递和提现状态更新在同一事务中
2. **重试机制**：临时故障自动重试（最多 3 次）
3. **死信队列**：失败消息保留，人工介入
4. **幂等性**：复用现有服务的幂等性保障，避免重复升级

---

## 性能基准

### 预期改进

| 指标 | 改进前（同步） | 改进后（异步） | 提升 |
|------|----------------|----------------|------|
| 提现响应时间（P95） | 500ms | <100ms | 80% ↓ |
| 并发处理能力 | 受限于同步执行 | 支持 100+ msg/s | - |
| 系统解耦 | 紧耦合 | 松耦合 | - |

### 性能测试

使用 Apache Bench 或 JMeter 压测提现接口：

```bash
# 压测提现状态更新（模拟 100 并发）
ab -n 1000 -c 100 http://localhost/api/withdraw-ledger/complete
```

**验证指标**：
- 响应时间 P95 < 100ms
- 消息投递成功率 >= 99.9%
- 无消息丢失

---

## 下一步

功能实施完成后：

1. **Phase 2**：使用 `/speckit.tasks` 生成任务清单（`tasks.md`）
2. **Phase 3**：使用 `/speckit.implement` 执行任务清单
3. **Phase 4**：使用 `/speckit.analyze` 进行跨文档一致性检查

完整实施流程请参考：
- [spec.md](./spec.md)：功能规格说明
- [plan.md](./plan.md)：实施方案
- [contracts/](./contracts/)：服务契约文档
