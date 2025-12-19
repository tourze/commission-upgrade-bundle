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
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionLevelBundle\Service\DistributorLevelService;

/**
 * 为现有的 DistributorLevel 实体初始化 level 字段.
 *
 * 此迁移命令用于为已存在但未设置 level 字段的 DistributorLevel 记录设置合适的数值
 */
#[AsCommand(
    name: 'commission-upgrade:migrate-distributor-level-field',
    description: '为现有的 DistributorLevel 实体初始化 level 字段',
)]
final class MigrateDistributorLevelFieldCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DistributorLevelService $distributorLevelService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, '模拟运行（不实际更新数据库）')
            ->addOption('force', 'f', InputOption::VALUE_NONE, '强制更新所有记录（包括已有 level 值的记录）')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');

        if ($dryRun) {
            $io->note('模拟运行模式 - 不会实际更新数据库');
        }

        $io->title('迁移 DistributorLevel level 字段');

        // 查找需要迁移的记录
        $levels = $this->findLevelsToMigrate($force);
        $totalCount = \count($levels);

        if (0 === $totalCount) {
            $io->success('🎉 所有 DistributorLevel 记录的 level 字段已经设置完成');
            return Command::SUCCESS;
        }

        $io->info(sprintf('找到 %d 个需要更新 level 字段的记录', $totalCount));

        if (!$dryRun && !$force) {
            $confirm = $io->confirm(sprintf('确定要更新 %d 个记录吗？', $totalCount));
            if (!$confirm) {
                $io->info('操作已取消');
                return Command::SUCCESS;
            }
        }

        // 按 ID 升序排序，确保先创建的等级获得较小的 level 值
        $levels = $this->sortLevelsByCreationOrder($levels);

        $progressBar = new ProgressBar($output, $totalCount);
        $progressBar->start();

        $updatedCount = $this->migrateLevels($levels, $dryRun, $progressBar, $io);

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $progressBar->finish();
        $io->newLine(2);

        $io->success(sprintf(
            '✅ 迁移完成：共更新 %d 个记录',
            $updatedCount
        ));

        if ($dryRun) {
            $io->note('这是模拟运行，实际未更新数据库');
        }

        return Command::SUCCESS;
    }

    /**
     * 查找需要迁移的 DistributorLevel 记录.
     *
     * @return array<DistributorLevel>
     */
    private function findLevelsToMigrate(bool $force): array
    {
        if ($force) {
            // 强制模式：返回所有记录
            return $this->distributorLevelService->findBy([], ['id' => 'ASC']);
        }

        // 普通模式：只返回 level 字段为 0 或未设置的记录
        return $this->distributorLevelService->findLevelsWithDefaultValue();
    }

    /**
     * 按创建顺序排序等级（通过 ID 排序）.
     *
     * @param array<DistributorLevel> $levels
     * @return array<DistributorLevel>
     */
    private function sortLevelsByCreationOrder(array $levels): array
    {
        usort($levels, function (DistributorLevel $a, DistributorLevel $b) {
            return $a->getId() <=> $b->getId();
        });

        return $levels;
    }

    /**
     * 迁移等级数据.
     *
     * @param array<DistributorLevel> $levels
     */
    private function migrateLevels(array $levels, bool $dryRun, ProgressBar $progressBar, SymfonyStyle $io): int
    {
        $updatedCount = 0;

        foreach ($levels as $index => $level) {
            $newLevelValue = $index + 1; // 从 1 开始分配等级值

            if ($dryRun) {
                $io->writeln(sprintf(
                    '[模拟] 更新等级 "%s" (ID: %d): level = %d',
                    $level->getName(),
                    $level->getId(),
                    $newLevelValue
                ), OutputInterface::VERBOSITY_VERBOSE);
            } else {
                $level->setLevel($newLevelValue);
                $this->entityManager->persist($level);

                if ($io->isVerbose()) {
                    $io->writeln(sprintf(
                        '更新等级 "%s" (ID: %d): level = %d',
                        $level->getName(),
                        $level->getId(),
                        $newLevelValue
                    ));
                }
            }

            ++$updatedCount;
            $progressBar->advance();
        }

        return $updatedCount;
    }
}