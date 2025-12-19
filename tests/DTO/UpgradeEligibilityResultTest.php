<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\DTO\UpgradeEligibilityResult;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeRule;

/**
 * @internal
 */
#[CoversClass(UpgradeEligibilityResult::class)]
final class UpgradeEligibilityResultTest extends TestCase
{

    public function testConstructorShouldInitializeAllProperties(): void
    {
        // 创建源等级
        $sourceLevel = new DistributorLevel();
        $sourceLevel->setName('中级');

        // 创建目标等级
        $newLevel = new DistributorLevel();
        $newLevel->setName('高级');

        // 创建升级规则
        $rule = new DistributorLevelUpgradeRule();
        $rule->setSourceLevel($sourceLevel);
        $rule->setTargetLevel($newLevel);
        $rule->setUpgradeExpression('withdrawnAmount >= 5000');

        // 创建上下文快照
        $contextSnapshot = [
            'withdrawnAmount' => 5100.0,
            'inviteeCount' => 10,
            'orderCount' => 25,
        ];

        // 创建 DTO
        $result = new UpgradeEligibilityResult(
            newLevel: $newLevel,
            rule: $rule,
            contextSnapshot: $contextSnapshot
        );

        // 验证所有 getter 方法
        $this->assertSame($newLevel, $result->getNewLevel());
        $this->assertSame($rule, $result->getRule());
        $this->assertSame($contextSnapshot, $result->getContextSnapshot());
    }

    public function testGetNewLevelShouldReturnCorrectLevel(): void
    {
        $sourceLevel = new DistributorLevel();
        $sourceLevel->setName('初级');

        $newLevel = new DistributorLevel();
        $newLevel->setName('中级');

        $rule = new DistributorLevelUpgradeRule();
        $rule->setSourceLevel($sourceLevel);
        $rule->setTargetLevel($newLevel);
        $rule->setUpgradeExpression('withdrawnAmount >= 3000');

        $result = new UpgradeEligibilityResult(
            newLevel: $newLevel,
            rule: $rule,
            contextSnapshot: ['withdrawnAmount' => 3500.0]
        );

        $this->assertSame($newLevel, $result->getNewLevel());
        $this->assertSame('中级', $result->getNewLevel()->getName());
    }

    public function testGetRuleShouldReturnCorrectRule(): void
    {
        $sourceLevel = new DistributorLevel();
        $sourceLevel->setName('普通');

        $newLevel = new DistributorLevel();
        $newLevel->setName('初级');

        $rule = new DistributorLevelUpgradeRule();
        $rule->setSourceLevel($sourceLevel);
        $rule->setTargetLevel($newLevel);
        $rule->setUpgradeExpression('inviteeCount >= 5');

        $result = new UpgradeEligibilityResult(
            newLevel: $newLevel,
            rule: $rule,
            contextSnapshot: ['inviteeCount' => 6]
        );

        $this->assertSame($rule, $result->getRule());
        $this->assertSame('inviteeCount >= 5', $result->getRule()->getUpgradeExpression());
    }

    public function testGetContextSnapshotShouldReturnCorrectSnapshot(): void
    {
        $sourceLevel = new DistributorLevel();
        $sourceLevel->setName('高级');

        $newLevel = new DistributorLevel();
        $newLevel->setName('VIP');

        $rule = new DistributorLevelUpgradeRule();
        $rule->setSourceLevel($sourceLevel);
        $rule->setTargetLevel($newLevel);
        $rule->setUpgradeExpression('withdrawnAmount >= 10000 and inviteeCount >= 20');

        $contextSnapshot = [
            'withdrawnAmount' => 12000.0,
            'inviteeCount' => 25,
            'orderCount' => 50,
            'activeInviteeCount' => 15,
        ];

        $result = new UpgradeEligibilityResult(
            newLevel: $newLevel,
            rule: $rule,
            contextSnapshot: $contextSnapshot
        );

        $this->assertSame($contextSnapshot, $result->getContextSnapshot());
        $this->assertIsArray($result->getContextSnapshot());
        $this->assertCount(4, $result->getContextSnapshot());
    }

    public function testContextSnapshotShouldPreserveArrayKeys(): void
    {
        $sourceLevel = new DistributorLevel();
        $sourceLevel->setName('游客');

        $newLevel = new DistributorLevel();
        $newLevel->setName('普通');

        $rule = new DistributorLevelUpgradeRule();
        $rule->setSourceLevel($sourceLevel);
        $rule->setTargetLevel($newLevel);
        $rule->setUpgradeExpression('true');

        $contextSnapshot = [
            'withdrawnAmount' => 1000.0,
            'custom_key' => 'custom_value',
            'nested' => ['data' => true],
        ];

        $result = new UpgradeEligibilityResult(
            newLevel: $newLevel,
            rule: $rule,
            contextSnapshot: $contextSnapshot
        );

        $snapshot = $result->getContextSnapshot();
        $this->assertArrayHasKey('withdrawnAmount', $snapshot);
        $this->assertArrayHasKey('custom_key', $snapshot);
        $this->assertArrayHasKey('nested', $snapshot);
        $this->assertSame('custom_value', $snapshot['custom_key']);
        $this->assertSame(['data' => true], $snapshot['nested']);
    }
}
