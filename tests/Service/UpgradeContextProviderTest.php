<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommissionDistributorBundle\Entity\Distributor;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\Service\UpgradeContextProvider;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UpgradeContextProvider::class)]
#[RunTestsInSeparateProcesses]
final class UpgradeContextProviderTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 集成测试初始化
    }

    public function testBuildContextReturnsAllRequiredKeys(): void
    {
        $provider = self::getService(UpgradeContextProvider::class);

        $em = self::getEntityManager();
        $user = $this->createNormalUser('test_user');

        $level = new DistributorLevel();
        $level->setName('初级');
        $em->persist($level);

        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($level);
        $em->persist($distributor);
        $em->flush();

        $context = $provider->buildContext($distributor);

        $this->assertIsArray($context);
        $this->assertArrayHasKey('withdrawnAmount', $context);
        $this->assertArrayHasKey('settledCommissionAmount', $context);
        $this->assertArrayHasKey('inviteeCount', $context);
        $this->assertArrayHasKey('orderCount', $context);
        $this->assertArrayHasKey('activeInviteeCount', $context);
    }

    public function testCalculateWithdrawnAmountReturnsFloat(): void
    {
        $provider = self::getService(UpgradeContextProvider::class);

        $em = self::getEntityManager();
        $user = $this->createNormalUser('test_user_2');

        $level = new DistributorLevel();
        $level->setName('初级');
        $em->persist($level);

        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($level);
        $em->persist($distributor);
        $em->flush();

        $amount = $provider->calculateWithdrawnAmount($distributor);

        $this->assertIsFloat($amount);
        $this->assertGreaterThanOrEqual(0.0, $amount);
    }

    public function testCalculateSettledCommissionAmountReturnsFloat(): void
    {
        $provider = self::getService(UpgradeContextProvider::class);

        $em = self::getEntityManager();
        $user = $this->createNormalUser('test_user_3');

        $level = new DistributorLevel();
        $level->setName('初级');
        $em->persist($level);

        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($level);
        $em->persist($distributor);
        $em->flush();

        $amount = $provider->calculateSettledCommissionAmount($distributor);

        $this->assertIsFloat($amount);
        $this->assertGreaterThanOrEqual(0.0, $amount);
    }

    public function testCalculateInviteeCountReturnsInt(): void
    {
        $provider = self::getService(UpgradeContextProvider::class);

        $em = self::getEntityManager();
        $user = $this->createNormalUser('test_user_4');

        $level = new DistributorLevel();
        $level->setName('初级');
        $em->persist($level);

        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($level);
        $em->persist($distributor);
        $em->flush();

        $count = $provider->calculateInviteeCount($distributor);

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testCalculateOrderCountReturnsInt(): void
    {
        $provider = self::getService(UpgradeContextProvider::class);

        $em = self::getEntityManager();
        $user = $this->createNormalUser('test_user_5');

        $level = new DistributorLevel();
        $level->setName('初级');
        $em->persist($level);

        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($level);
        $em->persist($distributor);
        $em->flush();

        $count = $provider->calculateOrderCount($distributor);

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testCalculateActiveInviteeCountReturnsInt(): void
    {
        $provider = self::getService(UpgradeContextProvider::class);

        $em = self::getEntityManager();
        $user = $this->createNormalUser('test_user_6');

        $level = new DistributorLevel();
        $level->setName('初级');
        $em->persist($level);

        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($level);
        $em->persist($distributor);
        $em->flush();

        $count = $provider->calculateActiveInviteeCount($distributor);

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }
}
