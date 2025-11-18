# Phase 0：技术研究 - 分销员自动升级

**Feature**: `distributor-auto-upgrade` | **Scope**: `packages/commission-upgrade-bundle` | **日期**: 2025-11-17

## 研究目标

为实现基于 Symfony Expression Language 的分销员等级自动升级功能，需研究以下技术决策：

1. **Symfony Expression Language 集成策略**：如何在 Symfony Bundle 中使用表达式引擎评估升级条件
2. **EasyAdmin 自定义字段类型**：如何创建支持表单 UI 和代码编辑器的混合编辑模式
3. **Monaco Editor 集成方案**：如何在 EasyAdmin 中嵌入 Monaco Editor 实现语法高亮
4. **可用变量实现模式**：如何设计可扩展的上下文变量系统（withdrawnAmount, inviteeCount 等）
5. **事件监听器架构**：如何监听 OrderCommissionBundle 的 WithdrawLedger 事件并执行升级逻辑

---

## 1. Symfony Expression Language 集成策略

### 研究问题

- 如何安全地执行用户配置的表达式？
- 如何定义可用变量清单并验证表达式仅引用支持的变量？
- 如何处理表达式执行时的运行时错误？

### 技术选型

**选择**：Symfony Expression Language（`symfony/expression-language`）

**理由**：
- Symfony 官方组件，与现有技术栈一致
- 支持安全的表达式评估（沙箱模式）
- 内置语法解析器和验证机制
- 支持自定义变量和函数
- 性能优秀（可缓存编译后的表达式）

### 实施方案

#### 1.1 安装依赖

```bash
# 在 commission-upgrade-bundle/composer.json 添加
composer require symfony/expression-language:^7.3
```

#### 1.2 表达式服务设计

创建 `UpgradeExpressionEvaluator` 服务：

```php
namespace Tourze\CommissionUpgradeBundle\Service;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class UpgradeExpressionEvaluator
{
    private ExpressionLanguage $expressionLanguage;

    /** @var array<string> 可用变量清单 */
    private const ALLOWED_VARIABLES = [
        'withdrawnAmount',   // 已提现佣金总额
        'inviteeCount',      // 邀请人数
        'orderCount',        // 订单数
        'activeInviteeCount' // 活跃邀请人数
    ];

    public function __construct()
    {
        $this->expressionLanguage = new ExpressionLanguage();
    }

    /**
     * 验证表达式语法和变量合法性
     *
     * @throws \InvalidArgumentException 表达式无效时抛出
     */
    public function validate(string $expression): void
    {
        try {
            // 解析表达式
            $ast = $this->expressionLanguage->parse($expression, self::ALLOWED_VARIABLES);

            // 检查是否引用了未授权的变量
            $usedVariables = $this->extractVariables($ast);
            $illegalVariables = array_diff($usedVariables, self::ALLOWED_VARIABLES);

            if (!empty($illegalVariables)) {
                throw new \InvalidArgumentException(
                    sprintf('表达式引用了不支持的变量: %s', implode(', ', $illegalVariables))
                );
            }
        } catch (\Symfony\Component\ExpressionLanguage\SyntaxError $e) {
            throw new \InvalidArgumentException('表达式语法错误: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 执行表达式评估
     *
     * @param string $expression 升级条件表达式
     * @param array<string, mixed> $context 上下文变量
     * @return bool 是否满足升级条件
     */
    public function evaluate(string $expression, array $context): bool
    {
        try {
            return (bool) $this->expressionLanguage->evaluate($expression, $context);
        } catch (\Throwable $e) {
            // 运行时错误记录日志，返回 false 表示条件不满足
            // 实际实现需要注入 LoggerInterface
            throw new \RuntimeException(
                sprintf('表达式执行失败: %s, 上下文: %s', $expression, json_encode($context)),
                0,
                $e
            );
        }
    }

    /**
     * 获取可用变量清单
     *
     * @return array<string>
     */
    public static function getAllowedVariables(): array
    {
        return self::ALLOWED_VARIABLES;
    }
}
```

#### 1.3 表达式缓存优化

Symfony Expression Language 支持将表达式编译为 PHP 代码以提升性能：

