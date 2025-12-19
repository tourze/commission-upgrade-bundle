# 实施方案：后台手动升级检测与执行

**Feature**: `manual-upgrade-check` | **Scope**: `packages/commission-upgrade-bundle` | **日期**: 2025-11-19 | **Spec**: [spec.md](./spec.md)
**输入**: `packages/commission-upgrade-bundle/specs/manual-upgrade-check/spec.md`

> 说明：本文档由 `/speckit.plan` 生成，基于 research.md 的技术研究结果

## 概述

为现有的自动升级系统添加后台手动升级管理功能，允许运营人员输入用户ID、检测升级条件、并手动触发升级操作。本功能复用现有的 `DistributorUpgradeService` 核心逻辑，通过 EasyAdmin 扩展后台管理界面，确保手动升级与自动升级遵循完全相同的业务规则。

**核心价值**:
- 为运营人员提供主动干预能力，处理自动升级未覆盖的边界场景
- 通过复用现有服务保证业务规则一致性
- 通过操作日志满足审计和合规要求

## 技术背景

**语言/版本**：PHP 8.2+
**主要依赖**：
- Symfony 7.3 (Framework Bundle, Form, Security, Validator)
- Doctrine ORM 3.0 (实体管理、乐观锁)
- EasyAdmin Bundle 4 (后台管理界面)
- 现有的 `DistributorUpgradeService`（升级核心逻辑）

**存储**：关系型数据库（通过 Doctrine ORM，表结构基于 Entity Attributes）
**测试**：PHPUnit 11.5 + Symfony Test Helpers（KernelTestCase, WebTestCase）
**目标平台**：Web后台管理系统（运行在 Linux/macOS 服务器）
**项目类型**：Symfony Bundle（可复用的后端模块）

**性能目标**：
- 单次检测响应时间 < 3秒（SC-003）
- 检测结果响应时间 < 5秒（SC-001）
- 支持 5个并发运营人员无性能下降（SC-004）

**约束**：
- 必须复用现有升级规则引擎，不得重复实现升级逻辑
- 必须通过 EasyAdmin 集成，保持后台UI一致性
- 必须使用 Doctrine 乐观锁防止并发冲突
- 操作日志必须持久化存储至少6个月（SC-005）

**规模/场景**：
- 预计运营人员数量：5-10人
- 预计日均手动升级操作：10-50次
- 分销员总量：10,000+ （现有业务规模）

## 宪章检查

> 阶段门：Phase 0 前必过，Phase 1 后复核。依据 `.specify/memory/constitution.md`。

- [x] **Monorepo 分层架构**：功能归属 `packages/commission-upgrade-bundle`（现有Bundle），依赖边界清晰（仅依赖 order-commission-bundle 和 Symfony/Doctrine 标准库）
- [x] **Spec 驱动**：已具备完整的 spec.md、plan.md（本文件）、research.md
- [x] **测试优先**：TDD策略明确 - 先写Controller/Service单元测试，再写集成测试验证完整流程
- [x] **质量门禁**：使用仓库根目录 `phpstan.neon`（Level 8），PHPUnit测试，PHP-CS-Fixer格式化
- [x] **可追溯性**：所有决策记录在 research.md，提交消息遵循 Conventional Commits
- [x] **用户管理统一接口**：本功能不涉及用户实体创建，仅查询现有 Distributor 实体（符合宪章第VIII条）
- [x] **数据库 Schema 管理**：遵循宪章规范，Entity 是结构唯一事实来源，不维护 Migration 文件
- [x] **测试目录结构**：遵循镜像 src/ 的目录结构，禁止 Unit/Integration 分类子目录

## 项目结构

### 文档（本 Feature）

