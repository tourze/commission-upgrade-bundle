# 快速入门指南 - 分销员自动升级

**Feature**: `distributor-auto-upgrade` | **Scope**: `packages/commission-upgrade-bundle` | **日期**: 2025-11-17

## 1. 概述

本指南帮助开发者快速上手分销员自动升级功能，涵盖：

- 环境搭建与依赖安装
- 数据库迁移与初始数据
- 升级规则配置
- 测试验证
- 常见问题排查

**预计完成时间**：30分钟

---

## 2. 前置条件

### 2.1 环境要求

- PHP >= 8.2
- Symfony >= 7.3
- MySQL >= 8.0
- Composer

### 2.2 必需Bundle

- `tourze/order-commission-bundle`：提供分销员、等级、提现流水等基础实体
- `easycorp/easyadmin-bundle`：后台管理界面
- `symfony/expression-language`：表达式引擎

---

## 3. 安装步骤

### 3.1 安装依赖

```bash
cd /Users/air/work/php-monorepo/packages/commission-upgrade-bundle

# 安装 Symfony Expression Language
composer require symfony/expression-language:^7.3
```

### 3.2 注册Bundle

在主应用的 `config/bundles.php` 中注册：

```php
return [
    // ...
    Tourze\CommissionUpgradeBundle\CommissionUpgradeBundle::class => ['all' => true],
];
```

### 3.3 配置服务（可选）

Bundle 已通过自动配置注册服务，无需手动配置。如需自定义，可在 `config/services.yaml` 添加：

```yaml
services:
    # 覆盖默认配置
    Tourze\CommissionUpgradeBundle\Service\UpgradeExpressionEvaluator:
        arguments:
            - '@logger'
```

---

## 4. 初始化数据

### 4.1 创建分销员等级

假设已在 OrderCommissionBundle 创建了以下等级：

```sql
-- 一级分销员（默认等级）
INSERT INTO order_commission_distributor_level
(id, name, sort, is_default, create_time)
VALUES
(1, '一级分销员', 1, true, NOW());

-- 二级分销员
INSERT INTO order_commission_distributor_level
(id, name, sort, is_default, create_time)
VALUES
(2, '二级分销员', 2, false, NOW());

-- 三级分销员
INSERT INTO order_commission_distributor_level
(id, name, sort, is_default, create_time)
VALUES
(3, '三级分销员', 3, false, NOW());
```

### 4.2 配置升级规则

通过 EasyAdmin 后台或直接 SQL 配置升级规则：

#### 方式A：通过 EasyAdmin 后台

1. 访问 `/admin`
2. 进入「分销员等级升级规则」菜单
3. 点击「新建」
4. 填写表单：
   - **源等级**：一级分销员
   - **目标等级**：二级分销员
   - **升级条件**：`withdrawnAmount >= 5000`
   - **是否启用**：是
   - **备注说明**：已提现佣金达到5000元
5. 保存

#### 方式B：通过 SQL 直接插入

```sql
-- 1级→2级：已提现佣金>=5000元
INSERT INTO commission_upgrade_distributor_level_upgrade_rule
(id, source_level_id, target_level_id, upgrade_expression, is_enabled, description, create_time)
VALUES
(1, 1, 2, 'withdrawnAmount >= 5000', true, '一级升二级：已提现佣金达到5000元', NOW());

-- 2级→3级：已提现佣金>=10000元 且 邀请人数>=10
INSERT INTO commission_upgrade_distributor_level_upgrade_rule
(id, source_level_id, target_level_id, upgrade_expression, is_enabled, description, create_time)
VALUES
(2, 2, 3, 'withdrawnAmount >= 10000 and inviteeCount >= 10', true, '二级升三级：已提现佣金达到10000元且邀请人数>=10', NOW());
```

---

## 5. 测试验证

### 5.1 创建测试分销员

```php
use Tourze\OrderCommissionBundle\Entity\Distributor;
use Tourze\OrderCommissionBundle\Entity\DistributorLevel;
use Tourze\OrderCommissionBundle\Enum\DistributorStatus;

$level1 = $em->getRepository(DistributorLevel::class)->find(1); // 一级分销员
$user = $userRepository->find(123); // 假设用户已存在

$distributor = new Distributor();
$distributor->setUser($user);
$distributor->setLevel($level1);
$distributor->setStatus(DistributorStatus::Approved);

$em->persist($distributor);
$em->flush();
```

### 5.2 模拟提现流程