```php
// 对于高频执行的表达式，可以缓存编译结果
$compiledExpression = $this->expressionLanguage->compile($expression, self::ALLOWED_VARIABLES);
// 存储 $compiledExpression 到缓存（如 Redis、APCu）
```

**决策**：初期不实现缓存，待性能测试后决定是否需要。

---

## 2. EasyAdmin 自定义字段类型

### 研究问题

- 如何创建支持两种编辑模式的自定义字段（表单 UI vs 代码编辑器）？
- 如何在保存前触发表达式验证？
- 如何在 EasyAdmin 4 中集成自定义 JavaScript/CSS？

### 技术选型

**选择**：EasyAdmin 4 Custom Field Type + Symfony Form Type

**理由**：
- EasyAdmin 4 支持自定义字段类型
- 可复用 Symfony Form 组件的验证机制
- 支持通过 `configureAssets()` 注入自定义 JavaScript/CSS

### 实施方案

#### 2.1 创建自定义表单类型

```php
namespace Tourze\CommissionUpgradeBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExpressionEditorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // 简单模式字段（隐藏，通过 JavaScript 动态切换显示）
        $builder
            ->add('field', TextType::class, ['required' => false])
            ->add('operator', ChoiceType::class, [
                'choices' => ['>' => '>', '<' => '<', '>=' => '>=', '<=' => '<=', '==' => '=='],
                'required' => false,
            ])
            ->add('value', NumberType::class, ['required' => false])
            // 高级模式字段
            ->add('advancedExpression', TextareaType::class, [
                'required' => false,
                'attr' => ['class' => 'monaco-editor', 'data-language' => 'plaintext'],
            ])
            // 模式标识（simple/advanced）
            ->add('mode', HiddenType::class, ['data' => 'simple'])
        ;
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // 传递可用变量清单到模板
        $view->vars['allowed_variables'] = $options['allowed_variables'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'allowed_variables' => [],
        ]);
    }
}
```

#### 2.2 EasyAdmin 字段集成

在 `DistributorLevelCrudController` 中使用：

```php
use Tourze\CommissionUpgradeBundle\Field\ExpressionEditorField;

public function configureFields(string $pageName): iterable
{
    // ... 其他字段

    yield ExpressionEditorField::new('criteriaJson', '升级条件')
        ->hideOnIndex()
        ->setFormTypeOptions([
            'allowed_variables' => UpgradeExpressionEvaluator::getAllowedVariables(),
        ]);
}
```

#### 2.3 前端交互设计

**简单模式 → 高级模式转换**：
- 用户在简单模式选择 "withdrawnAmount > 5000"
- JavaScript 自动生成表达式字符串并同步到高级模式编辑器
- 用户切换到高级模式后可直接编辑表达式

**高级模式 → 简单模式降级**：
- 如果高级模式的表达式无法解析为简单条件，禁用切换到简单模式
- 显示提示："当前表达式过于复杂，无法在简单模式下编辑"

---

## 3. Monaco Editor 集成方案

### 研究问题

- 如何在 EasyAdmin 中嵌入 Monaco Editor？
- 如何实现表达式语法高亮和自动补全？
- 如何处理资源加载（CDN vs 本地托管）？

### 技术选型

**选择**：Monaco Editor（VSCode 编辑器核心）

**理由**：
- 强大的代码编辑功能（语法高亮、错误提示、自动补全）
- 支持自定义语言定义
- 可通过 CDN 快速集成
- 活跃的社区和文档

### 实施方案

#### 3.1 CDN 集成（推荐初期方案）

```twig
{# templates/bundles/EasyAdminBundle/crud/form_theme.html.twig #}

{% block expression_editor_widget %}
    <div id="expression-editor-container-{{ id }}" style="height: 300px; border: 1px solid #ccc;"></div>
    <input type="hidden" id="{{ id }}" name="{{ full_name }}" value="{{ value }}">

    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/loader.js"></script>
    <script>
        require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs' }});
        require(['vs/editor/editor.main'], function() {
            const editor = monaco.editor.create(document.getElementById('expression-editor-container-{{ id }}'), {
                value: document.getElementById('{{ id }}').value,
                language: 'plaintext', // 或自定义语言
                theme: 'vs-light',
                minimap: { enabled: false },
                lineNumbers: 'on',
            });

            // 同步编辑器内容到隐藏字段
            editor.onDidChangeModelContent(function() {
                document.getElementById('{{ id }}').value = editor.getValue();
            });

            // 提供可用变量的自动补全
            monaco.languages.registerCompletionItemProvider('plaintext', {
                provideCompletionItems: function() {
                    const variables = {{ allowed_variables|json_encode|raw }};
                    return {
                        suggestions: variables.map(v => ({
                            label: v,
                            kind: monaco.languages.CompletionItemKind.Variable,
                            insertText: v,
                        }))
                    };
                }
            });
        });
    </script>
{% endblock %}
```

