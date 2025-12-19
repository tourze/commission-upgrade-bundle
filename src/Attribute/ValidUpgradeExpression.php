<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Attribute;

use Symfony\Component\Validator\Constraint;
use Tourze\CommissionUpgradeBundle\Validator\Constraints\ValidUpgradeExpressionValidator;

/**
 * 升级条件表达式验证约束.
 *
 * 用于验证UpgradeExpression字段的表达式语法和变量合法性
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class ValidUpgradeExpression extends Constraint
{
    public string $message = '表达式验证失败: {{ error }}';

    public function __construct(
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct([], $groups, $payload);

        $this->message = $message ?? $this->message;
    }

    public function validatedBy(): string
    {
        return ValidUpgradeExpressionValidator::class;
    }
}
