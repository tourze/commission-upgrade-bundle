<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Message;

use Tourze\AsyncContracts\AsyncMessageInterface;
use Tourze\CommissionUpgradeBundle\MessageHandler\DistributorUpgradeCheckHandler;

/**
 * 分销员升级检查异步消息
 *
 * 功能：将分销员升级检查请求封装为异步消息，通过 Symfony Messenger 传递到队列
 *
 * 设计约束：
 * - 实现 AsyncMessageInterface 标记接口（Symfony Messenger 路由识别）
 * - 不可变（readonly 属性）确保消息在队列传递过程中内容一致
 * - 仅包含标量类型（string）确保序列化兼容性
 * - 仅传递 ID 而非完整实体，避免序列化开销和状态过期风险
 *
 * @see AsyncMessageInterface
 * @see DistributorUpgradeCheckHandler
 */
final readonly class DistributorUpgradeCheckMessage implements AsyncMessageInterface
{
    /**
     * @param string $distributorId 分销员 ID（必填，雪花ID字符串）
     *
     * @throws \InvalidArgumentException 当分销员 ID 为空时
     */
    public function __construct(
        public string $distributorId,
    ) {
        if ('' === $distributorId || '0' === $distributorId) {
            throw new \InvalidArgumentException('Distributor ID must not be empty');
        }
    }
}
