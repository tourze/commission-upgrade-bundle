# 快速开始：后台手动升级功能开发指南

**Feature**: `manual-upgrade-check`
**日期**: 2025-11-19
**目标读者**: 开发人员
**预计阅读时间**: 15分钟

## 概述

本指南帮助开发人员快速搭建本地环境、理解代码结构、运行测试，并开始实现后台手动升级功能。

---

## 前置条件

### 必需软件

- **PHP**: >= 8.2
- **Composer**: >= 2.0
- **Database**: MySQL/PostgreSQL（任一）
- **Git**: 任意版本

### 开发工具（推荐）

- **IDE**: PhpStorm / VSCode + PHP Intelephense
- **Database Client**: TablePlus / DBeaver
- **Terminal**: iTerm2 / Windows Terminal

---

## 环境搭建

### 1. 克隆仓库并安装依赖

```bash
# 克隆项目（假设已完成）
cd /Users/air/work/php-monorepo

# 在根目录安装依赖（遵循 Monorepo 架构规范）
composer install

# 验证 commission-upgrade-bundle 依赖正常
composer show tourze/commission-upgrade-bundle
```

### 2. 配置数据库

```bash
# 复制环境配置（如果需要）
cp .env .env.local

# 编辑数据库连接（.env.local）
DATABASE_URL="mysql://user:password@127.0.0.1:3306/db_name"

# 创建数据库（如果不存在）
bin/console doctrine:database:create

# 同步表结构（开发环境）
bin/console doctrine:schema:update --dump-sql
bin/console doctrine:schema:update --force
```

###  3. 验证现有升级功能

```bash
# 查看现有 Command
bin/console list commission

# 输出示例：
#   commission:upgrade:batch-check    批量检查升级条件
#   commission:upgrade:init-levels    初始化分销员等级
#   commission:upgrade:validate-rules 验证升级规则表达式

# 测试现有升级服务（可选）
bin/console commission:upgrade:validate-rules
```

---

## 项目结构导览

### 核心目录

```text
packages/commission-upgrade-bundle/
├── src/
│   ├── Controller/Admin/       # 后台控制器
│   │   ├── DistributorLevelUpgradeHistoryCrudController.php
│   │   ├── DistributorLevelUpgradeRuleCrudController.php
│   │   └── ManualUpgradeCrudController.php  ← 待实现
│   │
│   ├── Form/
│   │   └── ManualUpgradeCheckType.php       ← 待实现
│   │
│   ├── Service/
│   │   ├── DistributorUpgradeService.php    ← 核心复用
│   │   ├── UpgradeContextProvider.php        ← 核心复用
│   │   ├── UpgradeExpressionEvaluator.php    ← 核心复用
│   │   └── ManualUpgradeResultFormatter.php  ← 待实现
│   │
│   ├── Entity/
│   │   └── DistributorLevelUpgradeHistory.php ← 需扩展
│   │
│   └── Security/Voter/
│       └── ManualUpgradeVoter.php            ← 待实现
│
└── tests/                       # 镜像 src/ 结构
    ├── Controller/Admin/
    │   └── ManualUpgradeCrudControllerTest.php
    ├── Form/
    │   └── ManualUpgradeCheckTypeTest.php
    ├── Service/
    │   └── ManualUpgradeResultFormatterTest.php
    └── Integration/
        └── ManualUpgradeFlowTest.php
```

### 设计文档

```text
specs/manual-upgrade-check/
├── spec.md                 # 功能规格（已完成）
├── plan.md                 # 实施方案（已完成）
├── research.md             # 技术研究（已完成）
├── data-model.md           # 数据模型（已完成）
├── quickstart.md           # 本文件
├── contracts/              # 服务契约（已完成）
│   ├── manual-upgrade-controller.md
│   └── manual-upgrade-form.md
└── tasks.md                # 任务分解（待生成）
```

---

## TDD 开发流程

### 第一步：编写测试（红）

**示例：ManualUpgradeCheckTypeTest**

```bash
# 创建测试文件
touch tests/Form/ManualUpgradeCheckTypeTest.php

# 运行测试（应失败，因为尚未实现）
vendor/bin/phpunit tests/Form/ManualUpgradeCheckTypeTest.php
```

**测试代码**（参考 `contracts/manual-upgrade-form.md`）：

