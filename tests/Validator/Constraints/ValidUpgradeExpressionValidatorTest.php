<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Validator\Constraints;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Tourze\CommissionUpgradeBundle\Attribute\ValidUpgradeExpression;
use Tourze\CommissionUpgradeBundle\Service\UpgradeExpressionEvaluator;
use Tourze\CommissionUpgradeBundle\Validator\Constraints\ValidUpgradeExpressionValidator;

/**
 * @internal
 */
#[CoversClass(ValidUpgradeExpressionValidator::class)]
final class ValidUpgradeExpressionValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ValidUpgradeExpressionValidator(new UpgradeExpressionEvaluator());
    }

    public function testValidatorShouldBeInstantiated(): void
    {
        $this->assertInstanceOf(ValidUpgradeExpressionValidator::class, $this->validator);
        $this->assertInstanceOf(ConstraintValidatorInterface::class, $this->validator);
    }

    public function testValidatorShouldNotBeAbstract(): void
    {
        $reflection = new \ReflectionClass(ValidUpgradeExpressionValidator::class);
        $this->assertFalse($reflection->isAbstract());
    }

    public function testValidateShouldPassForValidExpression(): void
    {
        $constraint = new ValidUpgradeExpression();
        $validExpression = 'withdrawnAmount >= 5000';

        $this->validator->validate($validExpression, $constraint);

        $this->assertCount(0, $this->context->getViolations(), '有效表达式不应产生违规');
    }

    public function testValidateShouldPassForNullValue(): void
    {
        $constraint = new ValidUpgradeExpression();

        $this->validator->validate(null, $constraint);

        $this->assertCount(0, $this->context->getViolations(), 'null 值不应产生违规');
    }

    public function testValidateShouldPassForEmptyString(): void
    {
        $constraint = new ValidUpgradeExpression();

        $this->validator->validate('', $constraint);

        $this->assertCount(0, $this->context->getViolations(), '空字符串不应产生违规');
    }

    public function testValidateShouldFailForInvalidExpression(): void
    {
        $constraint = new ValidUpgradeExpression();
        $invalidExpression = 'withdrawnAmount >='; // 语法错误

        $this->validator->validate($invalidExpression, $constraint);

        // 验证有违规产生,但不检查具体的错误消息内容
        $violations = $this->context->getViolations();
        $this->assertCount(1, $violations);
        $this->assertSame($constraint->message, $violations[0]->getMessageTemplate());
        $this->assertArrayHasKey('{{ error }}', $violations[0]->getParameters());
    }

    public function testValidateShouldFailForInvalidVariable(): void
    {
        $constraint = new ValidUpgradeExpression();
        // 使用一个语法正确但变量不在白名单中的表达式
        // 注意:由于 Symfony Expression Language 会先进行语法验证,
        // 对于未定义的变量可能在语法解析阶段就报错
        // 所以这个测试验证的是表达式验证失败的情况(无论是语法错误还是变量错误)
        $invalidExpression = 'invalidVariable >= 100';

        $this->validator->validate($invalidExpression, $constraint);

        // 验证有违规产生,但不检查具体的错误消息内容
        $violations = $this->context->getViolations();
        $this->assertCount(1, $violations);
        $this->assertSame($constraint->message, $violations[0]->getMessageTemplate());
        $this->assertArrayHasKey('{{ error }}', $violations[0]->getParameters());
    }

    public function testValidateShouldThrowExceptionForInvalidConstraintType(): void
    {
        $invalidConstraint = $this->createMock(\Symfony\Component\Validator\Constraint::class);

        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('withdrawnAmount >= 5000', $invalidConstraint);
    }

    public function testValidateShouldThrowExceptionForNonStringValue(): void
    {
        $constraint = new ValidUpgradeExpression();

        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(123, $constraint);
    }

    public function testValidateShouldPassForComplexValidExpression(): void
    {
        $constraint = new ValidUpgradeExpression();
        $complexExpression = 'withdrawnAmount >= 5000 and inviteeCount >= 10';

        $this->validator->validate($complexExpression, $constraint);

        $this->assertCount(0, $this->context->getViolations(), '复杂有效表达式不应产生违规');
    }

    public function testValidateShouldPassForAllAllowedVariables(): void
    {
        $constraint = new ValidUpgradeExpression();
        $expression = 'withdrawnAmount >= 5000 and inviteeCount >= 10 and orderCount >= 20 and activeInviteeCount >= 5 and settledCommissionAmount >= 3000';

        $this->validator->validate($expression, $constraint);

        $this->assertCount(0, $this->context->getViolations(), '包含所有允许变量的表达式不应产生违规');
    }
}
