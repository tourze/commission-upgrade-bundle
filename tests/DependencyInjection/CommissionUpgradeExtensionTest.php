<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\CommissionUpgradeBundle\DependencyInjection\CommissionUpgradeExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(CommissionUpgradeExtension::class)]
final class CommissionUpgradeExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
}
