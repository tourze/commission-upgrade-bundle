# 服务契约：ManualUpgradeCrudController

**Feature**: `manual-upgrade-check`
**日期**: 2025-11-19
**契约类型**: Controller Action 定义
**实现语言**: PHP (Symfony Controller)

## 概述

`ManualUpgradeCrudController` 是 EasyAdmin 扩展控制器，提供后台手动升级管理功能。继承 `AbstractCrudController`，实现三个核心 Action：
1. **检测升级条件**（`checkAction`）
2. **展示检测结果**（`resultAction`）
3. **执行升级**（`upgradeAction`）

---

## Action 1: checkAction（检测升级条件）

### 接口定义

**路由**: `POST /admin/manual-upgrade/check`
**权限**: `ROLE_UPGRADE_OPERATOR`（需要通过 Voter 验证）

### 输入

**请求参数**（Form Data）：

```php
[
    'distributor_id' => int // 必填，分销员ID
]
```

**验证规则**：
- `distributor_id` 必填
- 必须为正整数
- 分销员必须存在于数据库中

### 输出

**成功响应**（重定向到结果页面）：

```
HTTP 302 Found
Location: /admin/manual-upgrade/result?distributor_id=12345
```

**错误响应**（表单验证失败）：

```
HTTP 200 OK
Content-Type: text/html

[渲染表单页面，显示错误提示]
```

### 业务逻辑

1. **接收并验证输入**：
   ```php
   $form = $this->createForm(ManualUpgradeCheckType::class);
   $form->handleRequest($request);

   if (!$form->isValid()) {
       // 渲染表单，显示验证错误
       return $this->render('check_form.html.twig', ['form' => $form]);
   }
   ```

2. **查询分销员**：
   ```php
   $distributorId = $form->get('distributor_id')->getData();
   $distributor = $distributorRepository->find($distributorId);

   if (null === $distributor) {
       $this->addFlash('error', '分销员不存在');
       return $this->redirectToRoute('manual_upgrade_check');
   }
   ```

3. **调用升级服务检测条件**：
   ```php
   $currentLevel = $distributor->getLevel();
   $upgradeRule = $distributorUpgradeService->findNextLevelRule($currentLevel);

   if (null === $upgradeRule) {
       $result = new ManualUpgradeCheckResult(
           distributor: $distributor,
           currentLevel: $currentLevel,
           canUpgrade: false,
           failureReason: '已达最高等级或未配置升级规则'
       );
   } else {
       $context = $contextProvider->buildContext($distributor);
       $satisfied = $expressionEvaluator->evaluate(
           $upgradeRule->getUpgradeExpression(),
           $context
       );

       $result = new ManualUpgradeCheckResult(
           distributor: $distributor,
           currentLevel: $currentLevel,
           canUpgrade: $satisfied,
           targetLevel: $satisfied ? $upgradeRule->getTargetLevel() : null,
           upgradeRule: $satisfied ? $upgradeRule : null,
           context: $context,
           failureReason: !$satisfied ? '不满足升级条件' : null
       );
   }
   ```

4. **存储检测结果到 Session**：
   ```php
   $session->set(
       'manual_upgrade_check_result_' . $distributorId,
       $result->toArray()
   );
   ```

5. **重定向到结果页面**：
   ```php
   return $this->redirectToRoute('manual_upgrade_result', [
       'distributor_id' => $distributorId
   ]);
   ```

### 错误场景

| 场景 | HTTP状态 | 响应 |
|------|---------|------|
| 用户ID格式错误 | 200 | 表单验证错误提示 |
| 用户不存在 | 302 | Flash消息"分销员不存在"，重定向回表单 |
| 表达式评估失败 | 302 | 记录日志，视为条件不满足，正常展示结果 |
| 权限不足 | 403 | Symfony 标准 AccessDeniedHttpException |

### 测试用例

