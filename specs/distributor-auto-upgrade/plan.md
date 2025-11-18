# 实施方案：分销员自动升级

**Feature**: `distributor-auto-upgrade` | **Scope**: `packages/commission-upgrade-bundle` | **日期**: 2025-11-17 | **Spec**: [spec.md](./spec.md)
**输入**: `packages/commission-upgrade-bundle/specs/distributor-auto-upgrade/spec.md`

> 说明：本文档由 `/speckit.plan` 生成，汇总Phase 0研究和Phase 1设计成果。

---

## 概述

实现基于 **Symfony Expression Language** 的分销员等级自动升级功能，支持灵活配置升级条件（如"已提现金额 >= 5000" 或 "已提现金额 >= 10000 AND 邀请人数 >= 10"），在分销员提现成功后自动检查并执行升级，记录完整升级历史。

**核心价值**：
- **自动化升级**：减少运营成本，无需人工审核升级申请
- **灵活配置**：支持简单和复杂组合条件，满足多样化运营策略
- **防刷单机制**：仅统计已提现成功的佣金（WithdrawLedger.Completed），规避恶意刷单
- **完整追溯**：记录升级历史（时间、条件表达式、上下文快照），支持审计和复盘

**技术选型**：
- **表达式引擎**：Symfony Expression Language（支持复杂条件组合）
- **后台管理**：EasyAdmin Bundle（提供表单 UI + Monaco Editor 高级模式）
- **事件驱动**：Doctrine Entity Listener（监听 WithdrawLedger.Completed 事件）
- **数据隔离**：在 CommissionUpgradeBundle 独立管理升级规则，不修改 OrderCommissionBundle

---

## 技术背景

**语言/版本**：PHP 8.2+
**主要依赖**：
- Symfony Framework Bundle ^7.3
- Symfony Expression Language ^7.3
- EasyAdmin Bundle ^4
- Doctrine ORM ^3.0
- OrderCommissionBundle（提供 Distributor、DistributorLevel、WithdrawLedger 实体）

**存储**：MySQL 8.0+（使用 Doctrine ORM）
**测试**：PHPUnit ^11.5 + PHPStan ^2.1
**目标平台**：Linux server（Symfony 应用）
**项目类型**：单项目（Symfony Bundle）
**性能目标**：
- 升级条件评估 < 10ms（单次）
- 支持 1000 并发提现触发升级检查（无性能下降）

**约束**：
- 不修改 OrderCommissionBundle 实体结构
- 升级操作必须原子性（事务保障）
- 表达式执行失败时不影响提现流程

**规模/场景**：
- 预计分销员数量：10,000+
- 每日提现量：1,000+
- 升级规则数量：< 10（等级数量）

---

## 宪章检查

> 阶段门：Phase 0 前必过，Phase 1 后复核。依据 `.specify/memory/constitution.md`。