#### 3.2 本地托管方案（生产环境推荐）

```bash
# 安装 Monaco Editor
yarn add monaco-editor

# 使用 Webpack/Vite 打包
# 参考：https://github.com/microsoft/monaco-editor/blob/main/docs/integrate-esm.md
```

**决策**：初期使用 CDN 方案快速验证，后期迁移到本地托管以提升加载速度和稳定性。

#### 3.3 自定义语法高亮

为表达式语言定义自定义语法：

```javascript
monaco.languages.register({ id: 'upgradeExpression' });

monaco.languages.setMonarchTokensProvider('upgradeExpression', {
    tokenizer: {
        root: [
            [/withdrawnAmount|inviteeCount|orderCount/, 'variable'],
            [/and|or|not/, 'keyword'],
            [/[><=!]+/, 'operator'],
            [/\d+/, 'number'],
        ]
    }
});

monaco.editor.defineTheme('upgradeExpressionTheme', {
    base: 'vs',
    inherit: true,
    rules: [
        { token: 'variable', foreground: '0000FF', fontStyle: 'bold' },
        { token: 'keyword', foreground: 'FF0000' },
        { token: 'operator', foreground: '00AA00' },
        { token: 'number', foreground: 'AA00AA' },
    ]
});
```

---

## 4. 可用变量实现模式

### 研究问题

- 如何计算 `withdrawnAmount`（已提现佣金总额）？
- 如何获取 `inviteeCount`（邀请人数）？
- 如何设计可扩展的变量系统，便于未来新增变量（如 `activeInviteeCount`、`teamPerformance`）？

### 实施方案

#### 4.1 变量计算服务设计

```php
namespace Tourze\CommissionUpgradeBundle\Service;

use Tourze\OrderCommissionBundle\Entity\Distributor;
use Tourze\OrderCommissionBundle\Repository\WithdrawLedgerRepository;
use Tourze\OrderCommissionBundle\Enum\WithdrawLedgerStatus;

class UpgradeContextProvider
{
    public function __construct(
        private WithdrawLedgerRepository $withdrawLedgerRepository,
        // 注入其他必要的 Repository
    ) {}

    /**
     * 构建升级判断所需的上下文变量
     *
     * @param Distributor $distributor
     * @return array<string, mixed>
     */
    public function buildContext(Distributor $distributor): array
    {
        return [
            'withdrawnAmount' => $this->calculateWithdrawnAmount($distributor),
            'inviteeCount' => $this->calculateInviteeCount($distributor),
            'orderCount' => $this->calculateOrderCount($distributor),
            'activeInviteeCount' => $this->calculateActiveInviteeCount($distributor),
        ];
    }

    /**
     * 计算已提现佣金总额（仅统计 WithdrawLedger.Completed 状态）
     */
    private function calculateWithdrawnAmount(Distributor $distributor): float
    {
        return $this->withdrawLedgerRepository->sumCompletedAmount($distributor);
    }

    /**
     * 计算邀请人数（一级下线数量）
     */
    private function calculateInviteeCount(Distributor $distributor): int
    {
        // 假设 Distributor 有 parent 关系，通过查询 parent_id = distributor.id 计数
        return $this->distributorRepository->countByParent($distributor);
    }

    /**
     * 计算订单数（关联该分销员的订单总数）
     */
    private function calculateOrderCount(Distributor $distributor): int
    {
        // 需要查询 CommissionLedger 关联的订单数
        return $this->commissionLedgerRepository->countOrdersByDistributor($distributor);
    }

    /**
     * 计算活跃邀请人数（30天内有订单的下线）
     */
    private function calculateActiveInviteeCount(Distributor $distributor): int
    {
        // 实现逻辑：查询 parent_id = distributor.id 且最近30天有订单的分销员数量
        return $this->distributorRepository->countActiveInvitees($distributor, 30);
    }
}
```

