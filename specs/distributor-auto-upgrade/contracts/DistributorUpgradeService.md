# 服务契约：DistributorUpgradeService

**服务名称**：`DistributorUpgradeService`
**命名空间**：`Tourze\CommissionUpgradeBundle\Service`
**职责**：分销员等级升级核心逻辑

---

## 1. 职责描述

DistributorUpgradeService 负责：

1. **升级检查**：判断分销员是否满足升级条件
2. **升级执行**：原子性地更新分销员等级并记录历史
3. **等级查询**：查找分销员的下一级别配置
4. **幂等性保障**：确保并发场景下升级操作的正确性

---

## 2. 公开接口

### 2.1 checkAndUpgrade(Distributor $distributor, ?WithdrawLedger $triggeringLedger = null): ?DistributorLevelUpgradeHistory

**用途**：检查分销员是否满足升级条件，如果满足则执行升级

**参数**：
- `$distributor` (Distributor): 待检查的分销员
- `$triggeringLedger` (WithdrawLedger|null): 触发升级检查的提现流水（可选，用于记录）

**返回值**：
- `DistributorLevelUpgradeHistory`: 升级成功时返回升级历史记录
- `null`: 不满足升级条件或已达最高等级

**副作用**：
- 更新分销员等级（如果满足条件）
- 创建升级历史记录
- 发送升级通知（异步）

**异常**：
- `\Doctrine\ORM\OptimisticLockException`: 并发冲突时抛出（调用方应重试）
- `\RuntimeException`: 表达式执行失败或其他运行时错误

**前置条件**：
- 分销员已持久化
- 分销员当前等级配置了下一级别的升级规则

**后置条件**：
- 如升级成功，分销员等级已更新且有对应的升级历史记录
- 如条件不满足，无任何数据变更

**示例**：

```php
$service = new DistributorUpgradeService($em, $contextProvider, $expressionEvaluator);
$distributor = $distributorRepository->find(123);

// 提现成功后触发升级检查
$history = $service->checkAndUpgrade($distributor, $withdrawLedger);

if ($history !== null) {
    echo sprintf('分销员从 %s 升级到 %s',
        $history->getPreviousLevel()->getName(),
        $history->getNewLevel()->getName()
    );
} else {
    echo '不满足升级条件';
}
```

---

### 2.2 findNextLevelRule(DistributorLevel $currentLevel): ?DistributorLevelUpgradeRule

**用途**：查找当前等级对应的升级规则（下一级别）

**参数**：
- `$currentLevel` (DistributorLevel): 当前等级

**返回值**：
- `DistributorLevelUpgradeRule`: 下一级别的升级规则
- `null`: 已达最高等级或未配置升级规则

**业务规则**：
- 基于 `DistributorLevel.sort` 字段查找下一级别
- 返回最接近的更高级别（`sort` 最小且大于当前等级）

**示例**：

```php
$currentLevel = $distributor->getLevel(); // sort = 1
$nextRule = $service->findNextLevelRule($currentLevel);

if ($nextRule !== null) {
    echo sprintf('下一级别：%s，升级条件：%s',
        $nextRule->getTargetLevel()->getName(),
        $nextRule->getUpgradeExpression()
    );
}
```

---

## 3. 内部方法（受保护）

### 3.1 performUpgrade(...)

**签名**：

```php
protected function performUpgrade(
    Distributor $distributor,
    DistributorLevel $previousLevel,
    DistributorLevel $newLevel,
    DistributorLevelUpgradeRule $rule,
    array $context,
    ?WithdrawLedger $triggeringLedger
): DistributorLevelUpgradeHistory
```

**用途**：执行升级操作（原子事务）

**操作步骤**：
1. 开启数据库事务
2. 更新分销员等级
3. 创建升级历史记录
4. 提交事务
5. 发送升级通知（事务外，异步）

**错误处理**：
- 任何异常发生时回滚事务
- 重新抛出异常供调用方处理

---

## 4. 依赖关系

### 4.1 外部依赖

- `Doctrine\ORM\EntityManagerInterface`（数据持久化）
- `Tourze\CommissionUpgradeBundle\Service\UpgradeContextProvider`（上下文变量）
- `Tourze\CommissionUpgradeBundle\Service\UpgradeExpressionEvaluator`（表达式评估）
- `Tourze\CommissionUpgradeBundle\Repository\DistributorLevelUpgradeRuleRepository`（升级规则查询）
- `Psr\Log\LoggerInterface`（可选，日志记录）
- `Symfony\Component\Messenger\MessageBusInterface`（可选，异步通知）

### 4.2 服务配置

```yaml
# config/services.yaml
services:
    Tourze\CommissionUpgradeBundle\Service\DistributorUpgradeService:
        arguments:
            - '@doctrine.orm.entity_manager'
            - '@Tourze\CommissionUpgradeBundle\Service\UpgradeContextProvider'
            - '@Tourze\CommissionUpgradeBundle\Service\UpgradeExpressionEvaluator'
            - '@Tourze\CommissionUpgradeBundle\Repository\DistributorLevelUpgradeRuleRepository'
            - '@logger'
            - '@messenger.default_bus' # 可选
```

---

## 5. 业务规则

### 5.1 升级逻辑流程

```
1. 获取当前等级
2. 查找下一级别的升级规则
   └─ 如果不存在 → 返回 null（已达最高等级）
3. 构建上下文变量
4. 评估升级条件表达式
   ├─ 如果执行失败 → 记录错误日志，返回 null
   └─ 如果返回 false → 返回 null（条件不满足）
5. 执行升级操作（事务）
   ├─ 更新分销员等级
   ├─ 创建升级历史记录
   └─ 提交事务
6. 发送升级通知（异步）
7. 返回升级历史记录
```