```text
packages/commission-upgrade-bundle/specs/manual-upgrade-check/
├── spec.md              # 功能规格（已完成）
├── plan.md              # 本文件（实施方案）
├── research.md          # 技术研究（已完成）
├── data-model.md        # 数据模型（待生成）
├── quickstart.md        # 开发指南（待生成）
├── contracts/           # 服务契约（待生成）
│   ├── manual-upgrade-controller.md
│   └── manual-upgrade-form.md
└── tasks.md             # 任务分解（Phase 2 生成）
```

### 代码结构（Scope 根下）

```text
packages/commission-upgrade-bundle/
├── src/
│   ├── Controller/
│   │   └── Admin/
│   │       ├── DistributorLevelUpgradeHistoryCrudController.php (现有)
│   │       ├── DistributorLevelUpgradeRuleCrudController.php (现有)
│   │       └── ManualUpgradeCrudController.php (新增)
│   │
│   ├── Form/
│   │   └── ManualUpgradeCheckType.php (新增 - 用户ID输入表单)
│   │
│   ├── Service/
│   │   ├── DistributorUpgradeService.php (现有 - 复用)
│   │   ├── UpgradeContextProvider.php (现有 - 复用)
│   │   ├── UpgradeExpressionEvaluator.php (现有 - 复用)
│   │   └── ManualUpgradeResultFormatter.php (新增 - 格式化检测结果)
│   │
│   ├── Entity/
│   │   ├── DistributorLevelUpgradeHistory.php (扩展 - 新增 operator 和 triggerType 字段)
│   │   └── DistributorLevelUpgradeRule.php (现有 - 不变)
│   │
│   ├── Repository/
│   │   └── DistributorLevelUpgradeHistoryRepository.php (现有 - 可能新增按操作人查询方法)
│   │
│   └── Security/
│       └── Voter/
│           └── ManualUpgradeVoter.php (新增 - 权限控制)
│
└── tests/                              # 镜像 src/ 目录结构
    ├── Controller/
    │   └── Admin/
    │       └── ManualUpgradeCrudControllerTest.php
    │
    ├── Form/
    │   └── ManualUpgradeCheckTypeTest.php
    │
    ├── Service/
    │   └── ManualUpgradeResultFormatterTest.php
    │
    ├── Security/
    │   └── Voter/
    │       └── ManualUpgradeVoterTest.php
    │
    └── Integration/                    # Bundle级别集成测试（放在tests/根目录）
        └── ManualUpgradeFlowTest.php   # 端到端流程测试
```

**结构决策**：
- 复用现有的 `Service/` 层（`DistributorUpgradeService` 等），不重复实现升级逻辑
- 新增 `ManualUpgradeCrudController` 在 `Controller/Admin/` 下，与现有后台控制器保持一致
- 新增 `Form/ManualUpgradeCheckType` 处理用户输入表单（仅用户ID字段）
- 扩展 `Entity/DistributorLevelUpgradeHistory`，添加 `operator` 和 `triggerType` 字段区分手动/自动升级
- 新增 `Security/Voter/ManualUpgradeVoter` 实现细粒度权限控制
- 测试目录严格镜像 src/ 结构，集成测试放在 tests/Integration/（符合宪章要求）

## 复杂度备案

本功能无宪章违例，所有设计决策符合现有架构原则：

| 检查项 | 符合性说明 |
|--------|-----------|
| Monorepo 分层 | ✅ 功能在现有 package 中扩展，无新增包 |
| 测试优先 | ✅ TDD 流程明确，测试策略已规划 |
| 质量门禁 | ✅ 使用仓库标准 PHPStan 配置（Level 8） |
| 用户管理接口 | ✅ 不涉及用户创建，仅查询现有实体 |
| 数据库 Schema | ✅ Entity 驱动，无 Migration 文件 |
| 测试目录结构 | ✅ 镜像 src/ 结构，无分类子目录 |

## Phase 0: 研究总结

详细研究记录见 [research.md](./research.md)，核心结论：