```php
use Tourze\OrderCommissionBundle\Entity\WithdrawLedger;
use Tourze\OrderCommissionBundle\Enum\WithdrawLedgerStatus;

// 第一笔提现：3000元（累计3000元，未达升级阈值）
$withdraw1 = new WithdrawLedger();
$withdraw1->setDistributor($distributor);
$withdraw1->setAmount(3000.00);
$withdraw1->setStatus(WithdrawLedgerStatus::Completed);
$withdraw1->setProcessedAt(new \DateTimeImmutable());

$em->persist($withdraw1);
$em->flush(); // 触发升级检查，但条件不满足（3000 < 5000）

// 第二笔提现：2100元（累计5100元，满足升级条件）
$withdraw2 = new WithdrawLedger();
$withdraw2->setDistributor($distributor);
$withdraw2->setAmount(2100.00);
$withdraw2->setStatus(WithdrawLedgerStatus::Completed);
$withdraw2->setProcessedAt(new \DateTimeImmutable());

$em->persist($withdraw2);
$em->flush(); // 触发升级检查，自动升级到2级
```

### 5.3 验证升级结果

```php
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeHistory;

// 刷新分销员实体
$em->refresh($distributor);

// 验证等级已更新
assert($distributor->getLevel()->getId() === 2, '分销员应升级到2级');

// 查询升级历史
$history = $em->getRepository(DistributorLevelUpgradeHistory::class)
    ->findOneBy(['distributor' => $distributor]);

assert($history !== null, '升级历史记录应存在');
assert($history->getPreviousLevel()->getId() === 1, '原等级应为1级');
assert($history->getNewLevel()->getId() === 2, '新等级应为2级');
assert($history->getSatisfiedExpression() === 'withdrawnAmount >= 5000', '满足的表达式应正确记录');

// 验证上下文快照
$context = $history->getContextSnapshot();
assert($context['withdrawnAmount'] >= 5100.0, '已提现金额应为5100元');

echo sprintf(
    "✅ 升级成功：%s → %s（于 %s）\n",
    $history->getPreviousLevel()->getName(),
    $history->getNewLevel()->getName(),
    $history->getUpgradeTime()->format('Y-m-d H:i:s')
);
```

---

## 6. 后台管理

### 6.1 查看升级历史

访问 `/admin` → 「分销员等级升级历史」菜单，可查看所有升级记录：

- 分销员信息
- 原等级 → 新等级
- 满足的条件表达式
- 上下文变量快照
- 升级时间

### 6.2 编辑升级规则

访问 `/admin` → 「分销员等级升级规则」菜单：

**简单模式编辑**：
1. 选择字段（如 `withdrawnAmount`）
2. 选择比较符（`>`、`>=`、`<`、`<=`、`==`）
3. 输入值（如 `5000`）
4. 系统自动生成表达式：`withdrawnAmount >= 5000`

**高级模式编辑**（推荐用于复杂条件）：
1. 切换到「高级模式」
2. 在 Monaco Editor 中编写表达式：
   ```
   withdrawnAmount >= 10000 and inviteeCount >= 10
   ```
3. 编辑器提供语法高亮和自动补全
4. 保存前自动验证表达式

---

## 7. 命令行工具

### 7.1 批量初始化分销员等级

用于系统上线前，基于历史提现数据初始化分销员等级：

```bash
php bin/console commission-upgrade:initialize-levels

# 输出示例：
# Initializing distributor levels...
# Progress: 100/500 [===>-----------]  20%
# ✅ Initialized 500 distributors, 120 upgraded
```

### 7.2 验证升级规则

检查所有升级规则的表达式语法：

```bash
php bin/console commission-upgrade:validate-rules

# 输出示例：
# Validating upgrade rules...
# ✅ Rule #1: 1级→2级, expression: "withdrawnAmount >= 5000"
# ❌ Rule #2: 2级→3级, expression: "withdrawnAmount / 0" (Division by zero)
# Found 1 invalid rule(s)
```

---

## 8. 可用变量参考

在升级条件表达式中，可以引用以下变量：

| 变量名 | 类型 | 说明 | 示例值 |
|--------|------|------|--------|
| `withdrawnAmount` | float | 已提现佣金总额（仅统计 Completed 状态） | 5100.50 |
| `inviteeCount` | int | 邀请人数（一级下线数量） | 12 |
| `orderCount` | int | 订单数（关联该分销员的订单总数） | 58 |
| `activeInviteeCount` | int | 活跃邀请人数（30天内有订单的下线） | 8 |

**表达式语法**：

- **比较运算符**：`>`, `<`, `>=`, `<=`, `==`, `!=`
- **逻辑运算符**：`and`, `or`, `not`
- **括号分组**：`(`, `)`

**示例表达式**：

```
# 简单条件
withdrawnAmount >= 5000

# 复杂条件（AND）
withdrawnAmount >= 10000 and inviteeCount >= 10

# 复杂条件（OR）
withdrawnAmount >= 50000 or (inviteeCount >= 50 and activeInviteeCount >= 30)

# 使用括号
(withdrawnAmount >= 5000 and inviteeCount >= 5) or orderCount >= 100
```

