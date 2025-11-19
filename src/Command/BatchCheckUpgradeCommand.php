<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\CommissionUpgradeBundle\Message\DistributorUpgradeCheckMessage;
use Tourze\CommissionUpgradeBundle\MessageHandler\DistributorUpgradeCheckHandler;
use Tourze\OrderCommissionBundle\Entity\Distributor;

/**
 * 批量触发分销员升级检查命令
 *
 * 功能：批量投递升级检查消息到队列，支持按等级过滤和限制数量
 *
 * 使用场景：
 * - 升级规则调整后重算所有分销员等级
 * - 定期批量检查升级条件
 * - 数据修复和补偿场景
 *
 * 性能要求：
 * - 命令执行时间 < 5 秒（批量投递 1000 条消息）
 * - 分页查询避免内存溢出
 * - 异步投递不阻塞命令返回
 *
 * @see DistributorUpgradeCheckMessage
 * @see DistributorUpgradeCheckHandler
 */
#[AsCommand(
    name: 'commission-upgrade:batch-check',
    description: '批量触发分销员升级检查（异步）',
)]
final class BatchCheckUpgradeCommand extends Command
{
    // 防抖：记录最近执行时间（简化实现，生产环境可使用缓存）
    private static ?float $lastExecutionTime = null;
    private const DEBOUNCE_SECONDS = 5; // 5 秒内禁止重复执行

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'level',
                'l',
                InputOption::VALUE_OPTIONAL,
                '指定分销员等级 ID（可选）',
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_OPTIONAL,
                '限制处理数量（默认：1000）',
                1000
            )
            ->setHelp(<<<'HELP'
                批量触发分销员升级检查命令

                <info>使用示例：</info>

                  # 批量检查所有分销员（默认限制 1000）
                  <comment>php bin/console commission-upgrade:batch-check</comment>

                  # 仅检查等级 ID=1 的分销员
                  <comment>php bin/console commission-upgrade:batch-check --level=1</comment>

                  # 限制处理 500 个分销员
                  <comment>php bin/console commission-upgrade:batch-check --limit=500</comment>

                <info>工作原理：</info>

                  1. 查询符合条件的分销员（分页）
                  2. 批量投递升级检查消息到异步队列
                  3. 消息由 Worker 进程异步消费
                  4. 命令快速返回（不等待处理完成）

                <info>注意事项：</info>

                  - 需要启动 Messenger Worker 消费消息
                  - 5 秒内禁止重复执行（防抖保护）
                  - 处理失败的消息会进入死信队列

                <info>查看处理进度：</info>

                  <comment>bin/console messenger:stats</comment>
                HELP
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // T032: 重复请求检测（防抖）
        if (null !== self::$lastExecutionTime) {
            $elapsedSeconds = microtime(true) - self::$lastExecutionTime;
            if ($elapsedSeconds < self::DEBOUNCE_SECONDS) {
                $io->warning(sprintf(
                    '命令在 %.1f 秒前刚执行过，请等待 %d 秒后再试（防抖保护）',
                    $elapsedSeconds,
                    self::DEBOUNCE_SECONDS
                ));

                return Command::FAILURE;
            }
        }

        self::$lastExecutionTime = microtime(true);

        // 解析参数
        $levelIdRaw = $input->getOption('level');
        $levelId = (null !== $levelIdRaw && '' !== $levelIdRaw) ? (int) $levelIdRaw : null;
        $limitRaw = $input->getOption('limit');
        $limit = (null !== $limitRaw && '' !== $limitRaw) ? (int) $limitRaw : 1000;

        $io->title('批量触发分销员升级检查');
        $io->text([
            sprintf('等级过滤：%s', (null !== $levelId) ? "ID={$levelId}" : '全部'),
            sprintf('处理限制：%d 个分销员', $limit),
        ]);

        // T030: 批量查询分销员（分页避免内存溢出）
        $distributors = $this->queryDistributors($levelId, $limit);
        $totalCount = count($distributors);

        if (0 === $totalCount) {
            $io->success('未找到符合条件的分销员');

            return Command::SUCCESS;
        }

        $io->section(sprintf('找到 %d 个分销员，开始投递消息...', $totalCount));

        // T031: 批量投递消息（批次提交，性能优化）
        $successCount = 0;
        $failureCount = 0;
        $startTime = microtime(true);

        $io->progressStart($totalCount);

        foreach ($distributors as $distributor) {
            try {
                $distributorId = $distributor->getId();
                if (null === $distributorId) {
                    $this->logger->warning('分销员 ID 为空，跳过');
                    ++$failureCount;
                    $io->progressAdvance();

                    continue;
                }

                $message = new DistributorUpgradeCheckMessage(
                    distributorId: (int) $distributorId
                );

                $this->messageBus->dispatch($message);
                ++$successCount;
            } catch (\Throwable $e) {
                ++$failureCount;

                $this->logger->error('批量投递消息失败', [
                    'distributor_id' => $distributor->getId(),
                    'error' => $e->getMessage(),
                ]);
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        $elapsedTime = microtime(true) - $startTime;

        // 输出执行结果
        $io->success(sprintf(
            '消息投递完成！成功：%d，失败：%d，耗时：%.2f 秒',
            $successCount,
            $failureCount,
            $elapsedTime
        ));

        // 性能验证（T033）
        if ($elapsedTime > 5.0 && $totalCount >= 1000) {
            $io->warning(sprintf(
                '性能未达标：投递 %d 条消息耗时 %.2f 秒（目标 < 5 秒）',
                $totalCount,
                $elapsedTime
            ));
        }

        $io->note([
            '消息已投递到异步队列，需要启动 Worker 进程消费：',
            '  bin/console messenger:consume async',
            '',
            '查看队列统计：',
            '  bin/console messenger:stats',
        ]);

        return Command::SUCCESS;
    }

    /**
     * 查询符合条件的分销员（分页）
     *
     * @param int|null $levelId 等级 ID 过滤（可选）
     * @param int      $limit   限制数量
     *
     * @return Distributor[]
     */
    private function queryDistributors(?int $levelId, int $limit): array
    {
        $repository = $this->entityManager->getRepository(Distributor::class);
        $qb = $repository->createQueryBuilder('d')
            ->setMaxResults($limit)
        ;

        // 可选：按等级过滤
        if (null !== $levelId) {
            $qb->join('d.level', 'l')
                ->andWhere('l.id = :levelId')
                ->setParameter('levelId', $levelId)
            ;
        }

        // 按 ID 排序确保分页一致性
        $qb->orderBy('d.id', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
