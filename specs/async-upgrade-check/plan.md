# 实施方案：异步升级检查

**Feature**: `async-upgrade-check` | **Scope**: `packages/commission-upgrade-bundle` | **日期**: 2025-11-19 | **Spec**: [spec.md](./spec.md)
**输入**: `packages/commission-upgrade-bundle/specs/async-upgrade-check/spec.md`

> 说明：本模板由 `/speckit.plan` 填充。

## 概述

为 commission-upgrade-bundle 添加异步升级检查机制，解决当前同步升级检查阻塞提现流程的性能问题。主要需求包括：

1. **异步化提现触发流程**：将升级检查从同步改为消息队列异步处理，提现响应时间从 500ms 降至 100ms 以内
2. **可靠性与幂等性**：支持任务失败重试（最多 3 次），确保同一分销员不会重复升级
3. **批量处理能力**：提供命令行工具批量触发升级检查，支持规则调整后的重算场景

技术方案基于 Symfony Messenger 组件，通过实现 `AsyncMessageInterface` 接口创建升级检查消息，复用现有 `DistributorUpgradeService` 核心逻辑。

## 技术背景

**语言/版本**：PHP 8.2+
**主要依赖**：
- Symfony 7.3（框架核心：Console、DependencyInjection、EventDispatcher）
- Symfony Messenger（消息队列抽象层）- [NEEDS CLARIFICATION: 需确认项目已配置 Messenger 及传输层]
- Doctrine ORM 3.0+（实体管理、乐观锁）
- tourze/async-contracts（AsyncMessageInterface 接口定义）
- tourze/order-commission-bundle（Distributor、WithdrawLedger 实体依赖）

**存储**：
- 关系型数据库（通过 Doctrine ORM）：存储分销员、提现流水、升级历史
- 消息队列存储 - [NEEDS CLARIFICATION: RabbitMQ/Redis/数据库传输层，需确认项目配置]

**测试**：
- PHPUnit 11.5+（单元测试、集成测试）
- PHPStan level 8（静态分析）
- tourze/phpunit-symfony-kernel-test（Symfony 内核测试支持）

**目标平台**：Linux/macOS 服务器环境（PHP-FPM + CLI）

**项目类型**：Symfony Bundle（可复用库包）

**性能目标**：
- 提现状态更新响应时间 < 100ms（P95 分位）
- 单个升级检查任务执行时间 < 5 秒（P95 分位）
- 消息投递成功率 >= 99.9%
- 批量投递 1000 条消息 < 5 秒

**约束**：
- 必须保持与现有 DistributorUpgradeService 的逻辑一致性（复用而非重写）
- 必须实现 tourze/async-contracts 定义的 AsyncMessageInterface 接口
- 不得破坏现有事务边界和幂等性保障
- 消息队列延迟（投递到开始执行）< 10 秒（P95 分位）

**规模/场景**：
- 提现流水状态变更频率：<= 100 次/秒
- 分销员总数规模：10k~100k 量级
- 批量重算场景：单次触发 1000+ 分销员升级检查

## 宪章检查

> 阶段门：Phase 0 前必过，Phase 1 后复核。依据 `.specify/memory/constitution.md`。

- [x] **Monorepo 分层架构**：功能归属 `packages/commission-upgrade-bundle`，依赖边界清晰（依赖 async-contracts、order-commission-bundle、Symfony 核心组件）
- [x] **Spec 驱动**：已具备完整的 spec.md（用户场景、功能需求、成功标准），plan.md 正在填充
- [x] **测试优先**：TDD 策略明确（先编写测试验证消息投递、消费、幂等性，再实现代码）；测试目录结构镜像 src/ 目录（`tests/Message/`、`tests/MessageHandler/`），Bundle 级别测试放在 `tests/` 根目录
- [x] **质量门禁**：
  - 格式化：PHP-CS-Fixer（项目已配置）
  - 静态检查：PHPStan level 8（composer.json 已声明依赖）
  - 安全扫描：无硬编码密钥，使用 Doctrine 参数化查询，避免注入风险
- [x] **可追溯性**：设计决策记录在 plan.md 和 research.md，提交遵循 Conventional Commits 规范

**Phase 0 评估结果**：✅ 所有宪章检查项通过，无违例，可进入 Phase 0 研究阶段。

