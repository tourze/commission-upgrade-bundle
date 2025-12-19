<?php

declare(strict_types=1);

namespace Tourze\CommissionUpgradeBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\CommissionLevelBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\Entity\DirectUpgradeRule;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * 直升规则仓储.
 * 
 * @extends ServiceEntityRepository<DirectUpgradeRule>
 */
#[Autoconfigure(public: true)]
#[AsRepository(entityClass: DirectUpgradeRule::class)]
class DirectUpgradeRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DirectUpgradeRule::class);
    }

    /**
     * 查找适用于指定分销员的所有直升规则.
     * 
     * 返回所有目标等级高于当前等级且满足最低等级要求的启用规则
     * 按优先级降序排列（优先级高的先检查）
     * 
     * @param DistributorLevel $currentLevel 分销员当前等级
     * @return DirectUpgradeRule[] 按优先级降序排列的直升规则
     */
    public function findEligibleRulesForLevel(DistributorLevel $currentLevel): array
    {
        $qb = $this->createQueryBuilder('rule')
            ->join('rule.targetLevel', 'target')
            ->where('rule.isEnabled = :enabled')
            ->andWhere('target.level > :currentLevelValue')
            ->setParameter('enabled', true)
            ->setParameter('currentLevelValue', $currentLevel->getLevel())
        ;

        // 添加最低等级要求的条件
        $qb->andWhere(
            $qb->expr()->orX(
                'rule.minLevelRequirement IS NULL',
                'rule.minLevelRequirement <= :currentLevelValue'
            )
        );

        $qb->orderBy('rule.priority', 'DESC')  // 按优先级降序
            ->addOrderBy('target.level', 'DESC')  // 相同优先级时按目标等级降序
            ->addOrderBy('rule.id', 'ASC')  // 确保排序稳定
        ;

        /** @var array<DirectUpgradeRule> */
        return $qb->getQuery()->getResult();
    }

    /**
     * 查找指定目标等级的直升规则.
     * 
     * @param DistributorLevel $targetLevel 目标等级
     * @return DirectUpgradeRule|null 对应的直升规则
     */
    public function findByTargetLevel(DistributorLevel $targetLevel): ?DirectUpgradeRule
    {
        return $this->findOneBy([
            'targetLevel' => $targetLevel,
            'isEnabled' => true,
        ]);
    }

    /**
     * 查找所有启用的直升规则.
     * 
     * 按目标等级降序排列
     * 
     * @return DirectUpgradeRule[] 按目标等级降序排列的直升规则
     */
    public function findAllEnabled(): array
    {
        /** @var array<DirectUpgradeRule> */
        return $this->createQueryBuilder('rule')
            ->join('rule.targetLevel', 'target')
            ->where('rule.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('target.level', 'DESC')
            ->addOrderBy('rule.priority', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找最高优先级的直升规则.
     * 
     * 用于快速检查是否有直升规则比常规升级更优
     * 
     * @param DistributorLevel $currentLevel 当前等级
     * @return DirectUpgradeRule|null 最高优先级的适用直升规则
     */
    public function findHighestPriorityRule(DistributorLevel $currentLevel): ?DirectUpgradeRule
    {
        $rules = $this->findEligibleRulesForLevel($currentLevel);

        return [] !== $rules ? $rules[0] : null;
    }

    /**
     * 检查是否存在到指定等级的直升规则.
     */
    public function hasDirectRuleToLevel(DistributorLevel $targetLevel): bool
    {
        return null !== $this->findByTargetLevel($targetLevel);
    }

    public function save(DirectUpgradeRule $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DirectUpgradeRule $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}