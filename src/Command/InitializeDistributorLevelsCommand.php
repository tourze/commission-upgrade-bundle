<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\CommissionDistributorBundle\Entity\Distributor;
use Tourze\CommissionDistributorBundle\Service\DistributorService;
use Tourze\CommissionUpgradeBundle\Service\DistributorUpgradeService;

/**
 * 批量初始化分销员等级命令.
 *
 * 用于系统上线前,基于历史提现数据初始化分销员等级
 */
#[AsCommand(
    name: 'commission-upgrade:initialize-levels',
    description: '批量初始化分销员等级（基于历史提现数据）',
)]
final class InitializeDistributorLevelsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DistributorUpgradeService $upgradeService,
        private readonly DistributorService $distributorService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, '批处理大小', 100)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, '模拟运行（不实际更新数据库）')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batchSizeOption = $input->getOption('batch-size');
        assert(is_numeric($batchSizeOption) || is_string($batchSizeOption));
        $batchSize = (int) $batchSizeOption;

        $dryRunOption = $input->getOption('dry-run');
        $dryRun = (bool) $dryRunOption;

        if ($dryRun) {
            $io->note('模拟运行模式 - 不会实际更新数据库');
        }

        $io->title('批量初始化分销员等级');

        // 获取所有分销员（简化实现，实际应分批处理）
        $distributors = $this->distributorService->findAll();
        $totalCount = \count($distributors);

        if (0 === $totalCount) {
            $io->warning('未找到任何分销员');

            return Command::SUCCESS;
        }

        $io->info(sprintf('找到 %d 个分销员', $totalCount));

        $progressBar = new ProgressBar($output, $totalCount);
        $progressBar->start();

        [$upgradedCount, $errorCount] = $this->processDistributors(
            $distributors,
            $batchSize,
            $dryRun,
            $io,
            $progressBar
        );

        $this->flushFinalBatch($dryRun);

        $progressBar->finish();
        $io->newLine(2);

        $io->success(sprintf(
            '✅ 处理完成：共 %d 个分销员，%d 个升级，%d 个错误',
            $totalCount,
            $upgradedCount,
            $errorCount
        ));

        if ($dryRun) {
            $io->note('这是模拟运行，实际未更新数据库');
        }

        return Command::SUCCESS;
    }

    /**
     * 批量处理所有分销员.
     *
     * @param array<Distributor> $distributors
     *
     * @return array{int, int} [升级数量, 错误数量]
     */
    private function processDistributors(array $distributors, int $batchSize, bool $dryRun, SymfonyStyle $io, ProgressBar $progressBar): array
    {
        $upgradedCount = 0;
        $errorCount = 0;

        foreach ($distributors as $index => $distributor) {
            $result = $this->processDistributor($distributor, $io);

            if ($result['upgraded']) {
                ++$upgradedCount;
                $this->flushBatchIfNeeded($index, $batchSize, $dryRun);
            }

            if ($result['error']) {
                ++$errorCount;
            }

            $progressBar->advance();
        }

        return [$upgradedCount, $errorCount];
    }

    /**
     * 处理单个分销员升级.
     *
     * @return array{upgraded: bool, error: bool}
     */
    private function processDistributor(Distributor $distributor, SymfonyStyle $io): array
    {
        try {
            $history = $this->upgradeService->checkAndUpgrade($distributor);

            return ['upgraded' => null !== $history, 'error' => false];
        } catch (\Throwable $e) {
            $this->handleUpgradeError($distributor, $e, $io);

            return ['upgraded' => false, 'error' => true];
        }
    }

    /**
     * 处理升级错误.
     */
    private function handleUpgradeError(Distributor $distributor, \Throwable $e, SymfonyStyle $io): void
    {
        if ($io->isVerbose()) {
            $io->error(sprintf(
                '分销员 #%d 升级检查失败: %s',
                $distributor->getId(),
                $e->getMessage()
            ));
        }
    }

    /**
     * 根据批次大小决定是否提交.
     */
    private function flushBatchIfNeeded(int $index, int $batchSize, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        if (0 === ($index + 1) % $batchSize) {
            $this->entityManager->flush();
            $this->entityManager->clear();
        }
    }

    /**
     * 提交最终批次.
     */
    private function flushFinalBatch(bool $dryRun): void
    {
        if (!$dryRun) {
            $this->entityManager->flush();
        }
    }
}
