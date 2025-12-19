<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * 手动升级检测请求 DTO.
 *
 * 用于接收前端输入的分销员ID,用于检测升级条件
 */
class ManualUpgradeCheckRequest
{
    #[Assert\NotBlank(message: '分销员ID不能为空')]
    #[Assert\Type(type: 'int', message: '分销员ID必须是整数')]
    #[Assert\Positive(message: '分销员ID必须大于0')]
    private int $distributorId;

    public function __construct(int $distributorId = 0)
    {
        $this->distributorId = $distributorId;
    }

    public function getDistributorId(): int
    {
        return $this->distributorId;
    }

    public function setDistributorId(int $distributorId): self
    {
        $this->distributorId = $distributorId;

        return $this;
    }
}
