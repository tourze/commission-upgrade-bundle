<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\CommissionUpgradeBundle\Attribute\ValidUpgradeExpression;
use Tourze\CommissionUpgradeBundle\Validator\Constraints\ValidUpgradeExpressionValidator;

/**
 * @internal
 */
#[CoversClass(ValidUpgradeExpression::class)]
final class ValidUpgradeExpressionTest extends TestCase
{
    public function testConstraintCanBeInstantiated(): void
    {
        $constraint = new ValidUpgradeExpression();
        $this->assertInstanceOf(ValidUpgradeExpression::class, $constraint);
    }

    public function testConstraintIsNotAbstract(): void
    {
        $reflection = new \ReflectionClass(ValidUpgradeExpression::class);
        $this->assertFalse($reflection->isAbstract());
    }

    public function testValidatedByReturnsCorrectValidatorClass(): void
    {
        $constraint = new ValidUpgradeExpression();
        $this->assertSame(
            ValidUpgradeExpressionValidator::class,
            $constraint->validatedBy(),
            'validatedBy() 必须返回正确的验证器类名'
        );
    }
}