#### 4.2 Repository 方法扩展

在 `WithdrawLedgerRepository` 中添加：

```php
public function sumCompletedAmount(Distributor $distributor): float
{
    return (float) $this->createQueryBuilder('wl')
        ->select('SUM(wl.amount)')
        ->where('wl.distributor = :distributor')
        ->andWhere('wl.status = :status')
        ->setParameter('distributor', $distributor)
        ->setParameter('status', WithdrawLedgerStatus::Completed)
        ->getQuery()
        ->getSingleScalarResult() ?? 0.0;
}
```

#### 4.3 可扩展性设计

**插件化变量系统**（未来优化方向）：

```php
interface UpgradeVariableProviderInterface
{
    public function getName(): string;
    public function calculate(Distributor $distributor): mixed;
}

class WithdrawnAmountProvider implements UpgradeVariableProviderInterface
{
    public function getName(): string { return 'withdrawnAmount'; }
    public function calculate(Distributor $distributor): float { /* ... */ }
}

// UpgradeContextProvider 通过依赖注入收集所有 UpgradeVariableProviderInterface 实现
// 自动注册可用变量
```

**决策**：初期使用硬编码变量清单，待需求验证后再重构为插件化系统。

---

## 5. 事件监听器架构

### 研究问题

- 如何监听 OrderCommissionBundle 的 WithdrawLedger 状态变更？
- 如何确保升级逻辑不阻塞提现流程？
- 如何处理并发场景（多个提现同时完成）？

### 技术选型

**选择**：Symfony EventDispatcher + Doctrine Entity Listeners

**理由**：
- Symfony 原生事件系统，易于集成
- Doctrine 提供 `postUpdate`、`postPersist` 等实体生命周期事件
- 支持异步处理（通过 Symfony Messenger）

### 实施方案

#### 5.1 Doctrine 实体监听器

```php
namespace Tourze\CommissionUpgradeBundle\EventListener;

use Doctrine\ORM\Event\PostUpdateEventArgs;
use Tourze\OrderCommissionBundle\Entity\WithdrawLedger;
use Tourze\OrderCommissionBundle\Enum\WithdrawLedgerStatus;
use Tourze\CommissionUpgradeBundle\Service\DistributorUpgradeService;

class WithdrawLedgerStatusListener
{
    public function __construct(
        private DistributorUpgradeService $upgradeService,
    ) {}

    public function postUpdate(WithdrawLedger $withdrawLedger, PostUpdateEventArgs $args): void
    {
        // 检查状态是否变为 Completed
        $changeSet = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($withdrawLedger);

        if (
            isset($changeSet['status']) &&
            $changeSet['status'][1] === WithdrawLedgerStatus::Completed
        ) {
            // 触发升级检查
            $this->upgradeService->checkAndUpgrade($withdrawLedger->getDistributor());
        }
    }
}
```

#### 5.2 服务注册

```yaml
# config/services.yaml
services:
    Tourze\CommissionUpgradeBundle\EventListener\WithdrawLedgerStatusListener:
        tags:
            - { name: doctrine.orm.entity_listener, entity: Tourze\OrderCommissionBundle\Entity\WithdrawLedger, event: postUpdate }
```

#### 5.3 升级服务核心逻辑

