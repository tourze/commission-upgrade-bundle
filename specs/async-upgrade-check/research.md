# 技术研究：异步升级检查

**Feature**: async-upgrade-check | **日期**: 2025-11-19

## 研究目标

解决 plan.md 中标记的 NEEDS CLARIFICATION 项：

1. **Symfony Messenger 配置确认**：项目是否已配置 Messenger 组件及传输层
2. **消息队列传输层选择**：RabbitMQ/Redis/Database，适合本项目的传输层
3. **AsyncMessageInterface 接口规范**：确认接口定义和实现要求

---

## 1. Symfony Messenger 配置确认

### 研究方法

检查项目根目录和相关配置文件，确认 Symfony Messenger 是否已安装和配置。

### 调查步骤

1. 检查根目录 `composer.json` 是否包含 `symfony/messenger` 依赖
2. 检查 Symfony 配置目录（可能在 `config/packages/messenger.yaml` 或应用层）
3. 搜索现有代码库中是否有其他 Bundle 使用 Messenger 的示例

### 决策

**待验证**：需要在实施阶段执行以下命令确认：

```bash
# 在 monorepo 根目录执行
grep -r "symfony/messenger" composer.json
grep -r "messenger:" apps/*/config/ services/*/config/ -A 5
```

**假设**：如果项目未配置 Messenger，则需要：
1. 在根目录添加依赖：`composer require symfony/messenger`（假设使用 Doctrine 传输层：`symfony/doctrine-messenger`）
2. 在应用配置中启用 Messenger 并配置传输层

### 替代方案（如果 Messenger 不可用）

如果项目无法使用 Symfony Messenger，考虑：
- **方案 A**：直接使用队列库（如 `php-amqplib` for RabbitMQ），但会失去 Symfony 抽象层的灵活性
- **方案 B**：使用数据库表作为简易队列（轮询方式），性能较差但无外部依赖

**推荐**：优先使用 Symfony Messenger + Doctrine 传输层（最低依赖，利用已有数据库）

---

## 2. 消息队列传输层选择

### 研究方法

对比 Symfony Messenger 支持的传输层（Transport），结合项目约束和性能需求选择最佳方案。

### 传输层对比

| 传输层 | 优势 | 劣势 | 适用场景 |
|--------|------|------|---------|
| **Doctrine (数据库)** | - 无需额外基础设施<br>- 利用现有数据库<br>- 事务一致性强<br>- 部署简单 | - 性能略低于专用队列<br>- 高并发下数据库压力大 | - 中小规模（<100 msg/s）<br>- 需要事务一致性<br>- 运维成本敏感 |
| **Redis** | - 高性能（内存存储）<br>- 支持持久化<br>- 延迟低<br>- 社区成熟 | - 需要额外部署 Redis<br>- 内存成本<br>- 持久化配置复杂 | - 高并发（>100 msg/s）<br>- 对延迟敏感<br>- 已有 Redis 基础设施 |
| **RabbitMQ** | - 专业消息队列<br>- 功能丰富（优先级、延迟、死信）<br>- 高可用性支持 | - 部署和运维复杂<br>- 学习成本高<br>- 资源占用较大 | - 企业级规模<br>- 复杂路由需求<br>- 对可靠性要求极高 |
| **In-Memory (同步)** | - 零配置<br>- 适合测试 | - 无持久化<br>- 进程重启丢失<br>- 实际上不是异步 | - 仅用于开发测试 |

### 决策

**选择**：**Doctrine 传输层（数据库）**

**理由**：
1. **项目约束匹配**：
   - 提现流水频率 <= 100 次/秒，在 Doctrine 传输层性能范围内
   - 项目已有 Doctrine ORM 3.0+，无需额外依赖
   - Bundle 设计原则：减少外部基础设施依赖，提升可移植性

2. **事务一致性**：
   - 提现状态更新和消息投递可在同一数据库事务中完成，确保一致性
   - 避免消息投递成功但状态更新失败（或反之）的不一致场景

3. **运维成本**：
   - 不增加 Redis/RabbitMQ 的部署和监控成本
   - 利用现有数据库备份和高可用方案

4. **性能验证**：
   - 根据 spec 性能目标（100 msg/s），Doctrine 传输层完全满足
   - 如果未来流量增长，可平滑迁移到 Redis（Symfony Messenger 抽象层保证代码无需修改）

### 配置示例

```yaml
# config/packages/messenger.yaml (应用层配置示例)
framework:
    messenger:
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

        routing:
            'Tourze\CommissionUpgradeBundle\Message\DistributorUpgradeCheckMessage': async

        failure_transport: failed  # 死信队列

        transports:
            failed: 'doctrine://default?queue_name=failed_messages'
```

