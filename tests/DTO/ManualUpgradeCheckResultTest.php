<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\CommissionDistributorBundle\Entity\Distributor;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\DTO\ManualUpgradeCheckResult;

/**
 * @internal
 */
#[CoversClass(ManualUpgradeCheckResult::class)]
final class ManualUpgradeCheckResultTest extends TestCase
{

    public function testCanCreateUpgradeableResult(): void
    {
        $currentLevel = new DistributorLevel();
        $currentLevel->setName('初级');
        $currentLevel->setLevel(1);

        $targetLevel = new DistributorLevel();
        $targetLevel->setName('中级');
        $targetLevel->setLevel(2);

        $user = $this->createTestUser('test-user-001');
        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($currentLevel);

        $context = ['withdrawnAmount' => 1000.0];
        $result = new ManualUpgradeCheckResult(
            distributor: $distributor,
            currentLevel: $currentLevel,
            canUpgrade: true,
            targetLevel: $targetLevel,
            context: $context
        );

        $this->assertTrue($result->canUpgrade());
        $this->assertSame($distributor, $result->getDistributor());
        $this->assertSame($currentLevel, $result->getCurrentLevel());
        $this->assertSame($targetLevel, $result->getTargetLevel());
        $this->assertSame($context, $result->getContext());
        $this->assertInstanceOf(\DateTimeImmutable::class, $result->getCheckTime());
        $this->assertNull($result->getFailureReason());
    }

    public function testCanCreateNonUpgradeableResult(): void
    {
        $currentLevel = new DistributorLevel();
        $currentLevel->setName('初级');
        $currentLevel->setLevel(1);

        $user = $this->createTestUser('test-user-002');
        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($currentLevel);

        $failureReason = '不满足提现金额要求';
        $result = new ManualUpgradeCheckResult(
            distributor: $distributor,
            currentLevel: $currentLevel,
            canUpgrade: false,
            failureReason: $failureReason
        );

        $this->assertFalse($result->canUpgrade());
        $this->assertNull($result->getTargetLevel());
        $this->assertSame($failureReason, $result->getFailureReason());
    }

    public function testToArrayConvertsCorrectly(): void
    {
        $currentLevel = new DistributorLevel();
        $currentLevel->setName('初级');
        $currentLevel->setLevel(1);
        $this->setEntityId($currentLevel, '1');

        $user = $this->createTestUser('test-user-003');
        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($currentLevel);
        $this->setEntityId($distributor, '100');

        $result = new ManualUpgradeCheckResult(
            distributor: $distributor,
            currentLevel: $currentLevel,
            canUpgrade: false
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('distributor_id', $array);
        $this->assertArrayHasKey('current_level_id', $array);
        $this->assertArrayHasKey('current_level_name', $array);
        $this->assertArrayHasKey('can_upgrade', $array);
        $this->assertSame('100', $array['distributor_id']);
        $this->assertSame('初级', $array['current_level_name']);
        $this->assertFalse($array['can_upgrade']);
    }

    /**
     * 创建测试用户对象.
     */
    private function createTestUser(string $identifier): object
    {
        return new class($identifier) implements \Symfony\Component\Security\Core\User\UserInterface {
            public function __construct(private readonly string $identifier)
            {
            }

            public function getUserIdentifier(): string
            {
                return $this->identifier;
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }
        };
    }

    /**
     * 使用反射设置实体ID（仅用于测试）.
     */
    private function setEntityId(object $entity, string $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }
}
