<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 手动升级权限控制投票器.
 *
 * 控制哪些用户可以执行手动升级操作
 */
class ManualUpgradeVoter extends Voter
{
    public const CHECK = 'MANUAL_UPGRADE_CHECK';
    public const EXECUTE = 'MANUAL_UPGRADE_EXECUTE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::CHECK, self::EXECUTE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // 用户必须已登录
        if (!$user instanceof UserInterface) {
            return false;
        }

        // 检查用户是否具有 ROLE_UPGRADE_OPERATOR 角色
        return match ($attribute) {
            self::CHECK, self::EXECUTE => $this->canPerformManualUpgrade($token),
            default => false,
        };
    }

    private function canPerformManualUpgrade(TokenInterface $token): bool
    {
        // 检查用户是否具有 ROLE_UPGRADE_OPERATOR 或 ROLE_ADMIN 角色
        $roles = $token->getRoleNames();

        return \in_array('ROLE_UPGRADE_OPERATOR', $roles, true)
            || \in_array('ROLE_ADMIN', $roles, true);
    }
}
