<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Tourze\CommissionDistributorBundle\Service\DistributorService;
use Tourze\CommissionUpgradeBundle\Service\DistributorUpgradeService;

/**
 * 手动升级执行控制器 (步骤3: 执行手动升级).
 */
#[IsGranted(attribute: 'ROLE_UPGRADE_OPERATOR')]
final class ManualUpgradeExecuteController extends AbstractController
{
    private const SESSION_KEY = 'manual_upgrade_check_result';

    public function __construct(
        private readonly DistributorUpgradeService $upgradeService,
        private readonly DistributorService $distributorService,
        private readonly UserLoaderInterface $userLoader,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: '/admin/manual-upgrade/upgrade', name: 'admin_manual_upgrade_execute', methods: ['POST'])]
    public function __invoke(Request $request, SessionInterface $session): Response
    {
        // 1. 验证 CSRF Token
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('manual_upgrade_execute', $submittedToken)) {
            $this->addFlash('danger', 'CSRF 验证失败，请重新提交');

            return $this->redirectToRoute('admin_manual_upgrade_check');
        }

        // 2. 检查 Session 数据
        $sessionData = $session->get(self::SESSION_KEY);
        if (null === $sessionData || !isset($sessionData['expires_at']) || $sessionData['expires_at'] < time()) {
            $this->addFlash('warning', '检测结果已过期，请重新检测');

            return $this->redirectToRoute('admin_manual_upgrade_check');
        }

        $resultData = $sessionData['data'];

        // 3. 验证是否满足升级条件
        $canUpgrade = (bool) ($resultData['can_upgrade'] ?? false);
        if (!$canUpgrade) {
            $this->addFlash('danger', '该分销员不满足升级条件');
            $session->remove(self::SESSION_KEY);

            return $this->redirectToRoute('admin_manual_upgrade_check');
        }

        // 4. 从数据库重新加载分销员
        $distributor = $this->distributorService->findById($resultData['distributor_id']);
        if (null === $distributor) {
            $this->addFlash('danger', '分销员不存在');
            $session->remove(self::SESSION_KEY);

            return $this->redirectToRoute('admin_manual_upgrade_check');
        }

        // 5. 获取当前登录的操作人
        $currentUser = $this->getUser();
        $operator = null;
        if (null !== $currentUser) {
            $operator = $this->userLoader->loadUserByIdentifier($currentUser->getUserIdentifier());
        }

        try {
            // 6. 执行升级
            $history = $this->upgradeService->checkAndUpgrade($distributor);

            if (null === $history) {
                // 升级条件不再满足（数据可能已变化）
                $this->addFlash('warning', '当前不满足升级条件，可能数据已发生变化，请重新检测');
                $session->remove(self::SESSION_KEY);

                return $this->redirectToRoute('admin_manual_upgrade_check');
            }

            // 7. 标记为手动升级并记录操作人
            $history->setTriggerType('manual');
            $history->setOperator($operator);
            $this->entityManager->flush();

            // 8. 清除 Session
            $session->remove(self::SESSION_KEY);

            // 9. 记录成功消息并重定向
            $this->addFlash('success', sprintf(
                '升级成功！分销员 #%d 已从 %s 升级至 %s',
                $distributor->getId(),
                $history->getPreviousLevel()->getName(),
                $history->getNewLevel()->getName()
            ));

            return $this->render('@CommissionUpgrade/manual_upgrade/success.html.twig', [
                'distributor_id' => $distributor->getId(),
                'previous_level' => $history->getPreviousLevel()->getName(),
                'new_level' => $history->getNewLevel()->getName(),
                'upgrade_time' => $history->getUpgradeTime()->format('Y-m-d H:i:s'),
                'history_id' => $history->getId(),
            ]);
        } catch (OptimisticLockException $e) {
            // 并发冲突
            $this->addFlash('danger', '升级失败：数据已被其他操作修改，请重新检测后再试');
            $session->remove(self::SESSION_KEY);

            return $this->redirectToRoute('admin_manual_upgrade_check');
        } catch (\Exception $e) {
            // 其他错误
            $this->addFlash('danger', sprintf('升级失败：%s', $e->getMessage()));

            return $this->redirectToRoute('admin_manual_upgrade_result');
        }
    }
}
