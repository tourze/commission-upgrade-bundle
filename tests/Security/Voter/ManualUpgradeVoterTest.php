<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Security\Voter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\CommissionUpgradeBundle\Security\Voter\ManualUpgradeVoter;

/**
 * @internal
 */
#[CoversClass(ManualUpgradeVoter::class)]
final class ManualUpgradeVoterTest extends TestCase
{
    public function testVoterCanBeInstantiated(): void
    {
        $voter = new ManualUpgradeVoter();
        $this->assertInstanceOf(ManualUpgradeVoter::class, $voter);
    }

    public function testVoterIsNotAbstract(): void
    {
        $reflection = new \ReflectionClass(ManualUpgradeVoter::class);
        $this->assertFalse($reflection->isAbstract());
    }
}
