# commission-upgrade-bundle

[English](README.md) | [中文](README.zh-CN.md)



## 安装

```bash
composer require tourze/commission-upgrade-bundle
```

## 使用方法

### 命令行工具

#### commission-upgrade:batch-check

批量触发分销员升级检查（异步消息模式）。

```bash
# 检查所有分销员
bin/console commission-upgrade:batch-check

# 检查指定等级的分销员
bin/console commission-upgrade:batch-check --level=2

# 限制处理数量
bin/console commission-upgrade:batch-check --limit=500
```

#### commission-upgrade:initialize-levels

批量初始化分销员等级（基于历史提现数据）。

```bash
# 初始化所有分销员等级
bin/console commission-upgrade:initialize-levels

# 指定批次大小
bin/console commission-upgrade:initialize-levels --batch-size=200

# 模拟运行（不实际更新数据库）
bin/console commission-upgrade:initialize-levels --dry-run
```

#### commission-upgrade:validate-rules

验证升级规则配置的有效性。

```bash
# 验证所有升级规则
bin/console commission-upgrade:validate-rules
```

#### commission-upgrade:migrate-distributor-level-field

为现有的 DistributorLevel 实体初始化 level 字段。

```bash
# 迁移 level 字段（交互式确认）
bin/console commission-upgrade:migrate-distributor-level-field

# 模拟运行（不实际更新数据库）
bin/console commission-upgrade:migrate-distributor-level-field --dry-run

# 强制更新所有记录（包括已有 level 值的记录）
bin/console commission-upgrade:migrate-distributor-level-field --force
```

### PHP API

```php
<?php

use Tourze\CommissionUpgradeBundle\Service\DistributorUpgradeService;

// 注入服务
public function __construct(
    private DistributorUpgradeService $upgradeService
) {}

// 检查并升级分销员
$history = $this->upgradeService->checkAndUpgrade($distributor);

if ($history !== null) {
    echo "分销员已升级至等级：{$history->getTargetLevel()->getName()}";
}
```

## 配置

在您的应用程序中添加配置。

## 示例

查看 examples 目录以获取完整的使用示例。

## 参考文档

- [文档](docs/)
- [API 参考](docs/api.md)
- [更新日志](CHANGELOG.md)
