# 契约：DistributorUpgradeCheckMessage

**类型**：异步消息（Value Object）
**用途**：分销员升级检查的异步消息载体
**实现路径**：`src/Message/DistributorUpgradeCheckMessage.php`

---

## 接口契约

### 实现接口

```php
Tourze\AsyncContracts\AsyncMessageInterface
```

**说明**：标记接口，标识该类为异步消息，由 Symfony Messenger 识别和路由。

---

## 属性契约

### distributorId

**类型**：`int`
**必填**：是
**约束**：
- 必须 > 0（有效的数据库主键）
- 对应 `Distributor` 实体的主键

**用途**：唯一标识需要进行升级检查的分销员

**示例**：`12345`

### triggeringWithdrawLedgerId

**类型**：`?int`（可空整数）
**必填**：否
**约束**：
- 如果提供，必须 > 0
- 对应 `WithdrawLedger` 实体的主键

**用途**：
- 记录触发升级检查的提现流水 ID
- 用于日志追溯和历史记录关联
- 如果为 `null`，表示升级检查由其他途径触发（如批量检查命令）

**示例**：`67890` 或 `null`

---

## 行为约束

### 不可变性

**约束**：消息对象一旦创建，所有属性不可修改

**实现方式**：
- 使用 `readonly` 关键字（PHP 8.2+）
- 或使用 `final` 类 + 私有属性 + 无 setter 方法

**理由**：
- 避免消息在传递过程中被意外修改
- 确保消息内容与投递时一致（便于调试和追溯）

### 轻量化

**约束**：消息对象仅包含必要的标识信息（ID），不包含完整实体对象

**禁止**：
- ❌ 传递 `Distributor` 实体对象
- ❌ 传递 `WithdrawLedger` 实体对象
- ❌ 传递其他复杂对象或数组

**理由**：
- 避免序列化复杂对象的性能开销
- 避免实体状态在队列中过期（消息延迟处理时，实体可能已被修改）
- 避免循环引用导致的序列化失败

### 序列化兼容性

**约束**：消息对象必须可序列化（支持 PHP 原生序列化或 JSON 序列化）

**要求**：
- 所有属性必须是标量类型（`int`、`string`、`bool`、`float`）或可序列化对象
- 不包含资源类型（如数据库连接、文件句柄）
- 不包含匿名函数或闭包

**验证方式**：
```php
$message = new DistributorUpgradeCheckMessage(12345, 67890);
$serialized = serialize($message);
$unserialized = unserialize($serialized);
assert($unserialized == $message);
```

---

## 错误场景

### 无效的 distributorId

**场景**：传入 distributorId <= 0 或非整数

**期望行为**：
- 在消息创建时不进行验证（由 PHP 类型系统保证）
- 在 Handler 中查询 Distributor 实体时，如果不存在则记录警告日志并跳过处理

**不抛异常**：避免因数据不一致导致消息无限重试

### 无效的 triggeringWithdrawLedgerId

**场景**：传入 triggeringWithdrawLedgerId <= 0

**期望行为**：
- 在 Handler 中查询 WithdrawLedger 实体时，如果不存在则记录警告日志
- 继续执行升级检查（不因触发源缺失而中断）

---

## 测试用例

### TC-001：正常消息创建（带触发源）

**输入**：
```php
$message = new DistributorUpgradeCheckMessage(
    distributorId: 12345,
    triggeringWithdrawLedgerId: 67890
);
```

**期望输出**：
- `$message->distributorId === 12345`
- `$message->triggeringWithdrawLedgerId === 67890`

### TC-002：正常消息创建（不带触发源）

**输入**：
```php
$message = new DistributorUpgradeCheckMessage(
    distributorId: 12345
);
```

**期望输出**：
- `$message->distributorId === 12345`
- `$message->triggeringWithdrawLedgerId === null`

### TC-003：消息序列化与反序列化

**输入**：
```php
$original = new DistributorUpgradeCheckMessage(12345, 67890);
$serialized = serialize($original);
$restored = unserialize($serialized);
```

**期望输出**：
- `$restored->distributorId === $original->distributorId`
- `$restored->triggeringWithdrawLedgerId === $original->triggeringWithdrawLedgerId`

### TC-004：消息不可变性（如果使用 readonly）

**输入**：
```php
$message = new DistributorUpgradeCheckMessage(12345, 67890);
$message->distributorId = 99999; // 尝试修改
```

**期望输出**：
- PHP 8.2+：抛出 `Error`（Cannot modify readonly property）
- PHP < 8.2：通过私有属性 + 无 setter 避免修改

### TC-005：接口实现验证

**输入**：
```php
$message = new DistributorUpgradeCheckMessage(12345);
```

**期望输出**：
- `$message instanceof AsyncMessageInterface === true`

---

## 示例实现

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
    /**
     * @param int      $distributorId                分销员 ID
     * @param int|null $triggeringWithdrawLedgerId  触发升级检查的提现流水 ID（可选）
     */
    public function __construct(
        public int $distributorId,
        public ?int $triggeringWithdrawLedgerId = null,
    ) {
    }
}
```

---

## 配置示例

### Symfony Messenger Routing

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            'Tourze\CommissionUpgradeBundle\Message\DistributorUpgradeCheckMessage': async
```

**说明**：将该消息路由到 `async` 传输层（异步队列）

---

## 总结

### 核心约束

1. ✅ 实现 `AsyncMessageInterface` 接口
2. ✅ 不可变（`readonly` 或私有属性）
3. ✅ 仅包含 ID（不传递完整实体）
4. ✅ 可序列化（标量类型属性）

### 验证方式

- **单元测试**：`tests/Message/DistributorUpgradeCheckMessageTest.php`（镜像 src/ 结构）
  - 验证消息创建、属性访问、序列化
- **契约测试**：`tests/AsyncMessageInterfaceContractTest.php`（Bundle 级别）
  - 验证 AsyncMessageInterface 接口实现
- **集成测试**：`tests/AsyncUpgradeFlowTest.php`（Bundle 级别）
  - 验证消息在队列中的完整流转