```php
<?php

namespace Tourze\CommissionUpgradeBundle\Tests\Form;

use Symfony\Component\Form\Test\TypeTestCase;
use Tourze\CommissionUpgradeBundle\DTO\ManualUpgradeCheckRequest;
use Tourze\CommissionUpgradeBundle\Form\ManualUpgradeCheckType;

class ManualUpgradeCheckTypeTest extends TypeTestCase
{
    public function testSubmitValidData(): void
    {
        $formData = ['distributorId' => 12345];
        $form = $this->factory->create(ManualUpgradeCheckType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
    }
}
```

### 第二步：实现代码（绿）

```bash
# 创建实现文件
touch src/Form/ManualUpgradeCheckType.php
touch src/DTO/ManualUpgradeCheckRequest.php

# 实现代码（参考 contracts/manual-upgrade-form.md）
```

### 第三步：运行测试验证

```bash
vendor/bin/phpunit tests/Form/ManualUpgradeCheckTypeTest.php

# 输出示例：
# OK (1 test, 2 assertions)
```

### 第四步：重构与优化

```bash
# 运行质量门
vendor/bin/phpstan analyse -c phpstan.neon src/Form
vendor/bin/php-cs-fixer fix src/Form

# 重新运行测试确保无回归
vendor/bin/phpunit tests/Form/
```

---

## 运行测试

### 单元测试

```bash
# 运行所有测试
vendor/bin/phpunit

# 运行指定目录测试
vendor/bin/phpunit tests/Form
vendor/bin/phpunit tests/Controller

# 运行单个测试文件
vendor/bin/phpunit tests/Form/ManualUpgradeCheckTypeTest.php

# 运行单个测试方法
vendor/bin/phpunit --filter testSubmitValidData tests/Form/ManualUpgradeCheckTypeTest.php
```

### 集成测试

```bash
# 运行集成测试（需要数据库）
vendor/bin/phpunit tests/Integration/ManualUpgradeFlowTest.php

# 使用测试数据库（推荐）
APP_ENV=test bin/console doctrine:database:create --if-not-exists
APP_ENV=test bin/console doctrine:schema:update --force
```

### 测试覆盖率

```bash
# 生成覆盖率报告（需要 Xdebug 或 PCOV）
vendor/bin/phpunit --coverage-html coverage/

# 查看报告
open coverage/index.html
```

---

## 静态分析

### PHPStan

```bash
# 运行 PHPStan（Level 8）
vendor/bin/phpstan analyse -c phpstan.neon packages/commission-upgrade-bundle/src

# 查看基线（已知问题）
cat phpstan-baseline.neon

# 更新基线（谨慎使用）
vendor/bin/phpstan analyse --generate-baseline
```

### 代码格式化

```bash
# 检查格式
vendor/bin/php-cs-fixer fix --dry-run --diff packages/commission-upgrade-bundle/src

# 自动修复
vendor/bin/php-cs-fixer fix packages/commission-upgrade-bundle/src
```

---

## 访问后台功能

### 启动开发服务器

```bash
# 启动 Symfony 服务器
symfony serve

# 或使用 PHP 内置服务器
php -S localhost:8000 -t public
```

### 访问路径

| 功能 | URL |
|------|-----|
| EasyAdmin 后台首页 | `http://localhost:8000/admin` |
| 升级历史列表 | `http://localhost:8000/admin?crudController=DistributorLevelUpgradeHistoryCrudController` |
| 升级规则列表 | `http://localhost:8000/admin?crudController=DistributorLevelUpgradeRuleCrudController` |
| 手动升级检测（待实现） | `http://localhost:8000/admin/manual-upgrade/check` |

### 权限配置

**添加测试用户**（如果需要）：

```bash
# 使用 UserManagerInterface 创建测试用户
bin/console app:create-test-user operator@example.com --roles=ROLE_UPGRADE_OPERATOR
```

**配置 Voter**（在 `config/packages/security.yaml`）：

```yaml
security:
    role_hierarchy:
        ROLE_UPGRADE_OPERATOR: [ROLE_USER]
        ROLE_ADMIN: [ROLE_UPGRADE_OPERATOR]
```

---

## 调试技巧

### 日志查看

```bash
# 实时查看日志
tail -f var/log/dev.log

# 过滤升级相关日志
tail -f var/log/dev.log | grep upgrade
```

### Symfony Profiler

```bash
# 访问 Profiler（开发环境自动启用）
# 在浏览器底部工具栏点击图标

# 或直接访问
http://localhost:8000/_profiler
```

### Doctrine 查询日志

**配置**（`config/packages/doctrine.yaml`）：

```yaml
doctrine:
    dbal:
        logging: true
        profiling: true
```

**查看 SQL 日志**：