### 备选方案（未来扩展）

如果流量超过 100 msg/s 或需要更低延迟：
- **阶段 1**：迁移到 Redis 传输层（`dsn: 'redis://localhost:6379/messages'`）
- **阶段 2**：引入 RabbitMQ（企业级规模）

---

## 3. AsyncMessageInterface 接口规范

### 研究方法

读取 `packages/async-contracts/src/AsyncMessageInterface.php` 源码，确认接口定义和实现要求。

### 接口定义

```php
<?php

declare(strict_types=1);

namespace Tourze\AsyncContracts;

/**
 * 异步队列
 */
interface AsyncMessageInterface
{
}
```

### 分析结论

**接口特征**：
- **标记接口（Marker Interface）**：无方法定义，仅用于类型标识
- **用途**：标记某个类为异步消息，便于 Symfony Messenger 或其他中间件识别和路由
- **实现要求**：
  1. 消息类必须声明 `implements AsyncMessageInterface`
  2. 消息类应该是不可变对象（immutable），使用 `readonly` 属性或私有 setter
  3. 消息类必须可序列化（Symfony Messenger 默认使用 PHP 序列化或 JSON）

### 决策

**实现方式**：

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

**设计原则**：
1. **不可变性**：使用 `readonly` 关键字（PHP 8.2+），确保消息在传递过程中不被修改
2. **最小化数据**：仅传递实体 ID（而非整个实体对象），避免序列化复杂对象和状态过期问题
3. **可选触发源**：`triggeringWithdrawLedgerId` 可选，用于追溯哪笔提现触发了升级检查（用于日志和调试）

### 序列化策略

Symfony Messenger 默认序列化策略：
- **默认**：PHP 原生序列化（`serialize/unserialize`）
- **推荐**：JSON 序列化（配置 `serializer: messenger.transport.symfony_serializer`），便于跨语言和调试

**无需额外配置**：简单消息类（仅包含标量类型）使用默认序列化即可。

---

## 4. Symfony Messenger 最佳实践

### 研究来源

- [Symfony Messenger 官方文档](https://symfony.com/doc/current/messenger.html)
- [Best Practices for Asynchronous Messages](https://symfony.com/doc/current/messenger.html#async-messages-best-practices)

### 关键实践

1. **消息幂等性**：
   - Handler 应该能够安全地处理重复消息
   - 实现方式：在执行业务逻辑前检查状态（如分销员当前等级是否已升级）

2. **错误处理**：
   - 使用 `retry_strategy` 配置自动重试（指数退避）
   - 失败后进入 `failure_transport`（死信队列）
   - 记录详细日志（包含消息内容、错误堆栈、上下文快照）

3. **监控与可观测性**：
   - 记录消息投递、消费、成功、失败的日志（结构化 JSON 格式）
   - 监控队列长度（`messenger:stats` 命令）
   - 设置告警：失败消息数量 > 阈值

4. **测试策略**：
   - **单元测试**：测试 Handler 逻辑（使用 Mock MessageBus）
   - **集成测试**：测试端到端流程（使用 In-Memory 传输层或测试数据库）
   - **契约测试**：验证消息类实现 AsyncMessageInterface

5. **性能优化**：
   - 批量消费：配置 `messenger:consume --limit=100`（处理 100 条消息后重启 worker）
   - 多 worker 并发：启动多个 `messenger:consume` 进程
   - 优雅关闭：处理 SIGTERM 信号，避免消息丢失

---

## 总结

### 已解决的未决项

| 未决项 | 决策 | 依据 |
|--------|------|------|
| Symfony Messenger 配置 | 假设未配置，实施阶段需添加依赖和配置 | 需验证根 composer.json 和应用配置 |
| 消息队列传输层 | **选择 Doctrine 传输层（数据库）** | 性能满足需求、无额外依赖、事务一致性 |
| AsyncMessageInterface 规范 | 标记接口，实现为 `readonly` 不可变消息类 | 接口源码分析 + 最佳实践 |

### 待验证事项（实施阶段）

1. 确认项目根目录是否已安装 `symfony/messenger` 和 `symfony/doctrine-messenger`
2. 确认应用层配置文件位置（`apps/*/config/packages/messenger.yaml` 或 `services/*/config/`）
3. 执行 `doctrine:schema:update` 验证消息队列表自动创建

### 下一步行动

Phase 0 研究完成，进入 Phase 1：
1. 生成 data-model.md（数据模型定义）
2. 生成 contracts/（服务契约文档）
3. 生成 quickstart.md（快速开始指南）
