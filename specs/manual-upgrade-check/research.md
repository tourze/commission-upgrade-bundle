# 技术研究：后台手动升级检测与执行

**Feature**: `manual-upgrade-check`
**日期**: 2025-11-19
**研究目的**: 确定手动升级功能的技术实现方案，复用现有自动升级基础设施

## 研究问题与决策

### Q1: 如何复用现有的升级检测逻辑？

**决策**: 直接复用 `DistributorUpgradeService::checkAndUpgrade()` 方法

**理由**:
- 现有服务已实现完整的升级检测逻辑（表达式评估、上下文构建、升级执行）
- 该方法具有良好的单一职责设计，可直接在后台控制器中调用
- 保证手动升级与自动升级使用完全相同的业务规则

**实现方式**:
```php
// 在新的后台控制器 Action 中：
$result = $this->distributorUpgradeService->checkAndUpgrade($distributor);
```

### Q2: 后台管理界面如何实现？

**决策**: 扩展 EasyAdmin，添加自定义 Action 而非创建独立的 CRUD 页面

**理由**:
- 项目已使用 EasyAdmin 4 作为后台管理框架
- 现有的 `DistributorLevelUpgradeHistoryCrudController` 已展示了 EasyAdmin 集成模式
- 可以在现有的分销员或升级历史 CRUD 中添加自定义按钮和表单

**实现方式**:
- 创建新的 `ManualUpgradeController` 继承 `AbstractCrudController`
- 使用 EasyAdmin 的 Form 组件处理用户ID输入
- 使用 Custom Action 添加"检测"和"升级"按钮

### Q3: 如何实现"检测"和"升级"的两步操作？

**决策**: 使用 EasyAdmin 的自定义 Action + 临时会话存储检测结果

**理由**:
- 检测结果需要在"检测"和"升级"两个操作之间传递
- 使用 Session 存储检测结果，避免在URL中传递大量数据
- 升级前重新验证条件，确保数据一致性（符合 FR-005）

**实现流程**:
1. 用户输入用户ID → 检测Action
2. 检测Action调用 `DistributorUpgradeService::findNextLevelRule()` 和表达式评估（不执行升级）
3. 检测结果存入Session（用户ID + 检测时间戳 + 结果）
4. 显示检测结果页面，展示"执行升级"按钮
5. 用户点击"执行升级" → 升级Action
6. 升级Action验证Session数据，重新调用 `checkAndUpgrade()` 确保条件仍然满足
7. 执行升级并显示结果

### Q4: 如何防止并发冲突（手动升级 vs 自动升级）？

**决策**: 复用 `DistributorUpgradeService` 现有的乐观锁机制

**理由**:
- 现有服务已使用 Doctrine 的乐观锁（OptimisticLockException）
- `checkAndUpgrade()` 方法已处理并发冲突场景
- 手动升级调用相同方法即可自动获得并发保护

**实现细节**:
- 捕获 `OptimisticLockException` 并提示用户重试
- 在检测和升级之间重新验证条件（已包含在 `checkAndUpgrade()` 中）

### Q5: 权限控制如何实现？

**决策**: 使用 Symfony Security 的 Voter 机制 + EasyAdmin 的 Action 权限控制

**理由**:
- Symfony 项目标准权限管理方案
- EasyAdmin 原生支持基于角色的 Action 显示/隐藏
- 可灵活定义不同角色的权限（如只读 vs 可操作）

**实现方式**:
```php
// 在 Action 配置中：
Action::new('manualUpgrade', '手动升级')
    ->displayIf(fn () => $this->isGranted('ROLE_UPGRADE_OPERATOR'))
```

### Q6: 操作日志如何记录？

**决策**: 扩展现有的 `DistributorLevelUpgradeHistory` 实体，添加操作人字段

**理由**:
- 现有实体已记录自动升级历史
- 添加 `operator` 字段和 `trigger_type`（auto/manual）字段即可区分
- 保持历史记录的统一性和可查询性

**数据模型变更**:
```php
// 新增字段：
private ?User $operator = null;  // 操作人（手动升级时必填）
private string $triggerType;     // 'auto' | 'manual'
```

### Q7: 批量操作如何实现（P2优先级）？

**决策**: 第一版MVP不实现，作为独立的用户故事在 tasks.md 中列出

**理由**:
- 批量操作属于P2优先级，非核心MVP
- 单个用户操作已可满足基本需求
- 批量操作需要额外考虑事务、超时、进度反馈等复杂性
- 建议在Phase 2实现时使用异步任务队列（Symfony Messenger）

**未来实现建议**:
- 使用 Symfony Messenger 处理批量任务
- 前端轮询或WebSocket推送进度
- 失败重试机制

## 技术依赖评估

### 现有依赖（无需新增）
- `easycorp/easyadmin-bundle: ^4` - 后台管理框架
- `symfony/security-bundle: ^7.3` - 权限控制
- `symfony/form: ^7.3` - 表单处理
- `doctrine/orm: ^3.0` - ORM和乐观锁

### 建议新增依赖（可选）
- 无（现有技术栈已满足需求）

## 风险与缓解措施

| 风险 | 影响 | 缓解措施 |
|------|------|---------|
| 并发冲突导致重复升级 | 高 | 使用乐观锁，捕获异常并提示用户重试 |
| 检测和升级之间条件变化 | 中 | 升级前重新验证条件，记录验证失败原因 |
| Session过期导致升级失败 | 低 | 设置合理的Session超时时间（30分钟），提示用户重新检测 |
| 权限绕过风险 | 高 | 使用Voter严格控制权限，后端Action强制验证 |
| 大量并发手动操作压垮系统 | 中 | 暂不处理（运营人员数量有限），未来可考虑操作频率限制 |

## 架构决策记录（ADR）

### ADR-001: 复用现有服务而非重写升级逻辑

**上下文**: 现有 `DistributorUpgradeService` 已实现升级逻辑
**决策**: 手动升级直接调用现有服务
**后果**: 保证逻辑一致性，减少代码重复，但依赖现有服务的稳定性
**替代方案**: 重写独立的手动升级逻辑（rejected：违反DRY原则，维护成本高）

### ADR-002: 使用EasyAdmin扩展而非独立后台页面

**上下文**: 项目已使用EasyAdmin作为后台框架
**决策**: 通过EasyAdmin的Action机制扩展功能
**后果**: 保持UI一致性，减少开发成本，但受限于EasyAdmin的功能边界
**替代方案**: 使用Symfony Controller创建独立页面（rejected：破坏UI一致性，增加维护成本）

### ADR-003: 检测结果存储在Session中

**上下文**: 检测和升级是两步操作，需要传递检测结果
**决策**: 使用Session临时存储检测结果
**后果**: 实现简单，但依赖Session配置，存在过期风险
**替代方案1**: URL参数传递（rejected：数据量大，安全风险）
**替代方案2**: 数据库临时表（rejected：过度设计，增加复杂度）

## 下一步行动

1. ✅ 完成技术研究和决策记录
2. → 进入Phase 1：生成 data-model.md 定义实体变更
3. → 进入Phase 1：生成 contracts/*.md 定义服务接口
4. → 进入Phase 1：生成 quickstart.md 开发指南
