# 任务清单：后台手动升级检测与执行

**输入**: `packages/commission-upgrade-bundle/specs/manual-upgrade-check/` 下的设计文档
**前置**: plan.md、spec.md、research.md、data-model.md、contracts/*.md

**测试策略**: 本功能遵循 TDD 流程，每个实现任务前都有对应的测试任务

**组织方式**: 任务按用户故事分组，确保每个故事可独立实现与验收

---

## 格式说明：`[ID] [P?] [Story] 描述`

- **[P]**：可并行执行（不同文件、无未完成依赖）
- **[Story]**：所属用户故事（如 US1/US2/US3/US4）
- 描述中必须包含具体文件路径

---

## Phase 1: 初始化（环境搭建）

**目的**: 验证开发环境就绪，确保依赖完整

- [ ] T001 验证 PHP 8.2+ 和 Composer 环境
- [ ] T002 在根目录执行 `composer install` 验证依赖安装
- [ ] T003 [P] 运行 `bin/console doctrine:schema:validate` 验证现有数据库结构
- [ ] T004 [P] 运行现有测试套件确保基线通过：`vendor/bin/phpunit packages/commission-upgrade-bundle/tests`

**检查点**: 环境就绪，现有测试全部通过

---

## Phase 2: 基础能力（所有用户故事的前置依赖）

**目的**: 扩展数据模型和创建基础 DTO，为所有用户故事提供数据层支持

**⚠️ 关键**: 未完成前禁止进入任意用户故事

### 数据模型扩展

- [ ] T005 扩展 `DistributorLevelUpgradeHistory` 实体，新增 `trigger_type` 和 `operator` 字段：`packages/commission-upgrade-bundle/src/Entity/DistributorLevelUpgradeHistory.php`
- [ ] T006 运行 `bin/console doctrine:schema:update --dump-sql` 验证 DDL 正确性
- [ ] T007 [P] 创建 `ManualUpgradeCheckRequest` DTO：`packages/commission-upgrade-bundle/src/DTO/ManualUpgradeCheckRequest.php`
- [ ] T008 [P] 创建 `ManualUpgradeCheckResult` DTO：`packages/commission-upgrade-bundle/src/DTO/ManualUpgradeCheckResult.php`

### 测试基础实体扩展

- [ ] T009 编写 `DistributorLevelUpgradeHistory` 实体扩展测试：`packages/commission-upgrade-bundle/tests/Entity/DistributorLevelUpgradeHistoryTest.php`
- [ ] T010 运行测试确保新增字段的 getter/setter 正确：`vendor/bin/phpunit tests/Entity/DistributorLevelUpgradeHistoryTest.php`

**检查点**: 数据模型扩展完成，DTO 已定义，测试通过

---

## Phase 3: 用户故事 1 - 查询用户并检测升级条件（优先级：P1）🎯 MVP 核心

**目标**: 运营人员能够输入用户ID，检测该用户是否满足升级条件，并查看详细的检测结果

**独立验证**: 运营人员访问后台页面，输入用户ID，点击"检测升级条件"，能看到包含当前等级、目标等级、是否满足条件、各条件详细数据的检测报告

### 测试（TDD - 先写测试）

- [ ] T011 [P] [US1] 创建 `ManualUpgradeCheckType` 表单单元测试：`packages/commission-upgrade-bundle/tests/Form/ManualUpgradeCheckTypeTest.php`
- [ ] T012 [P] [US1] 运行表单测试确认失败（红）：`vendor/bin/phpunit tests/Form/ManualUpgradeCheckTypeTest.php`

### 表单实现

- [ ] T013 [US1] 实现 `ManualUpgradeCheckType` 表单类：`packages/commission-upgrade-bundle/src/Form/ManualUpgradeCheckType.php`（依赖 T007 DTO）
- [ ] T014 [US1] 运行表单测试确认通过（绿）：`vendor/bin/phpunit tests/Form/ManualUpgradeCheckTypeTest.php`

### 服务层实现（格式化检测结果）

- [ ] T015 [P] [US1] 创建 `ManualUpgradeResultFormatter` 服务测试：`packages/commission-upgrade-bundle/tests/Service/ManualUpgradeResultFormatterTest.php`
- [ ] T016 [US1] 实现 `ManualUpgradeResultFormatter` 服务：`packages/commission-upgrade-bundle/src/Service/ManualUpgradeResultFormatter.php`（复用现有 `UpgradeContextProvider` 和 `UpgradeExpressionEvaluator`）
- [ ] T017 [US1] 运行服务测试确认通过：`vendor/bin/phpunit tests/Service/ManualUpgradeResultFormatterTest.php`

### Controller 实现（检测和结果展示）

- [ ] T018 [P] [US1] 创建 `ManualUpgradeCrudController` 控制器测试（checkAction 和 resultAction）：`packages/commission-upgrade-bundle/tests/Controller/Admin/ManualUpgradeCrudControllerTest.php`
- [ ] T019 [US1] 实现 `ManualUpgradeCrudController` 的 `checkAction`（处理表单提交、调用升级服务检测、存储结果到 Session）：`packages/commission-upgrade-bundle/src/Controller/Admin/ManualUpgradeCrudController.php`
- [ ] T020 [US1] 实现 `ManualUpgradeCrudController` 的 `resultAction`（从 Session 读取结果、渲染结果页面）：`packages/commission-upgrade-bundle/src/Controller/Admin/ManualUpgradeCrudController.php`
- [ ] T021 [US1] 创建检测表单 Twig 模板：`packages/commission-upgrade-bundle/templates/manual_upgrade/check_form.html.twig`
- [ ] T022 [US1] 创建结果展示 Twig 模板：`packages/commission-upgrade-bundle/templates/manual_upgrade/result.html.twig`
- [ ] T023 [US1] 运行 Controller 测试确认 checkAction 和 resultAction 通过：`vendor/bin/phpunit tests/Controller/Admin/ManualUpgradeCrudControllerTest.php`

### 路由配置

- [ ] T024 [US1] 配置 EasyAdmin 路由（如需）：`packages/commission-upgrade-bundle/src/Resources/config/routes.yaml` 或通过 Attribute 配置

### 集成测试

- [ ] T025 [US1] 创建端到端集成测试（检测流程）：`packages/commission-upgrade-bundle/tests/Integration/ManualUpgradeCheckFlowTest.php`
- [ ] T026 [US1] 运行集成测试验证完整检测流程：`vendor/bin/phpunit tests/Integration/ManualUpgradeCheckFlowTest.php`

### 质量门

- [ ] T027 [US1] 运行 PHPStan 静态分析：`vendor/bin/phpstan analyse -c phpstan.neon packages/commission-upgrade-bundle/src`
- [ ] T028 [US1] 运行 PHP-CS-Fixer 格式化：`vendor/bin/php-cs-fixer fix packages/commission-upgrade-bundle/src`
- [ ] T029 [US1] 运行完整测试套件确保无回归：`vendor/bin/phpunit packages/commission-upgrade-bundle/tests`

**检查点**: 用户故事 1 完成，运营人员可以检测用户升级条件并查看详细结果，所有测试通过

---

## Phase 4: 用户故事 2 - 执行手动升级（优先级：P1）🎯 MVP 核心

**目标**: 运营人员在检测结果页面点击"执行升级"按钮，为满足条件的用户执行升级操作，并查看升级结果

**独立验证**: 运营人员对检测结果为"满足条件"的用户点击"执行升级"，能看到升级成功确认信息，且用户等级已更新

**依赖**: Phase 3 完成（依赖检测流程和 Session 数据）

### 权限控制

- [ ] T030 [P] [US2] 创建 `ManualUpgradeVoter` 权限控制测试：`packages/commission-upgrade-bundle/tests/Security/Voter/ManualUpgradeVoterTest.php`
- [ ] T031 [US2] 实现 `ManualUpgradeVoter` 权限控制（`ROLE_UPGRADE_OPERATOR`）：`packages/commission-upgrade-bundle/src/Security/Voter/ManualUpgradeVoter.php`
- [ ] T032 [US2] 运行 Voter 测试确认权限验证正确：`vendor/bin/phpunit tests/Security/Voter/ManualUpgradeVoterTest.php`

### Controller 实现（升级操作）

- [ ] T033 [P] [US2] 扩展 `ManualUpgradeCrudControllerTest` 添加 upgradeAction 测试用例：`packages/commission-upgrade-bundle/tests/Controller/Admin/ManualUpgradeCrudControllerTest.php`
- [ ] T034 [US2] 实现 `ManualUpgradeCrudController` 的 `upgradeAction`（验证 Session、验证权限、调用 `DistributorUpgradeService::checkAndUpgrade()`、标记 `trigger_type='manual'` 和 `operator`、清除 Session）：`packages/commission-upgrade-bundle/src/Controller/Admin/ManualUpgradeCrudController.php`
- [ ] T035 [US2] 创建升级成功 Twig 模板：`packages/commission-upgrade-bundle/templates/manual_upgrade/success.html.twig`
- [ ] T036 [US2] 运行 Controller 测试确认 upgradeAction 通过：`vendor/bin/phpunit tests/Controller/Admin/ManualUpgradeCrudControllerTest.php::testUpgradeAction*`

### 并发冲突处理

- [ ] T037 [US2] 在 `upgradeAction` 中添加 `OptimisticLockException` 异常捕获和错误提示
- [ ] T038 [US2] 编写并发冲突场景测试（模拟乐观锁异常）：`packages/commission-upgrade-bundle/tests/Controller/Admin/ManualUpgradeCrudControllerTest.php::testUpgradeActionWithOptimisticLockException`
- [ ] T039 [US2] 运行并发冲突测试确认异常处理正确

### 集成测试

- [ ] T040 [US2] 扩展集成测试添加升级流程（检测 → 升级 → 验证历史记录）：`packages/commission-upgrade-bundle/tests/Integration/ManualUpgradeCheckFlowTest.php`
- [ ] T041 [US2] 运行集成测试验证完整升级流程：`vendor/bin/phpunit tests/Integration/ManualUpgradeCheckFlowTest.php`

### 质量门

- [ ] T042 [US2] 运行 PHPStan 静态分析：`vendor/bin/phpstan analyse -c phpstan.neon packages/commission-upgrade-bundle/src`
- [ ] T043 [US2] 运行 PHP-CS-Fixer 格式化：`vendor/bin/php-cs-fixer fix packages/commission-upgrade-bundle/src`
- [ ] T044 [US2] 运行完整测试套件确保无回归：`vendor/bin/phpunit packages/commission-upgrade-bundle/tests`

**检查点**: 用户故事 2 完成，运营人员可以执行手动升级，升级操作被正确记录，所有测试通过

---

## Phase 5: 用户故事 3 - 批量查询用户列表（优先级：P2）📈 体验优化

**目标**: 运营人员可以一次输入多个用户ID，批量检测并展示列表结果，支持批量升级

**独立验证**: 运营人员输入10个用户ID（逗号分隔），系统返回10行结果列表，每行显示用户基本信息和检测结果

**依赖**: Phase 3 完成（复用检测逻辑）

**注意**: 本故事为 P2 优先级，可在 MVP 之后实施

### 表单扩展

- [ ] T045 [P] [US3] 扩展 `ManualUpgradeCheckType` 添加批量输入字段测试：`packages/commission-upgrade-bundle/tests/Form/ManualUpgradeCheckTypeTest.php`
- [ ] T046 [US3] 扩展 `ManualUpgradeCheckType` 添加 `distributorIds`（TextareaType）字段：`packages/commission-upgrade-bundle/src/Form/ManualUpgradeCheckType.php`
- [ ] T047 [US3] 运行表单测试确认批量输入验证正确

### 服务层实现（批量解析和检测）

- [ ] T048 [P] [US3] 创建批量ID解析服务测试：`packages/commission-upgrade-bundle/tests/Service/BatchDistributorIdParserTest.php`
- [ ] T049 [US3] 实现批量ID解析服务（支持逗号、空格、换行分隔）：`packages/commission-upgrade-bundle/src/Service/BatchDistributorIdParser.php`
- [ ] T050 [US3] 运行解析服务测试确认通过

### Controller 实现（批量检测和结果展示）

- [ ] T051 [P] [US3] 扩展 `ManualUpgradeCrudControllerTest` 添加批量检测测试用例：`packages/commission-upgrade-bundle/tests/Controller/Admin/ManualUpgradeCrudControllerTest.php`
- [ ] T052 [US3] 实现 `ManualUpgradeCrudController` 的 `batchCheckAction`（解析ID列表、逐个检测、返回列表结果）：`packages/commission-upgrade-bundle/src/Controller/Admin/ManualUpgradeCrudController.php`
- [ ] T053 [US3] 创建批量结果列表 Twig 模板：`packages/commission-upgrade-bundle/templates/manual_upgrade/batch_result.html.twig`
- [ ] T054 [US3] 运行批量检测测试确认通过

### 批量升级实现（可选子功能）

- [ ] T055 [P] [US3] 扩展 `ManualUpgradeCrudControllerTest` 添加批量升级测试用例
- [ ] T056 [US3] 实现 `ManualUpgradeCrudController` 的 `batchUpgradeAction`（遍历选中用户、逐个调用升级服务、记录成功/失败）：`packages/commission-upgrade-bundle/src/Controller/Admin/ManualUpgradeCrudController.php`
- [ ] T057 [US3] 运行批量升级测试确认通过

### 质量门

- [ ] T058 [US3] 运行 PHPStan 静态分析：`vendor/bin/phpstan analyse -c phpstan.neon packages/commission-upgrade-bundle/src`
- [ ] T059 [US3] 运行 PHP-CS-Fixer 格式化：`vendor/bin/php-cs-fixer fix packages/commission-upgrade-bundle/src`
- [ ] T060 [US3] 运行完整测试套件确保无回归：`vendor/bin/phpunit packages/commission-upgrade-bundle/tests`

**检查点**: 用户故事 3 完成，支持批量检测和升级，所有测试通过

---

## Phase 6: 用户故事 4 - 升级操作日志记录（优先级：P2）📋 审计合规

**目标**: 系统自动记录手动检测和升级操作日志，运营人员和管理员可以查询审计历史记录

**独立验证**: 运营人员在操作日志页面可以查看过去所有手动升级记录，包含操作人、时间、目标用户、结果等信息

**依赖**: Phase 4 完成（升级操作已实现，历史记录已有 `trigger_type` 和 `operator` 字段）

**注意**: 本故事为 P2 优先级，部分功能已在 Phase 2 数据模型中实现，本阶段主要完善查询和展示

### Repository 扩展

- [ ] T061 [P] [US4] 扩展 `DistributorLevelUpgradeHistoryRepository` 添加按操作人查询方法测试：`packages/commission-upgrade-bundle/tests/Repository/DistributorLevelUpgradeHistoryRepositoryTest.php`
- [ ] T062 [US4] 在 `DistributorLevelUpgradeHistoryRepository` 添加 `findByOperator()` 方法：`packages/commission-upgrade-bundle/src/Repository/DistributorLevelUpgradeHistoryRepository.php`
- [ ] T063 [US4] 在 `DistributorLevelUpgradeHistoryRepository` 添加 `findManualUpgrades()` 方法（查询 `trigger_type='manual'` 的记录）：`packages/commission-upgrade-bundle/src/Repository/DistributorLevelUpgradeHistoryRepository.php`
- [ ] T064 [US4] 运行 Repository 测试确认查询方法正确

### EasyAdmin CRUD 扩展

- [ ] T065 [US4] 扩展 `DistributorLevelUpgradeHistoryCrudController` 添加操作人和触发类型字段展示：`packages/commission-upgrade-bundle/src/Controller/Admin/DistributorLevelUpgradeHistoryCrudController.php`
- [ ] T066 [US4] 在 `DistributorLevelUpgradeHistoryCrudController` 添加按触发类型和操作人筛选功能
- [ ] T067 [US4] 手动测试后台历史记录页面，验证字段显示和筛选功能正确

### 质量门

- [ ] T068 [US4] 运行 PHPStan 静态分析：`vendor/bin/phpstan analyse -c phpstan.neon packages/commission-upgrade-bundle/src`
- [ ] T069 [US4] 运行 PHP-CS-Fixer 格式化：`vendor/bin/php-cs-fixer fix packages/commission-upgrade-bundle/src`
- [ ] T070 [US4] 运行完整测试套件确保无回归：`vendor/bin/phpunit packages/commission-upgrade-bundle/tests`

**检查点**: 用户故事 4 完成，操作日志可查询和审计，所有测试通过

---

## Phase 7: 完善与发布（跨故事优化）

**目的**: 完善文档、手动测试、准备发布

### 文档完善

- [ ] T071 [P] 更新 README.md 说明手动升级功能使用方式：`packages/commission-upgrade-bundle/README.md`
- [ ] T072 [P] 补充权限配置说明到 quickstart.md：`packages/commission-upgrade-bundle/specs/manual-upgrade-check/quickstart.md`

### 手动测试

- [ ] T073 启动开发服务器进行完整流程手动测试（检测 → 升级 → 查看日志）
- [ ] T074 使用不同角色测试权限控制（有权限 vs 无权限）
- [ ] T075 测试边界场景（Session 过期、并发冲突、无效用户ID、最高等级用户）

### 数据库迁移准备

- [ ] T076 生成生产环境 DDL 脚本：`bin/console doctrine:schema:update --dump-sql > packages/commission-upgrade-bundle/docs/migration.sql`
- [ ] T077 验证 DDL 脚本正确性并提交到文档目录

### 最终质量门

- [ ] T078 运行完整测试套件（所有 Phase）：`vendor/bin/phpunit packages/commission-upgrade-bundle/tests`
- [ ] T079 运行 PHPStan Level 8 零错误验证：`vendor/bin/phpstan analyse -c phpstan.neon packages/commission-upgrade-bundle/src`
- [ ] T080 运行 PHP-CS-Fixer 确保代码风格一致：`vendor/bin/php-cs-fixer fix packages/commission-upgrade-bundle/src`
- [ ] T081 验证测试覆盖率 > 80%：`vendor/bin/phpunit --coverage-text packages/commission-upgrade-bundle/tests`

### 提交与 Code Review

- [ ] T082 按 Conventional Commits 规范提交代码：`git commit -m "feat(commission-upgrade): 实现后台手动升级检测与执行功能"`
- [ ] T083 创建 Pull Request 并关联到 spec 文档
- [ ] T084 通过 Code Review 并合并到主分支

**检查点**: 功能完整、测试通过、文档齐全、已发布

---

## 依赖关系图

```
Phase 1 (初始化)
    ↓
Phase 2 (基础能力) - 必须完成
    ↓
    ├→ Phase 3 (US1: 检测) 🎯 MVP 核心
    │      ↓
    │   Phase 4 (US2: 升级) 🎯 MVP 核心 - 依赖 Phase 3
    │      ↓
    │      ├→ Phase 5 (US3: 批量) 📈 P2 - 依赖 Phase 3
    │      └→ Phase 6 (US4: 日志) 📋 P2 - 依赖 Phase 4
    │
    └→ Phase 7 (完善) - 依赖所有用户故事完成
```

**独立性说明**:
- Phase 3 (US1) 和 Phase 4 (US2) 构成 MVP，必须按顺序完成
- Phase 5 (US3) 和 Phase 6 (US4) 为 P2 优先级，可在 MVP 之后实施
- Phase 5 (US3) 仅依赖 Phase 3，不依赖 Phase 4（可独立实施批量检测）
- Phase 6 (US4) 依赖 Phase 4（需要升级操作已实现）

---

## 并行执行建议

### Phase 2（基础能力）并行机会

可以并行执行以下任务（不同文件，无依赖）：
- T007 (ManualUpgradeCheckRequest) || T008 (ManualUpgradeCheckResult)

### Phase 3（US1：检测）并行机会

可以并行执行以下任务（不同文件，无依赖）：
- T011 (表单测试) || T015 (服务测试) || T018 (Controller 测试)
- T021 (check_form.html.twig) || T022 (result.html.twig)

### Phase 4（US2：升级）并行机会

可以并行执行以下任务（不同文件，无依赖）：
- T030 (Voter 测试) || T033 (Controller 测试扩展)

### Phase 5（US3：批量）并行机会

可以并行执行以下任务（不同文件，无依赖）：
- T045 (表单扩展测试) || T048 (解析服务测试) || T051 (Controller 测试扩展)

### Phase 7（完善）并行机会

可以并行执行以下任务（不同文件，无依赖）：
- T071 (README) || T072 (quickstart.md)

---

## 实施策略

### MVP 优先（Phase 1-4）

**建议首次实施范围**：Phase 1 + Phase 2 + Phase 3 (US1) + Phase 4 (US2)

这将交付核心价值：
- ✅ 运营人员可以检测用户升级条件
- ✅ 运营人员可以执行手动升级
- ✅ 升级操作被正确记录并标记为手动触发
- ✅ 权限控制保证只有授权人员可操作

**预计工作量**：约 44 个任务（T001-T044）

### 增量交付（Phase 5-6）

**第二阶段实施**：Phase 5 (US3: 批量) 或 Phase 6 (US4: 日志查询)

根据业务优先级选择：
- 如果运营效率是瓶颈：优先实施 Phase 5（批量操作）
- 如果审计合规是重点：优先实施 Phase 6（日志查询增强）

**预计工作量**：Phase 5 约 16 个任务，Phase 6 约 10 个任务

---

## 任务统计

- **总任务数**：84 个任务
- **MVP 核心任务**：44 个（Phase 1-4）
- **P2 优化任务**：26 个（Phase 5-6）
- **完善任务**：14 个（Phase 7）
- **可并行任务**：约 20 个（标记 [P]）
- **测试任务**：约 30 个（遵循 TDD 流程）

---

## 验证清单

每个 Phase 完成后必须通过以下验证：

- [ ] 所有单元测试通过
- [ ] PHPStan Level 8 零错误
- [ ] PHP-CS-Fixer 格式化通过
- [ ] 集成测试通过（如有）
- [ ] 手动测试验证核心流程（Phase 3/4/7）

---

**任务清单生成完成！建议从 MVP（Phase 1-4）开始实施，按 TDD 流程逐个完成任务。**