1. **升级逻辑复用**：直接调用 `DistributorUpgradeService::checkAndUpgrade()`，保证业务规则一致性
2. **后台集成方案**：通过 EasyAdmin Custom Action 扩展，新增独立的 `ManualUpgradeCrudController`
3. **检测-升级流程**：使用 Session 存储检测结果，升级前重新验证条件（防止条件变化）
4. **并发保护**：复用 Doctrine 乐观锁机制（`OptimisticLockException`）
5. **权限控制**：Symfony Voter + EasyAdmin Action 权限配置
6. **操作日志**：扩展现有 `DistributorLevelUpgradeHistory` 实体，新增 `operator` 和 `triggerType` 字段

## Phase 1: 设计文档

### 1. 数据模型（data-model.md）

见 [data-model.md](./data-model.md)（待生成），包含：
- `DistributorLevelUpgradeHistory` 实体扩展（新增字段）
- Session 数据结构定义（检测结果临时存储）
- 表单输入/输出 DTO（ManualUpgradeCheckDTO）

### 2. 服务契约（contracts/）

见 `contracts/` 目录（待生成），包含：
- `manual-upgrade-controller.md`：控制器 Action 定义（检测、升级、结果展示）
- `manual-upgrade-form.md`：表单输入验证规则

### 3. 开发指南（quickstart.md）

见 [quickstart.md](./quickstart.md)（待生成），包含：
- 本地开发环境搭建
- 测试运行指南
- 后台功能访问路径
- 权限配置示例

## Phase 2: 任务分解

将在 `/speckit.tasks` 命令执行后生成 `tasks.md`，按用户故事分组任务：
- **用户故事 1**（P1）：查询用户并检测升级条件
- **用户故事 2**（P1）：执行手动升级
- **用户故事 3**（P2）：批量查询用户列表（可选）
- **用户故事 4**（P2）：升级操作日志记录（部分已实现，需扩展）

## 风险与缓解

| 风险 | 等级 | 缓解措施 | 负责人 |
|------|------|---------|--------|
| 并发冲突导致重复升级 | 高 | 使用 Doctrine 乐观锁，捕获 `OptimisticLockException` 并提示用户重试 | 开发 |
| 检测和升级之间条件变化 | 中 | 升级前重新调用 `checkAndUpgrade()` 验证条件，记录验证失败原因 | 开发 |
| Session过期导致升级失败 | 低 | 设置 30分钟Session超时，提示用户重新检测 | 开发 |
| 权限绕过风险 | 高 | Voter严格控制权限，后端Action强制验证 `isGranted()` | 开发 + 安全审计 |
| EasyAdmin版本升级不兼容 | 低 | 锁定 `easycorp/easyadmin-bundle: ^4` 版本，定期跟踪官方升级指南 | 运维 |

## 下一步行动

1. ✅ Phase 0: 完成 research.md（技术研究）
2. ✅ Phase 0: 完成 plan.md（本文件）
3. → Phase 1: 生成 data-model.md（数据模型定义）
4. → Phase 1: 生成 contracts/*.md（服务契约文档）
5. → Phase 1: 生成 quickstart.md（开发指南）
6. → Phase 1: 更新 agent context（调用 `update-agent-context.sh`）
7. → Phase 2: 使用 `/speckit.tasks` 生成任务分解（tasks.md）
8. → Phase 2: 复核宪章检查并生成最终报告

## 附录

### 关键依赖版本

```json
{
  "php": "^8.2",
  "symfony/framework-bundle": "^7.4",
  "symfony/form": "^7.3",
  "symfony/security-bundle": "^7.3",
  "doctrine/orm": "^3.0",
  "easycorp/easyadmin-bundle": "^4",
  "phpunit/phpunit": "^11.5"
}
```

### 相关文档链接

- [Spec 文档](./spec.md)
- [研究文档](./research.md)
- [宪章文档](../../../.specify/memory/constitution.md)
- [EasyAdmin 官方文档](https://symfony.com/bundles/EasyAdminBundle/current/index.html)
- [Symfony Security Voters](https://symfony.com/doc/current/security/voters.html)
