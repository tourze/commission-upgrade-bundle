<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommissionDistributorBundle\Entity\Distributor;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\DTO\ManualUpgradeCheckResult;
use Tourze\CommissionUpgradeBundle\Service\ManualUpgradeResultFormatter;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ManualUpgradeResultFormatter::class)]
#[RunTestsInSeparateProcesses]
final class ManualUpgradeResultFormatterTest extends AbstractIntegrationTestCase
{
    private ManualUpgradeResultFormatter $formatter;

    protected function onSetUp(): void
    {
        $this->formatter = self::getService(ManualUpgradeResultFormatter::class);
    }

    public function testFormatSummaryForUpgradeableResult(): void
    {

        $em = self::getEntityManager();
        $user = $this->createNormalUser('test_user');

        $currentLevel = new DistributorLevel();
        $currentLevel->setName('初级');
        $em->persist($currentLevel);

        $targetLevel = new DistributorLevel();
        $targetLevel->setName('中级');
        $em->persist($targetLevel);

        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($currentLevel);
        $em->persist($distributor);
        $em->flush();

        $result = new ManualUpgradeCheckResult(
            distributor: $distributor,
            currentLevel: $currentLevel,
            canUpgrade: true,
            targetLevel: $targetLevel
        );

        $summary = $this->formatter->formatSummary($result);

        $this->assertIsString($summary);
        $this->assertStringContainsString('初级', $summary);
        $this->assertStringContainsString('中级', $summary);
        $this->assertStringContainsString('满足升级条件', $summary);
    }

    public function testFormatSummaryForNonUpgradeableResult(): void
    {
        $em = self::getEntityManager();
        $user = $this->createNormalUser('test_user_2');

        $currentLevel = new DistributorLevel();
        $currentLevel->setName('初级');
        $em->persist($currentLevel);

        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($currentLevel);
        $em->persist($distributor);
        $em->flush();

        $result = new ManualUpgradeCheckResult(
            distributor: $distributor,
            currentLevel: $currentLevel,
            canUpgrade: false,
            failureReason: '不满足提现金额要求'
        );

        $summary = $this->formatter->formatSummary($result);

        $this->assertIsString($summary);
        $this->assertStringContainsString('初级', $summary);
        $this->assertStringContainsString('不满足提现金额要求', $summary);
    }

    public function testFormatContextReturnsFormattedArray(): void
    {

        $em = self::getEntityManager();
        $user = $this->createNormalUser('test_user_3');

        $currentLevel = new DistributorLevel();
        $currentLevel->setName('初级');
        $em->persist($currentLevel);

        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($currentLevel);
        $em->persist($distributor);
        $em->flush();

        $context = [
            'total_commission' => 1500.50,
            'total_orders' => 25,
            'direct_referrals' => 10,
        ];

        $result = new ManualUpgradeCheckResult(
            distributor: $distributor,
            currentLevel: $currentLevel,
            canUpgrade: false,
            context: $context
        );

        $formatted = $this->formatter->formatContext($result);

        $this->assertIsArray($formatted);
        $this->assertArrayHasKey('累计佣金', $formatted);
        $this->assertArrayHasKey('累计订单数', $formatted);
        $this->assertArrayHasKey('直接推荐人数', $formatted);
        $this->assertSame('1,500.50', $formatted['累计佣金']);
        $this->assertSame('25.00', $formatted['累计订单数']);
        $this->assertSame('10.00', $formatted['直接推荐人数']);
    }

    public function testGenerateHtmlReportProducesValidHtml(): void
    {

        $em = self::getEntityManager();
        $user = $this->createNormalUser('test_user_4');

        $currentLevel = new DistributorLevel();
        $currentLevel->setName('初级');
        $em->persist($currentLevel);

        $targetLevel = new DistributorLevel();
        $targetLevel->setName('中级');
        $em->persist($targetLevel);

        $distributor = new Distributor();
        $distributor->setUser($user);
        $distributor->setLevel($currentLevel);
        $em->persist($distributor);
        $em->flush();

        $result = new ManualUpgradeCheckResult(
            distributor: $distributor,
            currentLevel: $currentLevel,
            canUpgrade: true,
            targetLevel: $targetLevel,
            context: ['total_commission' => 2000.0]
        );

        $html = $this->formatter->generateHtmlReport($result);

        $this->assertIsString($html);
        $this->assertStringContainsString('<div', $html);
        $this->assertStringContainsString('初级', $html);
        $this->assertStringContainsString('中级', $html);
        $this->assertStringContainsString('满足条件', $html);
    }
}
