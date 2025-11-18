# 服务契约：UpgradeExpressionEvaluator

**服务名称**：`UpgradeExpressionEvaluator`
**命名空间**：`Tourze\CommissionUpgradeBundle\Service`
**职责**：验证和执行升级条件表达式

---

## 1. 职责描述

UpgradeExpressionEvaluator 负责：

1. **表达式验证**：检查表达式语法正确性和变量合法性
2. **表达式执行**：基于上下文变量评估表达式并返回布尔结果
3. **变量清单管理**：维护可用变量的白名单

---

## 2. 公开接口

### 2.1 validate(string $expression): void

**用途**：验证表达式语法和变量合法性（保存前调用）

**参数**：
- `$expression` (string): 升级条件表达式

**返回值**：void

**异常**：
- `\InvalidArgumentException`: 表达式语法错误或引用了不支持的变量

**前置条件**：
- 表达式不能为空

**后置条件**：
- 验证通过则无任何副作用
- 验证失败抛出异常

**示例**：

```php
$evaluator = new UpgradeExpressionEvaluator();

// 成功案例
$evaluator->validate('withdrawnAmount > 5000'); // 无异常

// 失败案例：语法错误
$evaluator->validate('withdrawnAmount >> 5000');
// 抛出 InvalidArgumentException: 表达式语法错误: Unexpected token ">>"

// 失败案例：非法变量
$evaluator->validate('withdrawnAmount > 5000 and invalidVar > 10');
// 抛出 InvalidArgumentException: 表达式引用了不支持的变量: invalidVar
```

---

### 2.2 evaluate(string $expression, array $context): bool

**用途**：执行表达式评估，返回是否满足升级条件

**参数**：
- `$expression` (string): 升级条件表达式
- `$context` (array<string, mixed>): 上下文变量（如 `['withdrawnAmount' => 5100, 'inviteeCount' => 12]`）

**返回值**：bool（true = 满足升级条件，false = 不满足）

**异常**：
- `\RuntimeException`: 表达式执行失败（如除零、类型不匹配）

**前置条件**：
- 表达式已通过 `validate()` 验证
- `$context` 包含表达式引用的所有变量

**后置条件**：
- 返回布尔值表示条件是否满足
- 执行失败时抛出异常并记录日志

**示例**：

```php
$evaluator = new UpgradeExpressionEvaluator();
$context = [
    'withdrawnAmount' => 5100,
    'inviteeCount' => 12,
];

// 成功案例
$result = $evaluator->evaluate('withdrawnAmount > 5000', $context);
// 返回 true

$result = $evaluator->evaluate('withdrawnAmount > 5000 and inviteeCount > 10', $context);
// 返回 true

$result = $evaluator->evaluate('withdrawnAmount > 10000', $context);
// 返回 false

// 失败案例：表达式执行错误
$result = $evaluator->evaluate('withdrawnAmount / 0', $context);
// 抛出 RuntimeException: 表达式执行失败: Division by zero
```

---

### 2.3 getAllowedVariables(): array<string>

**用途**：获取可用变量清单（静态方法）

**参数**：无

**返回值**：array<string>（可用变量名称数组）

**示例**：

```php
$variables = UpgradeExpressionEvaluator::getAllowedVariables();
// 返回: ['withdrawnAmount', 'inviteeCount', 'orderCount', 'activeInviteeCount']
```

---

## 3. 依赖关系

### 3.1 外部依赖

- `Symfony\Component\ExpressionLanguage\ExpressionLanguage`（表达式解析和执行）
- `Psr\Log\LoggerInterface`（可选，记录运行时错误）

### 3.2 服务配置

```yaml
# config/services.yaml
services:
    Tourze\CommissionUpgradeBundle\Service\UpgradeExpressionEvaluator:
        arguments:
            - '@logger' # 可选
```

---

## 4. 配置

### 4.1 可用变量清单

**定义位置**：`UpgradeExpressionEvaluator::ALLOWED_VARIABLES`

**当前支持**：

