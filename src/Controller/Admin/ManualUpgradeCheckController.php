<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Tourze\CommissionDistributorBundle\Service\DistributorService;
use Tourze\CommissionUpgradeBundle\DTO\ManualUpgradeCheckRequest;
use Tourze\CommissionUpgradeBundle\DTO\ManualUpgradeCheckResult;
use Tourze\CommissionUpgradeBundle\Form\ManualUpgradeCheckType;
use Tourze\CommissionUpgradeBundle\Service\DistributorUpgradeService;

/**
 * 手动升级检测控制器 (步骤1: 检测升级条件).
 */
#[IsGranted(attribute: 'ROLE_UPGRADE_OPERATOR')]
final class ManualUpgradeCheckController extends AbstractController
{
    private const SESSION_KEY = 'manual_upgrade_check_result';
    private const SESSION_TTL = 1800; // 30分钟

    public function __construct(
        private readonly DistributorUpgradeService $upgradeService,
        private readonly DistributorService $distributorService,
    ) {
    }

    #[Route(path: '/admin/manual-upgrade/check', name: 'admin_manual_upgrade_check', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, SessionInterface $session): Response
    {
        $checkRequest = new ManualUpgradeCheckRequest();
        $form = $this->createForm(ManualUpgradeCheckType::class, $checkRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $distributorId = $checkRequest->getDistributorId();

            // 验证分销员是否存在
            $distributor = $this->distributorService->findById($distributorId);
            if (null === $distributor) {
                $this->addFlash('danger', sprintf('分销员 #%d 不存在', $distributorId));

                return $this->redirectToRoute('admin_manual_upgrade_check');
            }

            // 调用升级服务检测条件
            $upgradeResult = $this->upgradeService->checkUpgradeEligibility($distributor);

            if (null === $upgradeResult) {
                // 不满足升级条件
                $currentLevel = $distributor->getLevel();
                $checkResult = new ManualUpgradeCheckResult(
                    distributor: $distributor,
                    currentLevel: $currentLevel,
                    canUpgrade: false,
                    failureReason: '当前已是最高等级或不满足任何升级规则'
                );
            } else {
                // 满足升级条件
                $checkResult = new ManualUpgradeCheckResult(
                    distributor: $distributor,
                    currentLevel: $distributor->getLevel(),
                    canUpgrade: true,
                    targetLevel: $upgradeResult->getNewLevel(),
                    upgradeRule: $upgradeResult->getRule(),
                    context: $upgradeResult->getContextSnapshot()
                );
            }

            // 存储到 Session
            $session->set(self::SESSION_KEY, [
                'data' => $checkResult->toArray(),
                'expires_at' => time() + self::SESSION_TTL,
            ]);

            // 重定向到结果页面
            return $this->redirectToRoute('admin_manual_upgrade_result');
        }

        return $this->render('@CommissionUpgrade/manual_upgrade/check_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