```php
public function testCheckAction_ValidUser_RedirectsToResult(): void
{
    $client = static::createClient();
    $this->loginAs($client, 'operator@example.com', ['ROLE_UPGRADE_OPERATOR']);

    $crawler = $client->request('GET', '/admin/manual-upgrade/check');
    $form = $crawler->selectButton('检测升级条件')->form([
        'manual_upgrade_check[distributor_id]' => 12345,
    ]);

    $client->submit($form);

    $this->assertResponseRedirects('/admin/manual-upgrade/result?distributor_id=12345');
}

public function testCheckAction_InvalidUser_ShowsError(): void
{
    $client = static::createClient();
    $this->loginAs($client, 'operator@example.com', ['ROLE_UPGRADE_OPERATOR']);

    $crawler = $client->request('GET', '/admin/manual-upgrade/check');
    $form = $crawler->selectButton('检测升级条件')->form([
        'manual_upgrade_check[distributor_id]' => 99999, // 不存在
    ]);

    $client->submit($form);

    $this->assertResponseRedirects();
    $this->assertSelectorTextContains('.alert-error', '分销员不存在');
}

public function testCheckAction_WithoutPermission_DeniesAccess(): void
{
    $client = static::createClient();
    $this->loginAs($client, 'viewer@example.com', ['ROLE_USER']);

    $client->request('GET', '/admin/manual-upgrade/check');

    $this->assertResponseStatusCodeSame(403);
}
```

---

## Action 2: resultAction（展示检测结果）

### 接口定义

**路由**: `GET /admin/manual-upgrade/result?distributor_id={id}`
**权限**: `ROLE_UPGRADE_OPERATOR`

### 输入

**Query 参数**：

```php
[
    'distributor_id' => int // 必填，分销员ID
]
```

### 输出

**成功响应**（HTML 页面）：

```
HTTP 200 OK
Content-Type: text/html

[渲染结果页面，显示检测结果和操作按钮]
```

**模板变量**：

```php
[
    'distributor' => Distributor,
    'current_level' => DistributorLevel,
    'can_upgrade' => bool,
    'target_level' => ?DistributorLevel,
    'upgrade_expression' => ?string,
    'context' => array,
    'failure_reason' => ?string,
    'check_time' => \DateTimeImmutable,
]
```

### 业务逻辑

1. **从 Session 读取检测结果**：
   ```php
   $distributorId = $request->query->getInt('distributor_id');
   $sessionKey = 'manual_upgrade_check_result_' . $distributorId;
   $resultData = $session->get($sessionKey);

   if (null === $resultData) {
       $this->addFlash('error', '检测结果已过期，请重新检测');
       return $this->redirectToRoute('manual_upgrade_check');
   }
   ```

2. **重新加载实体**（防止 Session 序列化实体）：
   ```php
   $distributor = $distributorRepository->find($resultData['distributor_id']);
   $currentLevel = $distributorLevelRepository->find($resultData['current_level_id']);
   $targetLevel = $resultData['target_level_id']
       ? $distributorLevelRepository->find($resultData['target_level_id'])
       : null;
   ```

3. **渲染结果页面**：
   ```php
   return $this->render('manual_upgrade/result.html.twig', [
       'distributor' => $distributor,
       'current_level' => $currentLevel,
       'can_upgrade' => $resultData['can_upgrade'],
       'target_level' => $targetLevel,
       'upgrade_expression' => $resultData['upgrade_expression'] ?? null,
       'context' => $resultData['context'],
       'failure_reason' => $resultData['failure_reason'] ?? null,
       'check_time' => new \DateTimeImmutable($resultData['check_time']),
   ]);
   ```

### UI 展示逻辑

**满足条件时**：
- 显示绿色成功提示
- 展示当前等级 → 目标等级
- 显示升级条件表达式
- 显示上下文变量详情
- 显示"执行升级"按钮（绿色，醒目）

**不满足条件时**：
- 显示黄色警告提示
- 展示当前等级
- 显示失败原因
- 显示上下文变量详情（帮助运营人员了解差距）
- 隐藏"执行升级"按钮

### 测试用例

