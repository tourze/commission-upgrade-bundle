<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Tourze\CommissionDistributorBundle\Service\DistributorService;

/**
 * 手动升级结果展示控制器 (步骤2: 展示检测结果).
 */
#[IsGranted(attribute: 'ROLE_UPGRADE_OPERATOR')]
final class ManualUpgradeResultController extends AbstractController
{
    private const SESSION_KEY = 'manual_upgrade_check_result';

    public function __construct(
        private readonly DistributorService $distributorService,
    ) {
    }

    #[Route(path: '/admin/manual-upgrade/result', name: 'admin_manual_upgrade_result', methods: ['GET'])]
    public function __invoke(SessionInterface $session): Response
    {
        $sessionData = $session->get(self::SESSION_KEY);

        // 检查 Session 是否存在或已过期
        if (null === $sessionData || !isset($sessionData['expires_at']) || $sessionData['expires_at'] < time()) {
            $this->addFlash('warning', '检测结果已过期，请重新检测');

            return $this->redirectToRoute('admin_manual_upgrade_check');
        }

        $resultData = $sessionData['data'];

        // 从数据库重新加载实体
        $distributor = $this->distributorService->findById($resultData['distributor_id']);
        if (null === $distributor) {
            $this->addFlash('danger', '分销员不存在');
            $session->remove(self::SESSION_KEY);

            return $this->redirectToRoute('admin_manual_upgrade_check');
        }

        $canUpgrade = (bool) ($resultData['can_upgrade'] ?? false);

        return $this->render('@CommissionUpgrade/manual_upgrade/result.html.twig', [
            'result_data' => $resultData,
            'distributor' => $distributor,
            'can_upgrade' => $canUpgrade,
            'summary' => $canUpgrade
                ? sprintf('满足升级条件，可从 %s 升级至 %s', $resultData['current_level_name'], $resultData['target_level_name'])
                : sprintf('不满足升级条件: %s', $resultData['failure_reason']),
        ]);
    }
}
