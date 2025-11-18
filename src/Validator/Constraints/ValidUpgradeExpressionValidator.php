<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Tourze\CommissionUpgradeBundle\Service\UpgradeExpressionEvaluator;

/**
 * 升级条件表达式验证器.
 *
 * 验证表达式语法正确性和变量合法性
 */
final class ValidUpgradeExpressionValidator extends ConstraintValidator
{
    public function __construct(
        private UpgradeExpressionEvaluator $expressionEvaluator,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidUpgradeExpression) {
            throw new UnexpectedTypeException($constraint, ValidUpgradeExpression::class);
        }

        // 允许null和空字符串（由NotBlank约束处理）
        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        try {
            $this->expressionEvaluator->validate($value);
        } catch (\InvalidArgumentException $e) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ error }}', $e->getMessage())
                ->addViolation()
            ;
        }
    }
}
