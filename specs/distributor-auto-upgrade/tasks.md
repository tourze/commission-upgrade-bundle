# 任务清单：分销员自动升级

**Feature**: `distributor-auto-upgrade` | **Scope**: `packages/commission-upgrade-bundle`
**输入**: `packages/commission-upgrade-bundle/specs/distributor-auto-upgrade/` 下的设计文档
**前置**: plan.md、spec.md、research.md、data-model.md、contracts/*.md

**测试策略**: 采用 TDD，测试任务在实现任务之前执行

**组织方式**: 任务按用户故事分组，确保每个故事可独立实现与验收

---

## 格式说明：`- [ ] [ID] [P?] [Story] 描述`

- **[P]**：可并行执行（不同文件、无未完成依赖）
- **[Story]**：所属用户故事（如 US1/US2/US3/US4）
- 描述中必须包含具体文件路径

**路径约定**：相对于 `packages/commission-upgrade-bundle/`

---

## Phase 1: 项目初始化

**目的**: 初始化项目结构与依赖

- [ ] T001 安装 Symfony Expression Language 依赖：`composer require symfony/expression-language:^7.3`
- [ ] T002 [P] 创建 Bundle 入口类：`src/CommissionUpgradeBundle.php`
- [ ] T003 [P] 创建 Bundle 配置文件：`src/DependencyInjection/CommissionUpgradeExtension.php`
- [ ] T004 [P] 配置 PHP-CS-Fixer：`.php-cs-fixer.dist.php`
- [ ] T005 [P] 配置 PHPStan：`phpstan.neon`（Level 8）

**检查点**: 依赖安装完成，Bundle 结构就绪

---

## Phase 2: 基础能力（阻塞项）

**目的**: 所有用户故事开始前必须完成的能力

**⚠️ 关键**: 未完成前禁止进入任意用户故事

### 数据模型

- [ ] T006 创建 DistributorLevelUpgradeRule 实体：`src/Entity/DistributorLevelUpgradeRule.php`
- [ ] T007 创建 DistributorLevelUpgradeHistory 实体：`src/Entity/DistributorLevelUpgradeHistory.php`
- [ ] T008 创建 DistributorLevelUpgradeRuleRepository：`src/Repository/DistributorLevelUpgradeRuleRepository.php`
- [ ] T009 创建 DistributorLevelUpgradeHistoryRepository：`src/Repository/DistributorLevelUpgradeHistoryRepository.php`

### 表达式服务

- [ ] T010 [P] 实现 UpgradeExpressionEvaluator 服务：`src/Service/UpgradeExpressionEvaluator.php`
- [ ] T011 [P] 实现 UpgradeContextProvider 服务：`src/Service/UpgradeContextProvider.php`

### Repository 扩展（OrderCommissionBundle）

- [ ] T012 在 WithdrawLedgerRepository 扩展 sumCompletedAmount 方法：`../order-commission-bundle/src/Repository/WithdrawLedgerRepository.php`
- [ ] T013 [P] 在 DistributorRepository 扩展 countByParent 方法：`../order-commission-bundle/src/Repository/DistributorRepository.php`
- [ ] T014 [P] 在 CommissionLedgerRepository 扩展 countOrdersByDistributor 方法：`../order-commission-bundle/src/Repository/CommissionLedgerRepository.php`

### 测试数据准备

- [ ] T015 [P] 创建 Fixture：分销员等级配置：`tests/Fixtures/distributor_levels.yaml`
- [ ] T016 [P] 创建 Fixture：升级规则示例：`tests/Fixtures/upgrade_rules.yaml`
- [ ] T017 [P] 创建 Fixture：测试分销员数据：`tests/Fixtures/distributors.yaml`

**检查点**: 基础数据模型、表达式服务、测试数据准备完成

---

## Phase 3: 用户故事 1 - 分销员达标自动升级（优先级：P1）🎯 MVP

**目标**: 分销员提现成功后，系统自动检查并执行升级，记录升级历史

**独立验证**: 创建测试分销员，模拟提现成功，验证自动升级并记录历史

### 测试（TDD - 先写测试）

- [ ] T018 [P] [US1] 编写 UpgradeExpressionEvaluator 单元测试：`tests/Unit/Service/UpgradeExpressionEvaluatorTest.php`
  - 测试简单条件验证：`withdrawnAmount >= 5000`
  - 测试复杂条件验证：`withdrawnAmount >= 10000 and inviteeCount >= 10`
  - 测试验证失败：语法错误、非法变量
  - 测试执行评估：条件满足/不满足、边界值
  - 测试执行失败：除零错误、类型不匹配
- [ ] T019 [P] [US1] 编写 UpgradeContextProvider 单元测试：`tests/Unit/Service/UpgradeContextProviderTest.php`
  - 测试计算已提现金额：包含 Completed、排除 Failed/Pending
  - 测试计算邀请人数：包含 Approved、排除 Pending/Rejected
  - 测试计算订单数：去重计数
  - 测试计算活跃邀请人数：30天内有订单
- [ ] T020 [US1] 编写 DistributorUpgradeService 单元测试：`tests/Unit/Service/DistributorUpgradeServiceTest.php`
  - 测试满足条件时执行升级
  - 测试条件不满足时返回 null
  - 测试已达最高等级时返回 null
  - 测试并发场景下乐观锁机制
  - 测试查找下一级别规则
  - 测试原子事务保障

### 实现

- [ ] T021 [US1] 实现 DistributorUpgradeService 核心逻辑：`src/Service/DistributorUpgradeService.php`
  - `checkAndUpgrade(Distributor, ?WithdrawLedger): ?DistributorLevelUpgradeHistory`
  - `findNextLevelRule(DistributorLevel): ?DistributorLevelUpgradeRule`
  - `performUpgrade(...)`: DistributorLevelUpgradeHistory`（原子事务）
- [ ] T022 [US1] 实现 WithdrawLedgerStatusListener 事件监听器：`src/EventListener/WithdrawLedgerStatusListener.php`
  - 监听 `WithdrawLedger::postUpdate` 事件
  - 检测 `status` 变更为 `Completed`
  - 触发 `DistributorUpgradeService::checkAndUpgrade()`
- [ ] T023 [US1] 配置事件监听器服务注册：`config/services.yaml`
- [ ] T024 [US1] 编写集成测试：完整升级流程：`tests/Integration/UpgradeFlowTest.php`
  - 创建分销员（1级，已提现4500元）
  - 配置升级规则（1级→2级：`withdrawnAmount >= 5000`）
  - 模拟提现成功（+600元）
  - 验证分销员等级为2级
  - 验证升级历史记录存在
  - 验证防刷单机制（仅统计 Completed 状态）
- [ ] T025 [US1] 运行所有测试并修复失败：`./vendor/bin/phpunit --testsuite=unit,integration --filter=US1`
- [ ] T026 [US1] 运行 PHPStan 检查：`./vendor/bin/phpstan analyse src --level=8`

**检查点**: 用户故事 1 可独立运行与测试，自动升级功能完成

---

## Phase 4: 用户故事 2 - 后台配置升级规则（优先级：P1）

**目标**: 管理员通过 EasyAdmin 后台配置升级条件表达式（简单模式 + 高级模式）

**独立验证**: 管理员登录后台，配置升级规则，保存后验证配置生效

### 测试（TDD）

- [ ] T027 [P] [US2] 编写表达式验证约束单元测试：`tests/Unit/Validator/Constraints/ValidUpgradeExpressionValidatorTest.php`
  - 测试有效表达式通过验证
  - 测试无效表达式触发验证错误
  - 测试非法变量触发验证错误
- [ ] T028 [US2] 编写 EasyAdmin CRUD 集成测试：`tests/Integration/EasyAdminRuleCrudTest.php`
  - 测试保存有效升级规则
  - 测试保存无效表达式时显示错误
  - 测试查看升级规则列表
  - 测试编辑现有规则

### 实现

- [ ] T029 [P] [US2] 实现表达式验证约束：`src/Validator/Constraints/ValidUpgradeExpression.php`
- [ ] T030 [P] [US2] 实现表达式验证约束验证器：`src/Validator/Constraints/ValidUpgradeExpressionValidator.php`
- [ ] T031 [US2] 创建 DistributorLevelUpgradeRuleCrudController：`src/Controller/Admin/DistributorLevelUpgradeRuleCrudController.php`
  - 配置字段：sourceLevel、targetLevel、upgradeExpression、isEnabled、description
  - 配置表达式字段使用 TextareaField（高级模式）
  - 配置保存前验证
- [ ] T032 [P] [US2] 创建 DistributorLevelUpgradeHistoryCrudController：`src/Controller/Admin/DistributorLevelUpgradeHistoryCrudController.php`
  - 配置字段：distributor、previousLevel、newLevel、satisfiedExpression、contextSnapshot、upgradeTime
  - 设置为只读（禁用 NEW/EDIT/DELETE 操作）
- [ ] T033 [US2] [可选] 实现自定义表达式编辑器字段类型：`src/Field/ExpressionEditorField.php`
  - 简单模式：下拉选择变量 + 比较符 + 值
  - 高级模式：Monaco Editor 代码编辑器
  - 切换按钮
- [ ] T034 [US2] [可选] 实现自定义表达式编辑器表单类型：`src/Form/ExpressionEditorType.php`
  - buildForm：定义简单模式字段和高级模式字段
  - buildView：传递可用变量清单
- [ ] T035 [US2] [可选] 集成 Monaco Editor：`templates/bundles/EasyAdminBundle/crud/form_theme.html.twig`
  - CDN 方式引入 Monaco Editor
  - 自定义语法高亮
  - 自动补全可用变量
- [ ] T036 [US2] 运行所有测试并修复失败：`./vendor/bin/phpunit --testsuite=integration --filter=US2`
- [ ] T037 [US2] 运行 PHPStan 检查：`./vendor/bin/phpstan analyse src --level=8`

**检查点**: 用户故事 1、2 均可独立运行，后台配置升级规则功能完成

---

## Phase 5: 用户故事 3 - 防刷单机制保障（优先级：P2）

**目标**: 系统仅统计已提现成功的佣金，排除待提现或提现失败的订单

**独立验证**: 创建测试分销员，生成不同状态的佣金记录，验证仅统计 Completed 状态

### 测试（TDD）

- [ ] T038 [P] [US3] 编写防刷单机制集成测试：`tests/Integration/AntiFraudMechanismTest.php`
  - 测试仅统计 Completed 状态佣金
  - 测试排除 Pending/Failed/Refunded 状态佣金
  - 测试异常订单快速生成大量待提现佣金无法升级
  - 测试提现失败后佣金不计入

### 实现

- [ ] T039 [US3] 增强 UpgradeContextProvider::calculateWithdrawnAmount 方法：`src/Service/UpgradeContextProvider.php`
  - 确保仅查询 `status=Completed` 的 WithdrawLedger 记录
  - 添加日志记录防刷单检查过程
- [ ] T040 [US3] 增强 WithdrawLedgerStatusListener 逻辑：`src/EventListener/WithdrawLedgerStatusListener.php`
  - 确保仅在 `status` 变更为 `Completed` 时触发升级检查
  - 忽略其他状态变更（Pending、Failed、Refunded）
- [ ] T041 [US3] 运行所有测试并修复失败：`./vendor/bin/phpunit --testsuite=integration --filter=US3`
- [ ] T042 [US3] 运行 PHPStan 检查：`./vendor/bin/phpstan analyse src --level=8`

**检查点**: 用户故事 1、2、3 均可独立运行，防刷单机制生效

---

## Phase 6: 用户故事 4 - 升级历史记录与通知（优先级：P3）

**目标**: 系统记录每次升级事件并向分销员发送升级通知

**独立验证**: 触发升级后查询历史记录，验证记录完整性；检查分销员是否收到通知

### 测试（TDD）

- [ ] T043 [P] [US4] 编写升级历史记录查询测试：`tests/Integration/UpgradeHistoryQueryTest.php`
  - 测试查询分销员升级历史
  - 测试历史记录包含完整信息（时间、等级、表达式、上下文快照）
  - 测试最高等级不记录升级事件
- [ ] T044 [P] [US4] 编写升级通知测试：`tests/Integration/UpgradeNotificationTest.php`
  - 测试升级成功后发送站内信
  - 测试最高等级不发送升级通知

### 实现

- [ ] T045 [US4] 在 DistributorUpgradeService 中集成通知服务：`src/Service/DistributorUpgradeService.php`
  - 注入 NotificationService（假设 OrderCommissionBundle 提供）
  - 在 `performUpgrade` 成功后发送站内信
  - 异步执行通知（事务外）
- [ ] T046 [US4] [可选] 创建 UpgradeNotificationService：`src/Service/UpgradeNotificationService.php`
  - `sendUpgradeNotification(Distributor, DistributorLevel): void`
  - 使用站内信组件发送通知
  - 记录通知日志
- [ ] T047 [US4] 增强 DistributorUpgradeService::performUpgrade 方法：`src/Service/DistributorUpgradeService.php`
  - 升级成功后调用通知服务
  - 捕获通知失败异常，记录日志但不影响升级事务
- [ ] T048 [US4] 运行所有测试并修复失败：`./vendor/bin/phpunit --testsuite=integration --filter=US4`
- [ ] T049 [US4] 运行 PHPStan 检查：`./vendor/bin/phpstan analyse src --level=8`

**检查点**: 所有用户故事均可独立运行，升级历史记录与通知功能完成

---

## Phase 7: 打磨与跨领域

### 命令行工具

- [ ] T050 [P] 实现 InitializeDistributorLevelsCommand：`src/Command/InitializeDistributorLevelsCommand.php`
  - 批量计算历史已提现佣金
  - 自动初始化分销员等级
  - 显示进度条
  - 记录成功/失败统计
- [ ] T051 [P] 实现 ValidateUpgradeRulesCommand：`src/Command/ValidateUpgradeRulesCommand.php`
  - 遍历所有升级规则
  - 验证表达式语法
  - 输出验证结果（成功/失败）
  - 标识无效规则

### 错误处理与日志

- [ ] T052 实现表达式执行失败错误处理：`src/Service/DistributorUpgradeService.php`
  - 捕获 RuntimeException
  - 记录详细错误日志（表达式、上下文、错误原因）
  - 返回 null（条件不满足）
  - 异步通知管理员修复配置
- [ ] T053 [P] 实现升级规则缺失警告日志：`src/Service/DistributorUpgradeService.php`
  - 在 `findNextLevelRule` 返回 null 时记录警告
  - 记录分销员ID、当前等级、期望下一级别
- [ ] T054 [P] 实现并发冲突重试机制：`src/Service/DistributorUpgradeService.php`
  - 捕获 OptimisticLockException
  - 重试1-2次
  - 重试失败后记录错误日志

### 文档与README

- [ ] T055 [P] 编写 Bundle README：`README.md`
  - 功能概述
  - 安装步骤
  - 配置说明
  - 使用示例
  - 命令行工具说明
  - 常见问题
- [ ] T056 [P] 编写 Bundle CHANGELOG：`CHANGELOG.md`
  - 版本 1.0.0：初始版本

### 质量门禁

- [ ] T057 运行完整测试套件：`./vendor/bin/phpunit --coverage-html coverage`
  - 确保覆盖率 >= 90%
  - 确保所有测试通过
- [ ] T058 运行 PHPStan 全量检查：`./vendor/bin/phpstan analyse src tests --level=8`
  - 确保无错误
- [ ] T059 运行 PHP-CS-Fixer 检查：`./vendor/bin/php-cs-fixer fix --dry-run --diff`
  - 确保代码符合 PSR-12
- [ ] T060 运行 quickstart.md 验证流程：手动执行快速入门指南所有步骤
  - 安装依赖
  - 配置升级规则
  - 模拟提现流程
  - 验证升级成功
  - 查看升级历史

### 部署准备

- [ ] T061 [P] 创建示例配置文件：`config/packages/commission_upgrade.yaml.dist`
- [ ] T062 [P] 编写部署文档：`docs/deployment.md`
  - 测试环境部署步骤
  - 生产环境部署步骤
  - 灰度发布策略
  - 回滚方案

---

## 依赖与执行顺序

### 串行依赖

```
Phase 1 → Phase 2 → Phase 3/4/5/6（用户故事并行） → Phase 7
```

### 用户故事依赖

- **用户故事 1（核心升级逻辑）**：无依赖，可先行实现
- **用户故事 2（后台配置）**：依赖 US1 完成（需要 DistributorLevelUpgradeRule 实体）
- **用户故事 3（防刷单）**：依赖 US1 完成（增强现有逻辑）
- **用户故事 4（历史与通知）**：依赖 US1 完成（增强现有逻辑）

### 并行执行建议

**Phase 2 完成后**：
- 先实现 US1（核心升级逻辑）
- 并行实现 US2（后台配置，依赖 US1 的实体）
- 并行实现 US3（防刷单，增强 US1 逻辑）
- 并行实现 US4（历史与通知，增强 US1 逻辑）

**Phase 7（打磨）**：
- T050-T056 可并行执行（命令行工具、错误处理、文档）
- T057-T060 必须串行执行（质量门禁）
- T061-T062 可并行执行（部署准备）

---

## 并行执行示例

### Phase 2（基础能力）

**可并行任务组**：
- 组A：T010（UpgradeExpressionEvaluator）
- 组B：T011（UpgradeContextProvider）
- 组C：T012（WithdrawLedgerRepository）
- 组D：T013（DistributorRepository）
- 组E：T014（CommissionLedgerRepository）
- 组F：T015-T017（Fixtures）

**串行依赖**：
- T006-T009 必须串行（数据模型依赖顺序创建）
- T010-T014 依赖 T006-T009 完成（服务依赖实体）

### Phase 3（用户故事 1）

**可并行任务组**：
- 组A：T018（UpgradeExpressionEvaluator 测试）
- 组B：T019（UpgradeContextProvider 测试）

**串行依赖**：
- T020 依赖 T018、T019 完成（DistributorUpgradeService 测试依赖其他服务测试）
- T021-T023 依赖 T020 完成（实现依赖测试先行）
- T024 依赖 T021-T023 完成（集成测试依赖实现）
- T025-T026 依赖 T024 完成（质量门禁依赖测试）

### Phase 4（用户故事 2）

**可并行任务组**：
- 组A：T027（验证约束测试）
- 组B：T029-T030（验证约束实现）
- 组C：T031-T032（CRUD 控制器）

**串行依赖**：
- T028 依赖 T027-T032 完成（集成测试依赖实现）
- T033-T035 可选，依赖 T031 完成（自定义字段增强）
- T036-T037 依赖 T028 完成（质量门禁依赖测试）

### Phase 7（打磨）

**可并行任务组**：
- 组A：T050（InitializeDistributorLevelsCommand）
- 组B：T051（ValidateUpgradeRulesCommand）
- 组C：T055-T056（文档）
- 组D：T061-T062（部署准备）

---

## MVP 范围建议

**最小可行产品（MVP）**包含：
- Phase 1：项目初始化
- Phase 2：基础能力
- Phase 3：用户故事 1（分销员达标自动升级）
- Phase 7 部分：T057-T060（质量门禁）

**MVP 交付价值**：
- 分销员提现成功后自动升级
- 升级历史完整记录
- 防刷单机制（仅统计已提现成功的佣金）

**后续增量**：
- Phase 4：后台配置升级规则（运营灵活性）
- Phase 5：防刷单机制增强（业务安全性）
- Phase 6：升级通知（用户体验）
- Phase 7 完整：命令行工具、文档、部署准备

---

## 任务统计

**总任务数**：62 个

**按用户故事分组**：
- Phase 1（初始化）：5 个任务
- Phase 2（基础能力）：12 个任务
- Phase 3（用户故事 1 - P1）：9 个任务
- Phase 4（用户故事 2 - P1）：11 个任务
- Phase 5（用户故事 3 - P2）：5 个任务
- Phase 6（用户故事 4 - P3）：7 个任务
- Phase 7（打磨）：13 个任务

**可并行任务数**：24 个（标记 [P]）

**预计工作量**（按标准人日）：
- MVP（Phase 1-3 + 质量门禁）：约 7-9 人日
- 完整功能（全部 Phase）：约 13-18 人日

---

## 验收标准

### Phase 3（用户故事 1）验收

- [ ] 分销员提现成功后自动升级
- [ ] 升级历史记录完整（时间、等级、表达式、上下文）
- [ ] 防刷单机制生效（仅统计 Completed 状态）
- [ ] 单元测试覆盖率 >= 90%
- [ ] PHPStan Level 8 无错误
- [ ] 集成测试通过（完整升级流程）

### Phase 4（用户故事 2）验收

- [ ] 管理员可通过 EasyAdmin 配置升级规则
- [ ] 保存前验证表达式语法
- [ ] 无效表达式显示错误提示
- [ ] 配置保存后立即生效
- [ ] 集成测试通过（后台 CRUD）

### Phase 5（用户故事 3）验收

- [ ] 仅统计 Completed 状态佣金
- [ ] 排除 Pending/Failed/Refunded 状态佣金
- [ ] 异常订单无法绕过防刷单机制
- [ ] 集成测试通过（防刷单机制）

### Phase 6（用户故事 4）验收

- [ ] 升级成功后发送站内信
- [ ] 升级历史可查询
- [ ] 最高等级不发送升级通知
- [ ] 集成测试通过（历史记录与通知）

### Phase 7（打磨）验收

- [ ] 命令行工具可用（初始化、验证规则）
- [ ] 错误处理与日志完善
- [ ] README 和文档完整
- [ ] 质量门禁全部通过（测试、PHPStan、PHP-CS-Fixer）
- [ ] quickstart.md 验证通过

---

**生成日期**：2025-11-17
**下一步**：执行 MVP 任务（Phase 1-3 + 质量门禁），完成核心升级功能
