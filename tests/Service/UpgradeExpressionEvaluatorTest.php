<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\CommissionUpgradeBundle\Service\UpgradeExpressionEvaluator;

/**
 * T018: UpgradeExpressionEvaluator 单元测试
 *
 * 测试表达式验证与评估功能
 * @internal
 */
#[CoversClass(UpgradeExpressionEvaluator::class)]
final class UpgradeExpressionEvaluatorTest extends TestCase
{
    private UpgradeExpressionEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new UpgradeExpressionEvaluator();
    }

    /**
     * @test
     * 测试简单条件验证：withdrawnAmount >= 5000
     */
    public function testValidate(): void
    {
        $expression = 'withdrawnAmount >= 5000';

        $this->evaluator->validate($expression);

        $this->assertTrue(true, '简单条件验证应该通过');
    }

    /**
     * @test
     * 测试复杂条件验证：withdrawnAmount >= 10000 and inviteeCount >= 10
     */
    public function itValidatesComplexAndCondition(): void
    {
        $expression = 'withdrawnAmount >= 10000 and inviteeCount >= 10';

        $this->evaluator->validate($expression);

        $this->assertTrue(true, 'AND 复杂条件验证应该通过');
    }

    /**
     * @test
     * 测试 OR 条件验证：withdrawnAmount >= 50000 or inviteeCount >= 50
     */
    public function itValidatesComplexOrCondition(): void
    {
        $expression = 'withdrawnAmount >= 50000 or inviteeCount >= 50';

        $this->evaluator->validate($expression);

        $this->assertTrue(true, 'OR 复杂条件验证应该通过');
    }

    /**
     * @test
     * 测试所有可用变量
     */
    public function itValidatesAllAllowedVariables(): void
    {
        $expression = 'withdrawnAmount >= 1000 and inviteeCount >= 5 and orderCount >= 10 and activeInviteeCount >= 3';

        $this->evaluator->validate($expression);

        $this->assertTrue(true, '所有可用变量的表达式应该通过验证');
    }

    /**
     * @test
     * 测试验证失败：语法错误
     */
    public function itThrowsExceptionForSyntaxError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/语法错误|Syntax error/i');

        $expression = 'withdrawnAmount >= >= 5000'; // 双重比较符

        $this->evaluator->validate($expression);
    }

    /**
     * @test
     * 测试验证失败：非法变量
     */
    public function itThrowsExceptionForIllegalVariable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Symfony ExpressionLanguage 在 parse 阶段就会检查变量是否在允许列表中
        // 所以异常消息会包含 "语法错误" 或 "Variable ... is not valid"
        $this->expectExceptionMessageMatches('/语法错误|Variable.*is not valid/i');

        $expression = 'illegalVariable >= 1000'; // 非法变量

        $this->evaluator->validate($expression);
    }

    /**
     * @test
     * 测试执行评估：条件满足
     */
    public function testEvaluate(): void
    {
        $expression = 'withdrawnAmount >= 5000';
        $context = ['withdrawnAmount' => 6000];

        $result = $this->evaluator->evaluate($expression, $context);

        $this->assertTrue($result, '条件满足时应该返回 true');
    }

    /**
     * @test
     * 测试执行评估：条件不满足
     */
    public function itEvaluatesExpressionWhenConditionNotMet(): void
    {
        $expression = 'withdrawnAmount >= 5000';
        $context = ['withdrawnAmount' => 4500];

        $result = $this->evaluator->evaluate($expression, $context);

        $this->assertFalse($result, '条件不满足时应该返回 false');
    }

    /**
     * @test
     * 测试执行评估：边界值（正好等于）
     */
    public function itEvaluatesExpressionAtBoundaryValue(): void
    {
        $expression = 'withdrawnAmount >= 5000';
        $context = ['withdrawnAmount' => 5000];

        $result = $this->evaluator->evaluate($expression, $context);

        $this->assertTrue($result, '边界值（正好等于）应该返回 true');
    }

    /**
     * @test
     * 测试执行评估：复杂 AND 条件满足
     */
    public function itEvaluatesComplexAndConditionWhenMet(): void
    {
        $expression = 'withdrawnAmount >= 10000 and inviteeCount >= 10';
        $context = [
            'withdrawnAmount' => 12000,
            'inviteeCount' => 15,
        ];

        $result = $this->evaluator->evaluate($expression, $context);

        $this->assertTrue($result, 'AND 条件全部满足时应该返回 true');
    }

    /**
     * @test
     * 测试执行评估：复杂 AND 条件部分满足
     */
    public function itEvaluatesComplexAndConditionWhenPartiallyMet(): void
    {
        $expression = 'withdrawnAmount >= 10000 and inviteeCount >= 10';
        $context = [
            'withdrawnAmount' => 12000,
            'inviteeCount' => 8, // 不满足
        ];

        $result = $this->evaluator->evaluate($expression, $context);

        $this->assertFalse($result, 'AND 条件部分不满足时应该返回 false');
    }

    /**
     * @test
     * 测试执行评估：复杂 OR 条件之一满足
     */
    public function itEvaluatesComplexOrConditionWhenOneMet(): void
    {
        $expression = 'withdrawnAmount >= 50000 or inviteeCount >= 50';
        $context = [
            'withdrawnAmount' => 60000, // 满足
            'inviteeCount' => 30, // 不满足
        ];

        $result = $this->evaluator->evaluate($expression, $context);

        $this->assertTrue($result, 'OR 条件之一满足时应该返回 true');
    }

    /**
     * @test
     * 测试执行失败：除零错误
     */
    public function itHandlesDivisionByZeroError(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/除零|Division by zero/i');

        $expression = 'withdrawnAmount / 0 > 1000';
        $context = ['withdrawnAmount' => 5000];

        $this->evaluator->evaluate($expression, $context);
    }

    /**
     * @test
     * 测试执行失败：类型不匹配
     */
    public function itHandlesTypeMismatchError(): void
    {
        $this->expectException(\RuntimeException::class);

        // 使用一个会真正导致运行时错误的表达式
        $expression = 'withdrawnAmount.invalid_method()';
        $context = ['withdrawnAmount' => 5000];

        $this->evaluator->evaluate($expression, $context);
    }

    /**
     * @test
     * 测试获取可用变量清单
     */
    public function itReturnsAllowedVariables(): void
    {
        $variables = $this->evaluator->getAllowedVariables();

        $this->assertIsArray($variables);
        $this->assertContains('withdrawnAmount', $variables);
        $this->assertContains('inviteeCount', $variables);
        $this->assertContains('orderCount', $variables);
        $this->assertContains('activeInviteeCount', $variables);
        $this->assertCount(4, $variables, '应该只有4个可用变量');
    }
}