- [x] **Monorepo 分层架构**：功能归属 `packages/commission-upgrade-bundle`，依赖 `packages/order-commission-bundle`，边界清晰
- [x] **Spec 驱动**：已具备完整的 spec.md、plan.md、research.md、data-model.md、contracts/*.md
- [x] **测试优先**：采用 TDD，单元测试（表达式验证、评估）+ 集成测试（完整升级流程）+ 契约测试（EasyAdmin 表单）
- [x] **质量门禁**：已规划 PHPStan level 8、PHP-CS-Fixer、PHPUnit 覆盖率
- [x] **可追溯性**：设计决策记录在 research.md，提交规范遵循 Conventional Commits

**宪章合规性**：✅ 无违例

---

## 项目结构

### 文档（本 Feature）

```text
packages/commission-upgrade-bundle/specs/distributor-auto-upgrade/
├── spec.md                                         # 功能规格说明书（用户场景、需求、成功标准）
├── plan.md                                         # 本文件：实施方案
├── research.md                                     # Phase 0：技术研究
├── data-model.md                                   # Phase 1：数据模型设计
├── quickstart.md                                   # Phase 1：快速入门指南
├── contracts/                                      # Phase 1：服务契约
│   ├── UpgradeExpressionEvaluator.md              # 表达式验证与评估服务
│   ├── UpgradeContextProvider.md                  # 上下文变量计算服务
│   ├── DistributorUpgradeService.md               # 升级核心逻辑服务
│   └── DistributorLevelUpgradeRule-entity.md      # 升级规则实体设计
├── checklists/                                     # 质量检查清单
│   └── requirements.md                            # 规格质量验证清单
└── tasks.md                                        # Phase 2：待执行任务清单（由 /speckit.tasks 生成）
```

### 代码结构（Scope 根下）

```text
packages/commission-upgrade-bundle/
├── src/
│   ├── Entity/                                     # 实体
│   │   ├── DistributorLevelUpgradeRule.php         # 升级规则实体
│   │   └── DistributorLevelUpgradeHistory.php      # 升级历史实体
│   ├── Repository/                                 # 仓储
│   │   ├── DistributorLevelUpgradeRuleRepository.php
│   │   └── DistributorLevelUpgradeHistoryRepository.php
│   ├── Service/                                    # 服务
│   │   ├── UpgradeExpressionEvaluator.php          # 表达式验证与评估
│   │   ├── UpgradeContextProvider.php              # 上下文变量计算
│   │   └── DistributorUpgradeService.php           # 升级核心逻辑
│   ├── EventListener/                              # 事件监听器
│   │   └── WithdrawLedgerStatusListener.php        # 监听提现成功事件
│   ├── Controller/Admin/                           # EasyAdmin 控制器
│   │   ├── DistributorLevelUpgradeRuleCrudController.php
│   │   └── DistributorLevelUpgradeHistoryCrudController.php
│   ├── Field/                                      # 自定义字段
│   │   └── ExpressionEditorField.php               # 表达式编辑器字段（表单 UI + Monaco Editor）
│   ├── Form/                                       # 表单类型
│   │   └── ExpressionEditorType.php                # 表达式编辑器表单类型
│   ├── Validator/Constraints/                      # 自定义验证
│   │   ├── ValidUpgradeExpression.php              # 表达式验证约束
│   │   └── ValidUpgradeExpressionValidator.php     # 验证器实现
│   ├── Command/                                    # 命令行工具
│   │   ├── InitializeDistributorLevelsCommand.php  # 批量初始化分销员等级
│   │   └── ValidateUpgradeRulesCommand.php         # 验证升级规则
│   └── CommissionUpgradeBundle.php                 # Bundle 入口
├── tests/
│   ├── Unit/                                       # 单元测试
│   │   ├── Service/
│   │   │   ├── UpgradeExpressionEvaluatorTest.php
│   │   │   ├── UpgradeContextProviderTest.php
│   │   │   └── DistributorUpgradeServiceTest.php
│   │   └── Entity/
│   │       └── DistributorLevelUpgradeRuleTest.php
│   ├── Integration/                                # 集成测试
│   │   ├── UpgradeFlowTest.php                     # 完整升级流程测试
│   │   └── EasyAdminRuleCrudTest.php               # 后台CRUD测试
│   ├── Fixtures/                                   # 测试数据
│   │   ├── distributor_levels.yaml
│   │   ├── upgrade_rules.yaml
│   │   └── distributors.yaml
│   └── bootstrap.php
├── composer.json
└── README.md
```

**结构决策**：
- **Entity**：`DistributorLevelUpgradeRule`（升级规则）和 `DistributorLevelUpgradeHistory`（升级历史）独立维护
- **Service**：三层服务架构（表达式 → 上下文 → 升级逻辑）
- **EventListener**：Doctrine Entity Listener 监听 `WithdrawLedger::postUpdate` 事件
- **Controller/Admin**：EasyAdmin 提供后台管理界面
- **Field/Form**：自定义表达式编辑器（简单模式 + 高级模式）

---

## 复杂度备案

**无宪章违例**，本功能遵循以下简化原则：

| 复杂度项 | 决策 | 简化理由 |
|---------|------|---------|
| 表达式引擎 | 使用 Symfony Expression Language | Symfony 官方组件，无需引入第三方规则引擎（如 RulesEngine、Drools） |
| 后台编辑器 | Monaco Editor（CDN 方式集成） | 避免复杂的前端构建流程，初期快速验证 |
| 异步执行 | 初期同步执行升级检查 | 评估性能影响 < 10ms，无需引入 Symfony Messenger 增加复杂度 |
| 缓存层 | 初期不引入 Redis 缓存 | 上下文变量计算开销可控，待性能测试后决定 |

---

## Phase 0：技术研究（已完成）

**研究成果**：详见 [research.md](./research.md)

**核心决策**：

1. **Symfony Expression Language 集成策略**
   - 使用 `ExpressionLanguage` 解析和执行表达式
   - 验证阶段：检查语法和变量白名单
   - 执行阶段：基于上下文变量返回布尔结果

2. **EasyAdmin 自定义字段类型**
   - 创建 `ExpressionEditorType` 表单类型
   - 简单模式：下拉选择变量 + 比较符 + 值
   - 高级模式：Monaco Editor 代码编辑器 + 语法高亮

3. **Monaco Editor 集成方案**
   - 初期使用 CDN（jsDelivr）快速集成
   - 生产环境迁移到本地托管（Webpack/Vite 打包）

4. **可用变量实现模式**
   - 硬编码变量清单：`withdrawnAmount`、`inviteeCount`、`orderCount`、`activeInviteeCount`
   - `UpgradeContextProvider` 负责计算所有变量值
   - Repository 扩展方法：`sumCompletedAmount()`、`countByParent()` 等

5. **事件监听器架构**
   - Doctrine Entity Listener 监听 `WithdrawLedger::postUpdate`
   - 检测 `status` 字段变更为 `Completed` 时触发升级检查
   - 使用乐观锁或悲观锁保障并发安全

---

## Phase 1：设计与实施计划（已完成）

### 1. 数据模型设计

**详见**：[data-model.md](./data-model.md)

**新建实体**：

1. **DistributorLevelUpgradeRule**（升级规则）
   - 字段：`source_level_id`、`target_level_id`、`upgrade_expression`、`is_enabled`、`description`
   - 唯一约束：`uniq_source_level`（每个源等级只有一条升级规则）
   - 外键约束：CASCADE（等级删除时同步删除规则）

2. **DistributorLevelUpgradeHistory**（升级历史）
   - 字段：`distributor_id`、`previous_level_id`、`new_level_id`、`satisfied_expression`、`context_snapshot`、`triggering_withdraw_ledger_id`、`upgrade_time`
   - 索引：`idx_distributor_upgrade_time`、`idx_upgrade_time`
   - 外键约束：CASCADE（分销员删除时同步删除历史）

### 2. 服务契约设计

**详见**：[contracts/](./contracts/)

**核心服务**：

1. **UpgradeExpressionEvaluator**（表达式验证与评估）
   - `validate(string $expression): void`：验证语法和变量合法性
   - `evaluate(string $expression, array $context): bool`：执行评估
   - `getAllowedVariables(): array<string>`：获取可用变量清单

2. **UpgradeContextProvider**（上下文变量计算）
   - `buildContext(Distributor $distributor): array`：构建完整上下文
   - `calculateWithdrawnAmount(Distributor): float`：计算已提现金额
   - `calculateInviteeCount(Distributor): int`：计算邀请人数
   - `calculateOrderCount(Distributor): int`：计算订单数
   - `calculateActiveInviteeCount(Distributor, int): int`：计算活跃邀请人数

3. **DistributorUpgradeService**（升级核心逻辑）
   - `checkAndUpgrade(Distributor, ?WithdrawLedger): ?DistributorLevelUpgradeHistory`：检查并执行升级
   - `findNextLevelRule(DistributorLevel): ?DistributorLevelUpgradeRule`：查找下一级别规则
   - `performUpgrade(...): DistributorLevelUpgradeHistory`：执行升级（原子事务）

### 3. 快速入门指南

**详见**：[quickstart.md](./quickstart.md)

**核心流程**：
1. 安装依赖（`symfony/expression-language`）
2. 执行数据库迁移
3. 配置升级规则（通过 EasyAdmin 或 SQL）
4. 模拟提现流程验证升级
5. 查看升级历史和后台管理

---

## Phase 2：任务分解（待执行）

**任务清单**：由 `/speckit.tasks` 生成，存储在 [tasks.md](./tasks.md)（待生成）

**预期任务分组**：

### P1 任务（核心功能）

1. **数据模型**
   - 创建 `DistributorLevelUpgradeRule` 实体
   - 创建 `DistributorLevelUpgradeHistory` 实体

2. **表达式服务实现**
   - 实现 `UpgradeExpressionEvaluator`
   - 编写单元测试（验证、评估、错误处理）
   - 实现 `UpgradeContextProvider`
   - 扩展 Repository 方法（`sumCompletedAmount` 等）

3. **升级核心逻辑**
   - 实现 `DistributorUpgradeService`
   - 实现 `WithdrawLedgerStatusListener`（事件监听器）
   - 编写集成测试（完整升级流程）

4. **后台管理界面**
   - 创建 `DistributorLevelUpgradeRuleCrudController`
   - 创建 `DistributorLevelUpgradeHistoryCrudController`
   - 实现表达式编辑器字段（简单模式 + 高级模式）
   - 集成 Monaco Editor（CDN）

### P2 任务（增强功能）

5. **表达式验证约束**
   - 实现 `ValidUpgradeExpression` 验证器
   - 在 EasyAdmin 保存时触发验证

6. **命令行工具**
   - 实现 `InitializeDistributorLevelsCommand`（批量初始化）
   - 实现 `ValidateUpgradeRulesCommand`（验证规则）

7. **错误处理与日志**
   - 表达式执行失败时记录日志
   - 升级规则缺失时记录警告
   - 并发冲突时重试机制

### P3 任务（可选优化）

8. **通知机制**
   - 发送站内信通知分销员升级成功
   - 管理员配置错误时发送通知

9. **性能优化**
   - 引入应用层缓存（上下文变量）
   - 批量查询优化（`buildContextBatch`）

10. **文档与培训**
    - 编写 README.md
    - 录制操作演示视频（EasyAdmin 配置流程）

---

## 测试策略

### 单元测试

**覆盖率目标**：>= 90%

**测试场景**：

1. **UpgradeExpressionEvaluator**
   - 验证简单条件：`withdrawnAmount >= 5000`
   - 验证复杂条件：`withdrawnAmount >= 10000 and inviteeCount >= 10`
   - 验证 OR 条件：`withdrawnAmount >= 50000 or inviteeCount >= 50`
   - 验证失败：语法错误、非法变量、除零错误

2. **UpgradeContextProvider**
   - 计算已提现金额（包含 Completed/排除 Failed）
   - 计算邀请人数（包含 Approved/排除 Pending）
   - 计算活跃邀请人数（30天内有订单）

3. **DistributorUpgradeService**
   - 满足条件时执行升级
   - 条件不满足时返回 null
   - 已达最高等级时返回 null
   - 并发场景下乐观锁机制

### 集成测试

**测试场景**：

1. **完整升级流程**
   - 创建分销员（1级，已提现4500元）
   - 配置升级规则（1级→2级：`withdrawnAmount >= 5000`）
   - 模拟提现成功（+600元）
   - 验证分销员等级为2级
   - 验证升级历史记录存在

2. **EasyAdmin 后台测试**
   - 保存有效升级规则
   - 保存无效表达式时显示错误
   - 查看升级历史列表

### 契约测试

**测试场景**：

1. **UpgradeExpressionEvaluator 契约**
   - `validate()` 输入/输出契约
   - `evaluate()` 输入/输出契约

2. **DistributorUpgradeService 契约**
   - `checkAndUpgrade()` 前置条件/后置条件

---

## 质量门禁

### 静态分析

- **PHPStan**：Level 8，无错误
- **PHP-CS-Fixer**：PSR-12 编码规范

**执行命令**：

```bash
./vendor/bin/phpstan analyse src --level=8
./vendor/bin/php-cs-fixer fix --dry-run --diff
```

### 单元测试

- **覆盖率**：>= 90%
- **失败率**：0%

**执行命令**：

```bash
./vendor/bin/phpunit --coverage-html coverage --testsuite=unit
```

### 集成测试

- **覆盖场景**：完整升级流程、EasyAdmin CRUD
- **失败率**：0%

**执行命令**：

```bash
./vendor/bin/phpunit --testsuite=integration
```

---

## 风险评估与缓解

| 风险 | 影响 | 概率 | 缓解措施 |
|------|------|------|---------|
| 表达式执行性能问题 | 高 | 中 | 1. 缓存编译后的表达式<br>2. 异步执行升级逻辑 |
| Monaco Editor CDN 不可用 | 中 | 低 | 迁移到本地托管资源 |
| 管理员配置错误表达式 | 高 | 中 | 1. 保存前严格验证<br>2. 运行时容错并通知管理员 |
| 并发升级导致数据不一致 | 高 | 中 | 使用乐观锁 + 原子事务 |
| 历史数据迁移耗时过长 | 中 | 低 | 分批执行 + 灰度发布 |
| 与 OrderCommissionBundle 版本不兼容 | 高 | 低 | 在 composer.json 明确版本约束 |

---

## 部署计划

### 测试环境部署

1. **准备工作**
   - [ ] 备份测试数据库
   - [ ] 安装依赖（`composer install`）
   - [ ] 注册 Bundle（`config/bundles.php`）

2. **初始化数据**
   - [ ] 配置升级规则（1级→2级、2级→3级）
   - [ ] 执行批量初始化：`php bin/console commission-upgrade:initialize-levels`

3. **功能验证**
   - [ ] 模拟提现成功并触发升级
   - [ ] 查看升级历史记录
   - [ ] 后台编辑升级规则

### 生产环境部署

1. **上线前检查**
   - [ ] 所有测试通过（单元 + 集成）
   - [ ] PHPStan Level 8 无错误
   - [ ] 代码审查完成
   - [ ] 性能测试通过（1000 并发提现）
   - [ ] 数据库表结构已创建

2. **灰度发布**
   - [ ] 仅对部分分销员启用升级功能（通过规则的 `is_enabled` 字段）
   - [ ] 监控错误日志和升级成功率
   - [ ] 收集用户反馈

3. **全量发布**
   - [ ] 启用所有升级规则
   - [ ] 持续监控性能指标
   - [ ] 准备回滚方案

---

## 监控与运维

### 关键指标

| 指标 | 目标值 | 监控方式 |
|------|--------|---------|
| 升级检查耗时 | < 10ms | 记录日志，定期统计 P95 耗时 |
| 升级成功率 | >= 99% | 统计升级历史记录数 / 触发次数 |
| 表达式执行失败率 | < 1% | 错误日志统计 |
| 并发冲突率 | < 0.1% | 乐观锁异常统计 |

### 日志监控

**关注日志关键词**：

```bash
# 表达式执行失败
grep "表达式执行失败" var/log/prod.log

# 升级规则缺失
grep "升级规则未配置" var/log/prod.log

# 并发冲突
grep "OptimisticLockException" var/log/prod.log
```

### 告警规则

- 表达式执行失败率 > 5%/小时 → 通知管理员检查配置
- 并发冲突率 > 1%/小时 → 通知技术负责人（可能需要引入异步执行）

---

## 下一步行动

### 立即执行

- [ ] 执行 `/speckit.tasks` 生成任务清单（tasks.md）
- [ ] 开始 Phase 2 实施：按 P1 → P2 → P3 顺序执行任务
- [ ] 每完成一个任务更新进度并执行质量门禁

### 后续优化（Phase 3+）

- [ ] 引入 Redis 缓存上下文变量
- [ ] 使用 Symfony Messenger 异步执行升级检查
- [ ] Monaco Editor 迁移到本地托管
- [ ] 支持升级规则 A/B 测试
- [ ] 添加降级机制（佣金退款后降级）

---

## 相关文档

- [功能规格说明书](./spec.md)
- [Phase 0：技术研究](./research.md)
- [Phase 1：数据模型设计](./data-model.md)
- [快速入门指南](./quickstart.md)
- [服务契约：UpgradeExpressionEvaluator](./contracts/UpgradeExpressionEvaluator.md)
- [服务契约：UpgradeContextProvider](./contracts/UpgradeContextProvider.md)
- [服务契约：DistributorUpgradeService](./contracts/DistributorUpgradeService.md)
- [实体设计：DistributorLevelUpgradeRule](./contracts/DistributorLevelUpgradeRule-entity.md)
- [规格质量检查清单](./checklists/requirements.md)

---

**文档完成日期**：2025-11-17
**文档状态**：✅ Phase 0 和 Phase 1 已完成，待执行 Phase 2 任务分解（`/speckit.tasks`）
**审核状态**：待审核
**下一阶段**：Phase 2 - 任务分解与实施
