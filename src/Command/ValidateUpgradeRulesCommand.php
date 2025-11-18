<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\CommissionUpgradeBundle\Repository\DistributorLevelUpgradeRuleRepository;
use Tourze\CommissionUpgradeBundle\Service\UpgradeExpressionEvaluator;

/**
 * 验证升级规则命令.
 *
 * 检查所有升级规则的表达式语法
 */
#[AsCommand(
    name: 'commission-upgrade:validate-rules',
    description: '验证所有升级规则的表达式语法',
)]
final class ValidateUpgradeRulesCommand extends Command
{
    public function __construct(
        private DistributorLevelUpgradeRuleRepository $ruleRepository,
        private UpgradeExpressionEvaluator $expressionEvaluator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('验证升级规则');

        $rules = $this->ruleRepository->findAll();

        if (0 === \count($rules)) {
            $io->warning('未找到任何升级规则');

            return Command::SUCCESS;
        }

        $io->info(sprintf('找到 %d 条升级规则', \count($rules)));

        $invalidCount = 0;

        foreach ($rules as $rule) {
            $expression = $rule->getUpgradeExpression();

            try {
                $this->expressionEvaluator->validate($expression);

                $io->success(sprintf(
                    '✅ 规则 #%d: %s→%s, 表达式: "%s"',
                    $rule->getId(),
                    $rule->getSourceLevel()->getName(),
                    $rule->getTargetLevel()->getName(),
                    $expression
                ));
            } catch (\InvalidArgumentException $e) {
                ++$invalidCount;

                $io->error(sprintf(
                    '❌ 规则 #%d: %s→%s, 表达式: "%s" (%s)',
                    $rule->getId(),
                    $rule->getSourceLevel()->getName(),
                    $rule->getTargetLevel()->getName(),
                    $expression,
                    $e->getMessage()
                ));
            }
        }

        if ($invalidCount > 0) {
            $io->error(sprintf('发现 %d 条无效规则', $invalidCount));

            return Command::FAILURE;
        }

        $io->success('所有升级规则验证通过');

        return Command::SUCCESS;
    }
}
