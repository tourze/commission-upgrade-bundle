<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\CommissionUpgradeBundle\DTO\ManualUpgradeCheckRequest;

/**
 * @internal
 */
#[CoversClass(ManualUpgradeCheckRequest::class)]
final class ManualUpgradeCheckRequestTest extends TestCase
{
    public function testCanCreateWithDistributorId(): void
    {
        $distributorId = 123;
        $request = new ManualUpgradeCheckRequest($distributorId);

        $this->assertInstanceOf(ManualUpgradeCheckRequest::class, $request);
        $this->assertSame($distributorId, $request->getDistributorId());
    }

    public function testCanSetDistributorId(): void
    {
        $request = new ManualUpgradeCheckRequest();
        $distributorId = 456;

        $result = $request->setDistributorId($distributorId);

        $this->assertSame($request, $result);
        $this->assertSame($distributorId, $request->getDistributorId());
    }

    public function testDefaultDistributorIdIsZero(): void
    {
        $request = new ManualUpgradeCheckRequest();

        $this->assertSame(0, $request->getDistributorId());
    }
}