```php
public function testResultAction_WithValidSession_ShowsResult(): void
{
    $client = static::createClient();
    $this->loginAs($client, 'operator@example.com', ['ROLE_UPGRADE_OPERATOR']);

    // 模拟 Session 数据
    $session = $client->getContainer()->get('session');
    $session->set('manual_upgrade_check_result_12345', [
        'distributor_id' => 12345,
        'current_level_id' => 1,
        'can_upgrade' => true,
        'target_level_id' => 2,
        'upgrade_expression' => 'total_amount >= 1000',
        'context' => ['total_amount' => 1200],
        'check_time' => '2025-11-19 14:30:00',
    ]);

    $crawler = $client->request('GET', '/admin/manual-upgrade/result?distributor_id=12345');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains('.alert-success', '满足升级条件');
    $this->assertSelectorExists('button:contains("执行升级")');
}

public function testResultAction_SessionExpired_RedirectsToCheck(): void
{
    $client = static::createClient();
    $this->loginAs($client, 'operator@example.com', ['ROLE_UPGRADE_OPERATOR']);

    $client->request('GET', '/admin/manual-upgrade/result?distributor_id=12345');

    $this->assertResponseRedirects('/admin/manual-upgrade/check');
    $this->assertSelectorTextContains('.alert-error', '检测结果已过期');
}
```

---

## Action 3: upgradeAction（执行升级）

### 接口定义

**路由**: `POST /admin/manual-upgrade/upgrade`
**权限**: `ROLE_UPGRADE_OPERATOR`

### 输入

**请求参数**（Form Data）：

```php
[
    'distributor_id' => int, // 必填，分销员ID
    '_token' => string       // CSRF Token
]
```

### 输出

**成功响应**（HTML 页面，显示升级结果）：

```
HTTP 200 OK
Content-Type: text/html

[渲染成功页面，显示升级详情和新的历史记录链接]
```

**错误响应**（业务错误）：

```
HTTP 200 OK
Content-Type: text/html

[渲染错误页面，显示具体失败原因]
```

### 业务逻辑

1. **验证 CSRF Token**：
   ```php
   if (!$this->isCsrfTokenValid('manual_upgrade', $request->request->get('_token'))) {
       throw new InvalidCsrfTokenException();
   }
   ```

2. **从 Session 读取检测结果**：
   ```php
   $distributorId = $request->request->getInt('distributor_id');
   $sessionKey = 'manual_upgrade_check_result_' . $distributorId;
   $resultData = $session->get($sessionKey);

   if (null === $resultData) {
       $this->addFlash('error', '检测结果已过期，请重新检测');
       return $this->redirectToRoute('manual_upgrade_check');
   }

   if (!$resultData['can_upgrade']) {
       $this->addFlash('error', '该用户不满足升级条件，无法执行升级');
       return $this->redirectToRoute('manual_upgrade_result', ['distributor_id' => $distributorId]);
   }
   ```

3. **加载分销员并执行升级**：
   ```php
   $distributor = $distributorRepository->find($distributorId);
   if (null === $distributor) {
       $this->addFlash('error', '分销员不存在');
       return $this->redirectToRoute('manual_upgrade_check');
   }

   try {
       // 重新调用 checkAndUpgrade 确保条件仍然满足（防止数据变化）
       $history = $distributorUpgradeService->checkAndUpgrade($distributor);

       if (null === $history) {
           $this->addFlash('warning', '升级条件已不满足，请重新检测');
           $session->remove($sessionKey);
           return $this->redirectToRoute('manual_upgrade_check');
       }

       // 标记为手动升级并记录操作人
       $history->setTriggerType('manual');
       $history->setOperator($this->getUser());
       $entityManager->flush();

       // 清除 Session
       $session->remove($sessionKey);

       $this->addFlash('success', sprintf(
           '升级成功！%s (#%d) 从 %s 升级到 %s',
           $distributor->getId(),
           $history->getPreviousLevel()->getName(),
           $history->getNewLevel()->getName()
       ));

       return $this->render('manual_upgrade/success.html.twig', [
           'distributor' => $distributor,
           'history' => $history,
       ]);

   } catch (OptimisticLockException $e) {
       $this->addFlash('error', '升级失败：检测到并发冲突，请稍后重试');
       return $this->redirectToRoute('manual_upgrade_result', ['distributor_id' => $distributorId]);
   }
   ```