```php
namespace Tourze\CommissionUpgradeBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\OrderCommissionBundle\Entity\Distributor;
use Tourze\OrderCommissionBundle\Entity\DistributorLevel;
use Tourze\CommissionUpgradeBundle\Entity\DistributorLevelUpgradeHistory;

class DistributorUpgradeService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UpgradeContextProvider $contextProvider,
        private UpgradeExpressionEvaluator $expressionEvaluator,
        // 注入通知服务
    ) {}

    public function checkAndUpgrade(Distributor $distributor): ?DistributorLevelUpgradeHistory
    {
        // 获取当前等级
        $currentLevel = $distributor->getLevel();

        // 查找下一级别
        $nextLevel = $this->findNextLevel($currentLevel);
        if ($nextLevel === null) {
            return null; // 已达最高等级
        }

        // 构建上下文变量
        $context = $this->contextProvider->buildContext($distributor);

        // 读取升级条件表达式
        $criteriaJson = $nextLevel->getCriteriaJson();
        $expression = $criteriaJson['upgradeExpression'] ?? null;

        if ($expression === null) {
            // 未配置升级条件，记录警告
            return null;
        }

        // 评估表达式
        try {
            $satisfied = $this->expressionEvaluator->evaluate($expression, $context);
        } catch (\RuntimeException $e) {
            // 表达式执行失败，记录错误日志并通知管理员
            // TODO: 发送通知
            return null;
        }

        if (!$satisfied) {
            return null; // 条件不满足
        }

        // 执行升级（原子操作）
        return $this->performUpgrade($distributor, $currentLevel, $nextLevel, $expression, $context);
    }

    private function performUpgrade(
        Distributor $distributor,
        DistributorLevel $previousLevel,
        DistributorLevel $newLevel,
        string $expression,
        array $context
    ): DistributorLevelUpgradeHistory {
        $this->entityManager->beginTransaction();

        try {
            // 更新分销员等级
            $distributor->setLevel($newLevel);

            // 创建升级历史记录
            $history = new DistributorLevelUpgradeHistory();
            $history->setDistributor($distributor);
            $history->setPreviousLevel($previousLevel);
            $history->setNewLevel($newLevel);
            $history->setSatisfiedExpression($expression);
            $history->setContextSnapshot($context);
            $history->setUpgradeTime(new \DateTimeImmutable());

            $this->entityManager->persist($history);
            $this->entityManager->flush();
            $this->entityManager->commit();

            // 发送升级通知（异步）
            // $this->notificationService->sendUpgradeNotification($distributor, $newLevel);

            return $history;
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    private function findNextLevel(DistributorLevel $currentLevel): ?DistributorLevel
    {
        // 假设 DistributorLevel 有 sort 字段表示等级顺序
        return $this->entityManager->getRepository(DistributorLevel::class)
            ->createQueryBuilder('dl')
            ->where('dl.sort > :currentSort')
            ->setParameter('currentSort', $currentLevel->getSort())
            ->orderBy('dl.sort', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
```

#### 5.4 并发安全保障

**问题**：多个提现同时完成，可能触发多次升级检查。

**解决方案**：

1. **乐观锁**：在 `Distributor` 实体添加 `version` 字段
   ```php
   #[ORM\Version]
   #[ORM\Column(type: 'integer')]
   private int $version = 0;
   ```

2. **悲观锁**：在升级检查时锁定分销员记录
   ```php
   $distributor = $this->entityManager->find(
       Distributor::class,
       $distributorId,
       \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE
   );
   ```

**决策**：使用乐观锁，在 `performUpgrade` 中捕获 `OptimisticLockException` 并重试。

---

## 6. 异步处理优化（可选）

### 研究问题

- 升级检查是否应该异步执行，避免阻塞提现流程？
- 如何使用 Symfony Messenger 实现异步升级？

### 技术选型

**选择**：Symfony Messenger（可选）

**理由**：
- Symfony 官方消息队列组件
- 支持多种传输方式（Redis、RabbitMQ、Doctrine）
- 失败重试机制

### 实施方案

```php
// 创建消息类
namespace Tourze\CommissionUpgradeBundle\Message;

class CheckDistributorUpgrade
{
    public function __construct(
        public readonly string $distributorId,
    ) {}
}

// 消息处理器
namespace Tourze\CommissionUpgradeBundle\MessageHandler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Tourze\CommissionUpgradeBundle\Message\CheckDistributorUpgrade;

#[AsMessageHandler]
class CheckDistributorUpgradeHandler
{
    public function __construct(
        private DistributorUpgradeService $upgradeService,
        private EntityManagerInterface $entityManager,
    ) {}

    public function __invoke(CheckDistributorUpgrade $message): void
    {
        $distributor = $this->entityManager->find(Distributor::class, $message->distributorId);
        if ($distributor !== null) {
            $this->upgradeService->checkAndUpgrade($distributor);
        }
    }
}

// 在监听器中派发消息
public function postUpdate(WithdrawLedger $withdrawLedger, PostUpdateEventArgs $args): void
{
    // ...
    $this->messageBus->dispatch(new CheckDistributorUpgrade($withdrawLedger->getDistributor()->getId()));
}
```