| 变量名 | 类型 | 说明 |
|--------|------|------|
| `withdrawnAmount` | float | 已提现佣金总额 |
| `inviteeCount` | int | 邀请人数 |
| `orderCount` | int | 订单数 |
| `activeInviteeCount` | int | 活跃邀请人数（30天内有订单） |

**扩展方式**：在 `ALLOWED_VARIABLES` 数组中添加新变量名，并在 `UpgradeContextProvider` 中实现计算逻辑。

---

## 5. 错误处理

### 5.1 验证错误

**场景**：表达式语法错误或引用非法变量

**处理**：抛出 `InvalidArgumentException`，错误消息包含具体原因

**示例**：

```
InvalidArgumentException: 表达式语法错误: Unexpected token ">>" at position 18
InvalidArgumentException: 表达式引用了不支持的变量: teamPerformance, repeatPurchaseRate
```

### 5.2 运行时错误

**场景**：表达式执行失败（如除零、类型不匹配、缺少变量）

**处理**：
1. 记录错误日志（包含表达式内容、上下文变量、错误堆栈）
2. 抛出 `RuntimeException`
3. 调用方（`DistributorUpgradeService`）捕获异常并通知管理员

**日志示例**：

```
[error] 表达式执行失败
Expression: "withdrawnAmount / 0"
Context: {"withdrawnAmount": 5100, "inviteeCount": 12}
Error: Division by zero
Trace: ...
```

---

## 6. 性能考虑

### 6.1 表达式缓存

**问题**：高频执行相同表达式，重复解析浪费性能。

**优化方案**（可选）：

```php
private array $compiledCache = [];

public function evaluate(string $expression, array $context): bool
{
    if (!isset($this->compiledCache[$expression])) {
        $this->compiledCache[$expression] = $this->expressionLanguage->compile(
            $expression,
            self::ALLOWED_VARIABLES
        );
    }

    $compiledExpression = $this->compiledCache[$expression];
    // 执行编译后的表达式
    return (bool) eval('return ' . $compiledExpression . ';');
}
```

**决策**：初期不实现缓存，待性能测试后决定。

---

## 7. 测试要求

### 7.1 单元测试覆盖

**测试场景**：

1. **validate() 成功案例**：
   - 简单条件：`withdrawnAmount > 5000`
   - 复杂条件：`withdrawnAmount > 5000 and inviteeCount > 10`
   - OR 条件：`withdrawnAmount > 10000 or inviteeCount > 20`

2. **validate() 失败案例**：
   - 语法错误：`withdrawnAmount >> 5000`
   - 非法变量：`invalidVar > 10`
   - 空表达式：`""`

3. **evaluate() 成功案例**：
   - 条件满足：返回 true
   - 条件不满足：返回 false
   - 边界值测试：`withdrawnAmount >= 5000`，上下文为 `5000` 时返回 true

4. **evaluate() 失败案例**：
   - 除零错误
   - 类型不匹配（如字符串与数字比较）
   - 缺少上下文变量

5. **getAllowedVariables()**：
   - 返回包含4个变量名的数组

### 7.2 集成测试

**场景**：在 EasyAdmin 后台保存升级条件时触发验证

**验证点**：
- 保存无效表达式时显示错误提示
- 保存有效表达式后成功保存到 `criteriaJson`

---

## 8. 变更历史

| 版本 | 日期 | 变更内容 |
|------|------|---------|
| 1.0.0 | 2025-11-17 | 初始版本：支持4个基础变量 |

---

## 9. 未来扩展

### 9.1 自定义函数支持

**需求**：支持在表达式中使用自定义函数（如 `max()`、`min()`）

**实现方式**：

```php
$this->expressionLanguage->register('max',
    function ($a, $b) {
        return sprintf('max(%s, %s)', $a, $b);
    },
    function ($arguments, $a, $b) {
        return max($a, $b);
    }
);

// 表达式示例：max(withdrawnAmount, 5000) > 10000
```

### 9.2 表达式版本管理

**需求**：管理员修改升级条件后，需追踪历史版本

**实现方式**：在 `DistributorLevel` 添加 `criteriaJsonHistory` 字段记录历史版本

---

**文档完成日期**：2025-11-17
**审核状态**：待审核
