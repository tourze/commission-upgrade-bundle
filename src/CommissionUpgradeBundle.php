<?php

namespace Tourze\CommissionUpgradeBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\OrderCommissionBundle\OrderCommissionBundle;

class CommissionUpgradeBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            OrderCommissionBundle::class => ['all' => true],
        ];
    }
}