```bash
tail -f var/log/doctrine.log
```

---

## 常见问题

### 1. Composer 依赖冲突

**问题**：`composer install` 提示版本冲突

**解决**：

```bash
# 清理缓存
composer clear-cache

# 重新安装
rm -rf vendor composer.lock
composer install
```

### 2. 数据库表不存在

**问题**：访问后台报错 "Table 'xxx' doesn't exist"

**解决**：

```bash
# 同步表结构
bin/console doctrine:schema:update --force

# 验证结构
bin/console doctrine:schema:validate
```

### 3. PHPStan 报错"Class not found"

**问题**：PHPStan 无法识别新创建的类

**解决**：

```bash
# 重新生成 autoload
composer dump-autoload

# 清理 PHPStan 缓存
vendor/bin/phpstan clear-result-cache
```

### 4. 测试失败"Kernel not found"

**问题**：集成测试无法加载 Symfony Kernel

**解决**：

```bash
# 确认测试类继承自 KernelTestCase 或 WebTestCase
# 确认 phpunit.xml.dist 配置正确

# 清理测试缓存
rm -rf var/cache/test
```

---

## 推荐开发流程

### 开发一个新功能（以 ManualUpgradeCrudController 为例）

1. **阅读设计文档**：
   - `spec.md` - 了解业务需求
   - `plan.md` - 了解技术方案
   - `contracts/manual-upgrade-controller.md` - 了解接口契约

2. **编写单元测试**：
   ```bash
   touch tests/Controller/Admin/ManualUpgradeCrudControllerTest.php
   # 编写测试用例（参考契约文档）
   vendor/bin/phpunit tests/Controller/Admin/ManualUpgradeCrudControllerTest.php
   # 预期：FAIL（红）
   ```

3. **实现功能代码**：
   ```bash
   touch src/Controller/Admin/ManualUpgradeCrudController.php
   # 实现 checkAction, resultAction, upgradeAction
   ```

4. **运行测试验证**：
   ```bash
   vendor/bin/phpunit tests/Controller/Admin/ManualUpgradeCrudControllerTest.php
   # 预期：OK（绿）
   ```

5. **运行质量门**：
   ```bash
   vendor/bin/phpstan analyse -c phpstan.neon src/Controller
   vendor/bin/php-cs-fixer fix src/Controller
   vendor/bin/phpunit tests/Controller
   ```

6. **集成测试**：
   ```bash
   # 编写端到端测试
   touch tests/Integration/ManualUpgradeFlowTest.php
   vendor/bin/phpunit tests/Integration/ManualUpgradeFlowTest.php
   ```

7. **手动测试**：
   - 启动服务器
   - 访问后台页面
   - 测试完整流程

8. **提交代码**：
   ```bash
   git add .
   git commit -m "feat(commission-upgrade): 实现手动升级检测功能

   - 新增 ManualUpgradeCrudController（检测、结果、升级三个Action）
   - 新增 ManualUpgradeCheckType 表单
   - 扩展 DistributorLevelUpgradeHistory 实体（trigger_type, operator）
   - 新增单元测试和集成测试

   Refs: packages/commission-upgrade-bundle/specs/manual-upgrade-check/spec.md
   "
   ```

---

## 下一步行动

1. ✅ 完成环境搭建和文档阅读（本指南）
2. → 使用 `/speckit.tasks` 生成任务分解（`tasks.md`）
3. → 按任务列表逐个实现功能（TDD 流程）
4. → 提交代码并创建 Pull Request

---

## 参考资源

### 内部文档

- [Spec 文档](./spec.md) - 功能需求
- [Plan 文档](./plan.md) - 技术方案
- [Data Model](./data-model.md) - 数据模型
- [Contracts](./contracts/) - 服务契约

### 外部文档

- [Symfony 官方文档](https://symfony.com/doc/current/index.html)
- [EasyAdmin 文档](https://symfony.com/bundles/EasyAdminBundle/current/index.html)
- [Doctrine ORM 文档](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/index.html)
- [PHPUnit 文档](https://phpunit.de/documentation.html)
- [PHPStan 文档](https://phpstan.org/user-guide/getting-started)

### 团队规范

- [Monorepo 宪章](../../../.specify/memory/constitution.md)
- [Commit 规范](https://www.conventionalcommits.org/)
- [测试策略](../../../.specify/memory/constitution.md#iii-测试优先)

---

**祝开发愉快！如有问题请联系团队或参考 [Plan 文档](./plan.md) 中的风险缓解措施。**
