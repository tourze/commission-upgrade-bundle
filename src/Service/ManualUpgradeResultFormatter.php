<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\CommissionUpgradeBundle\DTO\ManualUpgradeCheckResult;

/**
 * 手动升级结果格式化服务.
 *
 * 负责将检测结果格式化为用户友好的展示内容
 */
#[Autoconfigure(public: true)]
class ManualUpgradeResultFormatter
{
    /**
     * 格式化检测结果为可读的文本摘要.
     */
    public function formatSummary(ManualUpgradeCheckResult $result): string
    {
        if ($result->canUpgrade()) {
            return sprintf(
                '分销员 #%d 当前等级：%s，满足升级条件，可升级至：%s',
                $result->getDistributor()->getId(),
                $result->getCurrentLevel()->getName(),
                $result->getTargetLevel()?->getName() ?? '未知'
            );
        }

        $reason = $result->getFailureReason() ?? '不满足升级条件';

        return sprintf(
            '分销员 #%d 当前等级：%s，%s',
            $result->getDistributor()->getId(),
            $result->getCurrentLevel()->getName(),
            $reason
        );
    }

    /**
     * 格式化上下文变量为可读的键值对数组.
     *
     * @return array<string, string>
     */
    public function formatContext(ManualUpgradeCheckResult $result): array
    {
        $context = $result->getContext();
        $formatted = [];

        $labels = [
            'total_commission' => '累计佣金',
            'total_orders' => '累计订单数',
            'direct_referrals' => '直接推荐人数',
            'team_members' => '团队成员数',
            'monthly_sales' => '月度销售额',
        ];

        foreach ($context as $key => $value) {
            $label = $labels[$key] ?? $key;
            $formatted[$label] = $this->formatValue($value);
        }

        return $formatted;
    }

    /**
     * 格式化单个值.
     */
    private function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '是' : '否';
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 2);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    /**
     * 生成详细的HTML报告.
     */
    public function generateHtmlReport(ManualUpgradeCheckResult $result): string
    {
        $context = $this->formatContext($result);
        $contextHtml = '';

        foreach ($context as $label => $value) {
            $contextHtml .= sprintf(
                '<tr><td>%s</td><td>%s</td></tr>',
                htmlspecialchars($label, \ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($value, \ENT_QUOTES, 'UTF-8')
            );
        }

        $statusClass = $result->canUpgrade() ? 'success' : 'warning';
        $statusText = $result->canUpgrade() ? '✓ 满足条件' : '✗ 不满足条件';

        return sprintf(
            '<div class="upgrade-check-result alert alert-%s">
                <h4>检测结果</h4>
                <p><strong>状态：</strong>%s</p>
                <p><strong>当前等级：</strong>%s</p>
                %s
                <h5>检测数据：</h5>
                <table class="table table-sm">
                    <tbody>%s</tbody>
                </table>
                <p class="text-muted"><small>检测时间：%s</small></p>
            </div>',
            $statusClass,
            $statusText,
            htmlspecialchars($result->getCurrentLevel()->getName(), \ENT_QUOTES, 'UTF-8'),
            $result->canUpgrade()
                ? sprintf('<p><strong>目标等级：</strong>%s</p>', htmlspecialchars($result->getTargetLevel()?->getName() ?? '', \ENT_QUOTES, 'UTF-8'))
                : sprintf('<p><strong>原因：</strong>%s</p>', htmlspecialchars($result->getFailureReason() ?? '', \ENT_QUOTES, 'UTF-8')),
            $contextHtml,
            $result->getCheckTime()->format('Y-m-d H:i:s')
        );
    }
}
