# 规格质量清单：分销员自动升级

**目的**：在进入规划前验证规格的完整性与质量
**创建时间**：2025-11-17
**最后更新**：2025-11-17（澄清会话后）
**Feature**：[distributor-auto-upgrade](../spec.md)

## 内容质量
- [x] 无实现细节（语言/框架/API）
- [x] 聚焦用户价值与业务需求
- [x] 面向非技术干系人可读
- [x] 必填章节已完成

## 需求完整性
- [x] 无 [NEEDS CLARIFICATION] 标记残留
- [x] 需求可测试且无歧义
- [x] 成功标准可量化
- [x] 成功标准不依赖具体技术
- [x] 验收场景已覆盖
- [x] 边界/异常场景已识别
- [x] 范围清晰、有界
- [x] 依赖与假设已标明

## 功能就绪度
- [x] 所有功能需求有明确验收标准
- [x] 用户场景覆盖主要流程
- [x] 符合成功标准中的可量化结果
- [x] 规格未泄露实现细节

## 验证结果
✅ **所有检查项已通过**

## 澄清记录（Session 2025-11-17）

### 第一轮澄清（提现与升级关系）
1. **佣金结算触发时机**：分销员提现成功后（WithdrawLedger.Completed）
2. **职责边界**：提现功能已在 OrderCommissionBundle 实现，本 bundle 仅负责监听提现成功事件并执行升级逻辑
3. **实体复用策略**：复用 Distributor、DistributorLevel、WithdrawLedger；升级阈值配置存储在 DistributorLevel.criteriaJson；新建 DistributorLevelUpgradeHistory 记录升级历史
4. **防刷单机制**：仅统计 WithdrawLedger.Completed 状态的佣金，利用 OrderCommissionBundle 的提现流程天然防刷单

### 第二轮澄清（升级条件灵活性）
5. **升级条件灵活性**：采用 Symfony Expression Language 支持复杂组合条件（如 "withdrawnAmount > 5000 and inviteeCount > 10"），criteriaJson 存储表达式字符串
6. **后台编辑界面**：基于 EasyAdmin bundle 提供混合方案：表单 UI（简单条件）+ 高级模式（Monaco Editor 代码编辑器 + 语法高亮）
7. **可用变量清单**：提供明确的可引用变量（withdrawnAmount, inviteeCount, orderCount 等），保存时验证变量合法性
8. **表达式验证与容错**：保存时验证语法，运行时失败记录日志并通知管理员

## 备注
- **已解决**：通知渠道确定为"站内信"（未来可重构为多渠道）
- **架构决策**：
  - 与 OrderCommissionBundle 集成，复用现有实体和提现流程，减少维护成本
  - 使用 Symfony Expression Language 作为规则引擎，支持复杂升级条件，具备良好的可扩展性
  - 后台基于 EasyAdmin bundle，提供表单 UI 和高级代码编辑器的混合方案
- **可扩展性**：criteriaJson 存储表达式字符串，未来可轻松新增变量（如团队业绩、复购率等）
- **下一步**：规格已就绪，可执行 `/speckit.plan` 生成实施计划