**Phase 1 后复核结果**：✅ 设计完成后再次确认所有宪章检查项仍然通过：
- ✅ **Monorepo 分层架构**：代码结构符合 Bundle 设计规范，依赖边界清晰（已在 data-model.md 中明确）
- ✅ **Spec 驱动**：已完成 spec.md、plan.md、research.md、data-model.md、contracts/*.md、quickstart.md
- ✅ **测试优先**：已在 contracts/*.md 中定义测试用例，测试目录结构遵循宪章约定（镜像 src/ 结构，禁止 Unit/Integration 分类子目录，Bundle 级别测试放在 tests/ 根目录）
- ✅ **质量门禁**：PHPStan level 8、PHP-CS-Fixer、安全基线均已规划
- ✅ **可追溯性**：所有设计决策记录在 research.md 和 plan.md，契约文档完整

若有宪章违例，请在"复杂度备案"中说明原因与替代方案。

## 项目结构

### 文档（本 Feature）

```text
[scope]/specs/[feature]/
├── plan.md              # 本文件（/speckit.plan 输出）
├── research.md          # Phase 0（/speckit.plan 输出）
├── data-model.md        # Phase 1（/speckit.plan 输出）
├── quickstart.md        # Phase 1（/speckit.plan 输出）
├── contracts/           # Phase 1（/speckit.plan 输出）
└── tasks.md             # Phase 2（/speckit.tasks 输出）
```

### 代码结构（Scope 根下）

```text
packages/commission-upgrade-bundle/
├── src/
│   ├── Message/
│   │   └── DistributorUpgradeCheckMessage.php    # 异步消息类（实现 AsyncMessageInterface）
│   ├── MessageHandler/
│   │   └── DistributorUpgradeCheckHandler.php    # 消息消费者（Handler）
│   ├── EventListener/
│   │   └── WithdrawLedgerStatusListener.php      # 修改：改为异步投递消息
│   ├── Command/
│   │   └── BatchCheckUpgradeCommand.php          # 新增：批量触发升级检查命令
│   ├── Service/
│   │   └── DistributorUpgradeService.php         # 复用：核心升级逻辑（不修改）
│   └── Resources/config/
│       └── services.yaml                          # 新增：配置 Handler、Messenger routing
│
├── tests/
│   ├── Message/
│   │   └── DistributorUpgradeCheckMessageTest.php    # 镜像 src/Message/
│   ├── MessageHandler/
│   │   └── DistributorUpgradeCheckHandlerTest.php    # 镜像 src/MessageHandler/
│   ├── AsyncUpgradeFlowTest.php                       # Bundle 级别集成测试（端到端）
│   ├── IdempotencyTest.php                            # Bundle 级别集成测试（幂等性验证）
│   └── AsyncMessageInterfaceContractTest.php          # 契约测试：接口实现验证
│
└── specs/async-upgrade-check/
    ├── spec.md
    ├── plan.md (本文件)
    ├── research.md (Phase 0 输出)
    ├── data-model.md (Phase 1 输出)
    ├── quickstart.md (Phase 1 输出)
    └── contracts/ (Phase 1 输出)
        ├── upgrade-check-message.md
        └── upgrade-check-handler.md
```

**结构决策**：

1. **Message 目录**：存放异步消息类，遵循 Symfony Messenger 命名约定
2. **MessageHandler 目录**：存放消息处理器（Handler），Symfony 自动注册为消费者
3. **复用现有服务**：不修改 `DistributorUpgradeService`，仅在 Handler 中调用
4. **测试目录结构**（遵循 constitution.md 测试目录结构约定）：
   - **镜像 src/ 结构**：`tests/Message/`、`tests/MessageHandler/` 直接镜像 `src/Message/`、`src/MessageHandler/`
   - **禁止分类子目录**：不创建 `tests/Unit/`、`tests/Integration/` 等分类目录
   - **Bundle 级别测试**：集成测试（`AsyncUpgradeFlowTest.php`、`IdempotencyTest.php`）和契约测试（`AsyncMessageInterfaceContractTest.php`）直接放在 `tests/` 根目录
   - **理由**：简化文件定位、减少认知负担、确保测试覆盖率可追溯（每个源文件对应的测试文件路径一致）
5. **配置集中化**：Messenger routing 配置在 `services.yaml` 中统一管理

## 复杂度备案（仅当宪章有违例时填写）

| 违例项 | 原因 | 更简单方案为何不可 |
|--------|------|-------------------|
| 示例：增加第 4 个子项目 | 当前需要 | 3 个不足的理由 |
| 示例：引入 Repository 模式 | 特定问题 | 直连 DB 不足的理由 |