**决策**：初期同步执行升级逻辑（评估性能影响 < 10ms），如后续性能测试发现瓶颈再迁移到异步。

---

## 7. 数据迁移策略

### 研究问题

- 系统上线前如何初始化现有分销员的等级？
- 如何批量计算历史已提现佣金并设置初始等级？

### 实施方案

#### 7.1 迁移命令设计

```php
namespace Tourze\CommissionUpgradeBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InitializeDistributorLevelsCommand extends Command
{
    protected static $defaultName = 'commission-upgrade:initialize-levels';

    public function __construct(
        private DistributorUpgradeService $upgradeService,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $distributors = $this->entityManager->getRepository(Distributor::class)->findAll();
        $io->progressStart(count($distributors));

        foreach ($distributors as $distributor) {
            $this->upgradeService->checkAndUpgrade($distributor);
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success('分销员等级初始化完成');

        return Command::SUCCESS;
    }
}
```

#### 7.2 批量执行策略

- 分批处理（每批100条），避免内存溢出
- 使用 `clear()` 清理 EntityManager 缓存
- 记录迁移日志，标识成功/失败的分销员

---

## 8. 测试策略

### 单元测试

- `UpgradeExpressionEvaluator::validate()` 测试各种表达式语法
- `UpgradeExpressionEvaluator::evaluate()` 测试边界条件（空值、负数、除零）
- `UpgradeContextProvider::buildContext()` 验证变量计算准确性

### 集成测试

- 模拟 WithdrawLedger 状态变为 Completed，验证监听器触发
- 测试并发场景下乐观锁机制
- 验证升级历史记录完整性

### 契约测试

- EasyAdmin 表单保存前验证表达式
- API 返回升级历史记录的结构

---

## 9. 风险与缓解措施

| 风险 | 影响 | 缓解措施 |
|------|------|---------|
| 表达式执行性能问题 | 高并发时升级检查延迟 | 1. 缓存编译后的表达式<br>2. 异步执行升级逻辑 |
| Monaco Editor CDN 不可用 | 后台编辑器无法加载 | 迁移到本地托管资源 |
| 管理员配置错误表达式 | 升级功能失效 | 1. 保存前严格验证<br>2. 运行时容错并通知管理员 |
| 并发升级导致数据不一致 | 分销员等级错误 | 使用乐观锁 + 原子事务 |
| 历史数据迁移耗时过长 | 上线延期 | 分批执行 + 灰度发布 |

---

## 10. 开放决策

以下决策需在 Phase 1 实施前确认：

1. **Monaco Editor 资源托管方式**：CDN vs 本地打包？
   - **建议**：初期 CDN，生产环境迁移本地

2. **升级执行模式**：同步 vs 异步？
   - **建议**：初期同步，性能测试后决定

3. **变量系统扩展性**：硬编码 vs 插件化？
   - **建议**：初期硬编码，待需求稳定后重构

4. **降级功能**：是否支持佣金退款后降级？
   - **建议**：Phase 1 不支持，记录到 backlog

5. **通知渠道**：站内信 vs 多渠道（短信/邮件/推送）？
   - **建议**：Phase 1 仅站内信，后续扩展

---

## 11. 下一步行动

- [ ] 安装 `symfony/expression-language` 依赖
- [ ] 创建 `UpgradeExpressionEvaluator` 服务并编写单元测试
- [ ] 实现 `UpgradeContextProvider` 及 Repository 扩展方法
- [ ] 设计并实现 EasyAdmin 自定义字段类型
- [ ] 集成 Monaco Editor（CDN 方案）
- [ ] 实现 Doctrine 实体监听器 `WithdrawLedgerStatusListener`
- [ ] 创建 `DistributorUpgradeService` 核心逻辑
- [ ] 编写集成测试验证完整升级流程
- [ ] 生成 Phase 1 设计文档（data-model.md、contracts/*.md）

---

**研究完成日期**：2025-11-17
**审核状态**：待审核
**下一阶段**：Phase 1 - 设计与实施计划