### 5.2 等级顺序性约束

**规则**：升级必须逐级进行，禁止跳级。

**实现**：`findNextLevelRule()` 始终返回紧邻的下一级别。

**示例**：
- 当前等级：1级（sort=1）
- 配置了 2级（sort=2）和 3级（sort=3）
- 即使满足 3级 条件，也只升级到 2级

### 5.3 并发安全

**问题**：多个提现同时完成，可能触发同一分销员的多次升级检查。

**解决方案**：使用乐观锁

```php
// 在 Distributor 实体添加版本字段（OrderCommissionBundle需修改）
#[ORM\Version]
#[ORM\Column(type: 'integer')]
private int $version = 0;

// 升级时捕获乐观锁异常
try {
    $this->entityManager->flush(); // 可能抛出 OptimisticLockException
} catch (OptimisticLockException $e) {
    // 调用方应重试
    throw $e;
}
```

**备选方案**（如 OrderCommissionBundle 不支持修改）：使用悲观锁

```php
$distributor = $this->entityManager->find(
    Distributor::class,
    $distributorId,
    \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE
);
```

---

## 6. 错误处理

### 6.1 表达式执行失败

**场景**：升级条件表达式存在语法错误或运行时错误

**处理**：
1. 捕获 `RuntimeException`
2. 记录错误日志（包含分销员ID、表达式内容、上下文变量）
3. 返回 `null`（条件不满足）
4. 异步通知管理员修复配置

**日志示例**：

```
[error] 升级条件评估失败
Distributor ID: 123
Current Level: 一级分销员 (sort=1)
Next Level: 二级分销员 (sort=2)
Expression: "withdrawnAmount / 0"
Context: {"withdrawnAmount": 5100, "inviteeCount": 12}
Error: Division by zero
```

### 6.2 升级规则缺失

**场景**：DistributorLevel 配置了下一级别，但未创建对应的 DistributorLevelUpgradeRule

**处理**：
1. `findNextLevelRule()` 返回 `null`
2. 记录警告日志
3. `checkAndUpgrade()` 返回 `null`

**日志示例**：

```
[warning] 升级规则未配置
Distributor ID: 123
Current Level: 一级分销员 (ID=1, sort=1)
Expected Next Level: 二级分销员 (ID=2, sort=2)
Missing Rule: DistributorLevelUpgradeRule for targetLevel=2
```

---

## 7. 性能考虑

### 7.1 查询优化

**问题**：高频调用 `checkAndUpgrade()` 导致大量数据库查询。

**优化方案**：

1. **预加载关联数据**：
   ```php
   $distributor = $distributorRepository->createQueryBuilder('d')
       ->addSelect('level')
       ->leftJoin('d.level', 'level')
       ->where('d.id = :id')
       ->setParameter('id', $distributorId)
       ->getQuery()
       ->getOneOrNullResult();
   ```

2. **缓存升级规则**：
   ```php
   private array $ruleCache = [];

   public function findNextLevelRule(DistributorLevel $currentLevel): ?DistributorLevelUpgradeRule
   {
       $cacheKey = $currentLevel->getId();
       if (isset($this->ruleCache[$cacheKey])) {
           return $this->ruleCache[$cacheKey];
       }

       $rule = $this->ruleRepository->findOneBy([
           'sourceLevel' => $currentLevel,
       ]);

       $this->ruleCache[$cacheKey] = $rule;
       return $rule;
   }
   ```

### 7.2 异步执行

**问题**：升级检查可能阻塞提现流程。

**优化方案**：使用 Symfony Messenger 异步执行

```php
// 在事件监听器中派发消息
$this->messageBus->dispatch(new CheckDistributorUpgrade($distributor->getId()));

// 消息处理器执行实际升级逻辑
#[AsMessageHandler]
class CheckDistributorUpgradeHandler
{
    public function __invoke(CheckDistributorUpgrade $message): void
    {
        $distributor = $this->em->find(Distributor::class, $message->distributorId);
        $this->upgradeService->checkAndUpgrade($distributor);
    }
}
```

**决策**：初期同步执行，性能测试后决定是否异步化。

---

## 8. 测试要求

### 8.1 单元测试覆盖

**测试场景**：

1. **checkAndUpgrade() 成功案例**：
   - 满足升级条件：返回升级历史记录
   - 分销员等级已更新
   - 升级历史记录包含完整信息

2. **checkAndUpgrade() 失败案例**：
   - 条件不满足：返回 null，无数据变更
   - 已达最高等级：返回 null
   - 表达式执行失败：返回 null，记录错误日志

3. **findNextLevelRule()**：
   - 有下一级别：返回对应规则
   - 已达最高等级：返回 null
   - 多个更高级别：返回最接近的一个

4. **performUpgrade() 事务保障**：
   - 升级成功：提交事务
   - 升级失败：回滚事务

5. **并发安全**：
   - 模拟并发升级：触发乐观锁异常
   - 验证数据一致性

### 8.2 集成测试

**场景**：完整升级流程

**测试步骤**：
1. 创建分销员（1级，已提现4500元）
2. 配置升级规则（1级→2级：`withdrawnAmount >= 5000`）
3. 模拟提现成功（+600元）
4. 触发升级检查
5. 验证分销员等级为2级
6. 验证升级历史记录存在

---

## 9. 变更历史

| 版本 | 日期 | 变更内容 |
|------|------|---------|
| 1.0.0 | 2025-11-17 | 初始版本 |

---

**文档完成日期**：2025-11-17
**审核状态**：待审核
