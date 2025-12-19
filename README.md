# commission-upgrade-bundle

[English](README.md) | [中文](README.zh-CN.md)

[English](README.md) | [-�](README.zh-CN.md)

## Installation

```bash
composer require tourze/commission-upgrade-bundle
```

## Usage

### CLI Commands

#### commission-upgrade:batch-check

Batch trigger distributor upgrade checks (async message mode).

```bash
# Check all distributors
bin/console commission-upgrade:batch-check

# Check distributors of specific level
bin/console commission-upgrade:batch-check --level=2

# Limit processing count
bin/console commission-upgrade:batch-check --limit=500
```

#### commission-upgrade:initialize-levels

Batch initialize distributor levels (based on historical withdrawal data).

```bash
# Initialize all distributor levels
bin/console commission-upgrade:initialize-levels

# Specify batch size
bin/console commission-upgrade:initialize-levels --batch-size=200

# Dry run (do not actually update database)
bin/console commission-upgrade:initialize-levels --dry-run
```

#### commission-upgrade:validate-rules

Validate upgrade rule configuration.

```bash
# Validate all upgrade rules
bin/console commission-upgrade:validate-rules
```

#### commission-upgrade:migrate-distributor-level-field

Initialize level field for existing DistributorLevel entities.

```bash
# Migrate level field (interactive confirmation)
bin/console commission-upgrade:migrate-distributor-level-field

# Dry run (do not actually update database)
bin/console commission-upgrade:migrate-distributor-level-field --dry-run

# Force update all records (including those with existing level values)
bin/console commission-upgrade:migrate-distributor-level-field --force
```

### PHP API

```php
<?php

use Tourze\CommissionUpgradeBundle\Service\DistributorUpgradeService;

// Inject service
public function __construct(
    private DistributorUpgradeService $upgradeService
) {}

// Check and upgrade distributor
$history = $this->upgradeService->checkAndUpgrade($distributor);

if ($history !== null) {
    echo "Distributor upgraded to level: {$history->getTargetLevel()->getName()}";
}
```

## Configuration

Add configuration in your application.

## Examples

See the examples directory for complete usage examples.

## References

- [Documentation](docs/)
- [API Reference](docs/api.md)
- [Changelog](CHANGELOG.md)