---

## 9. 常见问题排查

### 9.1 升级未触发

**症状**：分销员提现成功，但等级未自动升级。

**排查步骤**：

1. **检查升级规则是否配置**：
   ```sql
   SELECT * FROM commission_upgrade_distributor_level_upgrade_rule
   WHERE source_level_id = <当前等级ID>;
   ```
   如果无记录，说明未配置规则。

2. **检查规则是否启用**：
   ```sql
   SELECT is_enabled FROM commission_upgrade_distributor_level_upgrade_rule
   WHERE id = <规则ID>;
   ```
   如果 `is_enabled = false`，规则不生效。

3. **检查事件监听器是否注册**：
   ```bash
   php bin/console debug:event-dispatcher doctrine.orm.entity_listener
   ```
   应包含 `WithdrawLedgerStatusListener`。

4. **检查表达式是否满足**：
   ```php
   $context = $upgradeContextProvider->buildContext($distributor);
   var_dump($context); // 查看实际变量值

   $result = $upgradeExpressionEvaluator->evaluate('withdrawnAmount >= 5000', $context);
   var_dump($result); // 查看评估结果
   ```

5. **查看错误日志**：
   ```bash
   tail -f var/log/dev.log | grep "upgrade"
   ```
   搜索表达式执行失败的日志。

---

### 9.2 表达式验证失败

**症状**：后台保存升级规则时提示"表达式验证失败"。

**常见原因**：

1. **语法错误**：
   ```
   错误：withdrawnAmount >> 5000
   正确：withdrawnAmount >= 5000
   ```

2. **引用非法变量**：
   ```
   错误：teamPerformance >= 10000（未支持的变量）
   正确：withdrawnAmount >= 10000
   ```

3. **空表达式**：
   ```
   错误：""
   正确：withdrawnAmount >= 5000
   ```

**解决方案**：
- 参考「可用变量参考」章节确认变量名
- 使用高级模式的语法高亮检查拼写错误
- 使用 `commission-upgrade:validate-rules` 命令批量验证

---

### 9.3 升级历史记录缺失上下文快照

**症状**：升级历史记录的 `context_snapshot` 字段为空。

**可能原因**：
- `UpgradeContextProvider` 计算变量时返回 null
- 数据库中缺少必要数据（如无提现记录）

**排查**：
```php
$context = $upgradeContextProvider->buildContext($distributor);
var_dump($context); // 应包含所有4个变量

// 检查是否有null值
assert(isset($context['withdrawnAmount']), 'withdrawnAmount不能为空');
```

---

### 9.4 并发升级导致数据不一致

**症状**：多个提现同时完成，分销员等级出现异常（如跳级或重复升级历史）。

**解决方案**：

1. **启用乐观锁**（推荐）：
   在 `Distributor` 实体添加 `version` 字段（需修改 OrderCommissionBundle）：
   ```php
   #[ORM\Version]
   #[ORM\Column(type: 'integer')]
   private int $version = 0;
   ```

2. **使用悲观锁**（备选方案）：
   在升级检查时锁定分销员记录：
   ```php
   $distributor = $em->find(
       Distributor::class,
       $distributorId,
       \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE
   );
   ```

3. **异步执行**：
   使用 Symfony Messenger 将升级检查放入队列，避免并发冲突：
   ```bash
   php bin/console messenger:consume async
   ```

---

## 10. 下一步

### 10.1 生产环境部署

- [ ] 在测试环境验证完整升级流程
- [ ] 确保数据库表结构已创建
- [ ] 运行 `commission-upgrade:initialize-levels` 初始化历史分销员等级
- [ ] 配置升级规则
- [ ] 监控错误日志（关注表达式执行失败）

### 10.2 扩展功能

- 添加降级机制（佣金退款后降级）
- 支持多渠道通知（短信/邮件/推送）
- 升级规则 A/B 测试
- 实时统计报表（升级漏斗分析）

### 10.3 性能优化

- 引入 Redis 缓存上下文变量
- 异步执行升级检查（Symfony Messenger）
- 批量升级检查（定时任务）

---

## 11. 相关文档

- [Phase 0：技术研究](./research.md)
- [Phase 1：数据模型设计](./data-model.md)
- [服务契约：UpgradeExpressionEvaluator](./contracts/UpgradeExpressionEvaluator.md)
- [服务契约：UpgradeContextProvider](./contracts/UpgradeContextProvider.md)
- [服务契约：DistributorUpgradeService](./contracts/DistributorUpgradeService.md)
- [实体设计：DistributorLevelUpgradeRule](./contracts/DistributorLevelUpgradeRule-entity.md)

---

**文档完成日期**：2025-11-17
**反馈渠道**：提交 Issue 到项目仓库或联系技术负责人
