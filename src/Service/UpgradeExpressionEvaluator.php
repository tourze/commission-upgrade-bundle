<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Node\NameNode;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * 升级条件表达式验证与评估服务.
 *
 * 负责验证表达式语法和变量合法性,并基于上下文执行表达式评估
 */
#[WithMonologChannel(channel: 'commission_upgrade')]
class UpgradeExpressionEvaluator
{
    /**
     * 允许在升级表达式中使用的变量白名单.
     */
    private const ALLOWED_VARIABLES = [
        'withdrawnAmount',    // 已提现佣金总额
        'inviteeCount',       // 邀请人数
        'orderCount',         // 订单数
        'activeInviteeCount', // 活跃邀请人数(30天内有订单)
        'settledCommissionAmount', // 已结算总佣金
    ];

    private ExpressionLanguage $expressionLanguage;

    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->expressionLanguage = new ExpressionLanguage();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * 验证表达式语法和变量合法性.
     *
     * @param string $expression 升级条件表达式
     *
     * @throws \InvalidArgumentException 表达式语法错误或引用了不支持的变量
     */
    public function validate(string $expression): void
    {
        if ('' === trim($expression)) {
            throw new \InvalidArgumentException('表达式不能为空');
        }

        // 1. 验证语法正确性
        try {
            $parsedExpression = $this->expressionLanguage->parse($expression, self::ALLOWED_VARIABLES);
        } catch (SyntaxError $e) {
            throw new \InvalidArgumentException(sprintf('表达式语法错误: %s', $e->getMessage()), 0, $e);
        }

        // 2. 验证变量白名单
        // 递归遍历所有节点，检查变量名
        $invalidVariables = [];
        $this->validateNodeVariables($parsedExpression->getNodes(), $invalidVariables);

        if (\count($invalidVariables) > 0) {
            throw new \InvalidArgumentException(sprintf('变量不在白名单中: %s', implode(', ', array_unique($invalidVariables))));
        }
    }

    /**
     * 执行表达式评估.
     *
     * @param string               $expression 升级条件表达式
     * @param array<string, mixed> $context    上下文变量 (如 ['withdrawnAmount' => 5100, 'inviteeCount' => 12])
     *
     * @return bool true = 满足升级条件, false = 不满足
     *
     * @throws \RuntimeException 表达式执行失败 (如除零、类型不匹配)
     */
    public function evaluate(string $expression, array $context): bool
    {
        try {
            $result = $this->expressionLanguage->evaluate($expression, $context);

            return (bool) $result;
        } catch (\Throwable $e) {
            $this->logger->error('表达式执行失败', [
                'expression' => $expression,
                'context' => $context,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \RuntimeException(sprintf('表达式执行失败: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * 递归验证节点中的变量名.
     *
     * @param array<string> $invalidVariables 收集的无效变量（引用传递）
     */
    private function validateNodeVariables(Node $node, array &$invalidVariables): void
    {
        $this->checkAndCollectInvalidVariable($node, $invalidVariables);
        $this->validateChildNodes($node, $invalidVariables);
    }

    /**
     * 检查并收集无效变量名.
     *
     * @param array<string> $invalidVariables 收集的无效变量（引用传递）
     */
    private function checkAndCollectInvalidVariable(Node $node, array &$invalidVariables): void
    {
        if ($node instanceof NameNode) {
            $varName = $node->attributes['name'];
            if (!\in_array($varName, self::ALLOWED_VARIABLES, true)) {
                $invalidVariables[] = $varName;
            }
        }
    }

    /**
     * 递归验证子节点.
     *
     * @param array<string> $invalidVariables 收集的无效变量（引用传递）
     */
    private function validateChildNodes(Node $node, array &$invalidVariables): void
    {
        $reflection = new \ReflectionClass($node);
        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($node);
            $this->validatePropertyValue($value, $invalidVariables);
        }
    }

    /**
     * 验证属性值中的节点.
     *
     * @param mixed $value 属性值
     * @param array<string> $invalidVariables 收集的无效变量（引用传递）
     */
    private function validatePropertyValue(mixed $value, array &$invalidVariables): void
    {
        if ($value instanceof Node) {
            $this->validateNodeVariables($value, $invalidVariables);

            return;
        }

        if (\is_array($value)) {
            $this->validateArrayItems($value, $invalidVariables);
        }
    }

    /**
     * 验证数组中的节点项.
     *
     * @param array<mixed> $items 数组项
     * @param array<string> $invalidVariables 收集的无效变量（引用传递）
     */
    private function validateArrayItems(array $items, array &$invalidVariables): void
    {
        foreach ($items as $item) {
            if ($item instanceof Node) {
                $this->validateNodeVariables($item, $invalidVariables);
            }
        }
    }

    /**
     * 获取可用变量清单.
     *
     * @return array<string> 可用变量名称数组
     */
    public static function getAllowedVariables(): array
    {
        return self::ALLOWED_VARIABLES;
    }
}