### 错误场景

| 场景 | 处理方式 |
|------|---------|
| Session 过期 | Flash 消息"检测结果已过期"，重定向到检测页面 |
| 检测结果为"不可升级" | Flash 消息"不满足升级条件"，重定向到结果页面 |
| 条件在执行时已不满足 | Flash 消息"升级条件已不满足，请重新检测"，清除Session，重定向到检测页面 |
| 并发冲突（OptimisticLockException） | Flash 消息"检测到并发冲突，请稍后重试"，重定向到结果页面 |
| 用户已是最高等级 | 视为条件不满足，返回 warning |
| CSRF Token 无效 | 抛出 InvalidCsrfTokenException（Symfony 标准处理） |

### 测试用例

```php
public function testUpgradeAction_ValidRequest_UpgradesSuccessfully(): void
{
    $client = static::createClient();
    $this->loginAs($client, 'operator@example.com', ['ROLE_UPGRADE_OPERATOR']);

    // 模拟 Session 数据
    $session = $client->getContainer()->get('session');
    $session->set('manual_upgrade_check_result_12345', [
        'distributor_id' => 12345,
        'can_upgrade' => true,
        'current_level_id' => 1,
        'target_level_id' => 2,
        'check_time' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
    ]);

    $client->request('POST', '/admin/manual-upgrade/upgrade', [
        'distributor_id' => 12345,
        '_token' => $this->generateCsrfToken('manual_upgrade'),
    ]);

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains('.alert-success', '升级成功');

    // 验证历史记录
    $history = $this->getContainer()->get('doctrine')->getRepository(DistributorLevelUpgradeHistory::class)
        ->findOneBy(['distributor' => 12345], ['upgradeTime' => 'DESC']);

    $this->assertSame('manual', $history->getTriggerType());
    $this->assertNotNull($history->getOperator());
}

public function testUpgradeAction_SessionExpired_RedirectsToCheck(): void
{
    $client = static::createClient();
    $this->loginAs($client, 'operator@example.com', ['ROLE_UPGRADE_OPERATOR']);

    $client->request('POST', '/admin/manual-upgrade/upgrade', [
        'distributor_id' => 12345,
        '_token' => $this->generateCsrfToken('manual_upgrade'),
    ]);

    $this->assertResponseRedirects('/admin/manual-upgrade/check');
}

public function testUpgradeAction_OptimisticLockConflict_ShowsErrorMessage(): void
{
    // 模拟并发冲突场景...
    $this->markTestIncomplete('需要 mock EntityManager 模拟 OptimisticLockException');
}
```

---

## 权限控制

使用 `ManualUpgradeVoter` 实现细粒度权限控制：

```php
// 在 Controller 中使用
$this->denyAccessUnlessGranted('MANUAL_UPGRADE', $distributor);
```

**Voter 规则**：
- 仅 `ROLE_UPGRADE_OPERATOR` 角色可以执行手动升级
- 可扩展为更细粒度的控制（如限制操作特定等级的用户）

---

## 依赖注入

Controller 构造器注入以下服务：

```php
public function __construct(
    private DistributorUpgradeService $distributorUpgradeService,
    private UpgradeContextProvider $contextProvider,
    private UpgradeExpressionEvaluator $expressionEvaluator,
    private DistributorRepository $distributorRepository,
    private DistributorLevelRepository $distributorLevelRepository,
    private EntityManagerInterface $entityManager,
) {}
```

---

## 相关文档

- [数据模型](../data-model.md)
- [表单契约](./manual-upgrade-form.md)
- [Plan 文档](../plan.md)
