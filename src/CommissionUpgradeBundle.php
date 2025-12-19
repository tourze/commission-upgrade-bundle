<?php

namespace Tourze\CommissionUpgradeBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\CommissionDistributorBundle\CommissionDistributorBundle;
use Tourze\CommissionLedgerBundle\CommissionLedgerBundle;
use Tourze\CommissionLevelBundle\CommissionLevelBundle;
use Tourze\CommissionWithdrawBundle\CommissionWithdrawBundle;
use Tourze\DoctrineSnowflakeBundle\DoctrineSnowflakeBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\EcolBundle\EcolBundle;
use Tourze\OrderCommissionBundle\OrderCommissionBundle;

class CommissionUpgradeBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            OrderCommissionBundle::class => ['all' => true],
            CommissionLevelBundle::class => ['all' => true],
            CommissionDistributorBundle::class => ['all' => true],
            CommissionWithdrawBundle::class => ['all' => true],
            CommissionLedgerBundle::class => ['all' => true],
            EcolBundle::class => ['all' => true],
            DoctrineSnowflakeBundle::class => ['all' => true],
            DoctrineTimestampBundle::class => ['all' => true],
        ];
    }
}
