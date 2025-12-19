# 服务契约：ManualUpgradeCheckType（表单）

**Feature**: `manual-upgrade-check`
**日期**: 2025-11-19
**契约类型**: Symfony Form Type 定义
**实现语言**: PHP (Symfony Form Component)

## 概述

`ManualUpgradeCheckType` 是用户输入分销员ID的表单类型，用于手动升级功能的第一步"检测升级条件"。继承 `AbstractType`，提供简单的输入验证。

---

## 表单定义

### 字段配置

```php
namespace Tourze\CommissionUpgradeBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\CommissionUpgradeBundle\DTO\ManualUpgradeCheckRequest;

class ManualUpgradeCheckType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('distributorId', IntegerType::class, [
                'label' => '分销员ID',
                'required' => true,
                'attr' => [
                    'placeholder' => '请输入分销员ID（例如：12345）',
                    'class' => 'form-control',
                    'min' => 1,
                    'autofocus' => true,
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => '分销员ID不能为空',
                    ]),
                    new Assert\Type([
                        'type' => 'integer',
                        'message' => '分销员ID必须是整数',
                    ]),
                    new Assert\Positive([
                        'message' => '分销员ID必须大于0',
                    ]),
                ],
                'help' => '提示：可以从分销员列表页面复制ID',
            ])
            ->add('submit', SubmitType::class, [
                'label' => '检测升级条件',
                'attr' => [
                    'class' => 'btn btn-primary',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ManualUpgradeCheckRequest::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'manual_upgrade_check',
        ]);
    }
}
```

---

## 字段详细说明

### distributorId（分销员ID）

**类型**: `IntegerType`
**标签**: "分销员ID"
**必填**: 是

**约束规则**：

| 约束 | 配置 | 错误消息 |
|------|------|---------|
| NotBlank | 字段不能为空 | "分销员ID不能为空" |
| Type | 必须为整数类型 | "分销员ID必须是整数" |
| Positive | 必须大于0 | "分销员ID必须大于0" |

**HTML 属性**：

```html
<input type="number"
       name="manual_upgrade_check[distributorId]"
       placeholder="请输入分销员ID（例如：12345）"
       class="form-control"
       min="1"
       autofocus
       required>
```

**帮助文本**: "提示：可以从分销员列表页面复制ID"

---

## CSRF 保护

**配置**：

```php
'csrf_protection' => true,
'csrf_field_name' => '_token',
'csrf_token_id' => 'manual_upgrade_check',
```

**生成的隐藏字段**：

```html
<input type="hidden"
       name="manual_upgrade_check[_token]"
       value="[动态生成的CSRF Token]">
```

**验证**：

```php
// Controller 中验证（Symfony 自动处理）
if (!$form->isValid()) {
    // CSRF 验证失败会导致表单无效
    // 错误消息："The CSRF token is invalid. Please try to resubmit the form."
}
```

---

## 数据绑定

### 输入数据（Request）

```php
// POST /admin/manual-upgrade/check
// Content-Type: application/x-www-form-urlencoded

manual_upgrade_check[distributorId]=12345&
manual_upgrade_check[_token]=abc123xyz
```

### 绑定到 DTO

```php
$form = $this->createForm(ManualUpgradeCheckType::class);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    /** @var ManualUpgradeCheckRequest $data */
    $data = $form->getData();
    $distributorId = $data->getDistributorId(); // 12345
}
```

---

## 验证流程

### 1. HTML5 前端验证

```html
<input type="number" min="1" required>
```

- 浏览器原生验证（可被绕过）
- 仅作为用户体验优化，不可作为安全依赖

### 2. Symfony 后端验证

```php
$form->handleRequest($request);

if ($form->isSubmitted()) {
    if (!$form->isValid()) {
        // 获取验证错误
        $errors = $form->getErrors(true, false);
        foreach ($errors as $error) {
            // 例如："分销员ID不能为空"
            $message = $error->getMessage();
        }
    }
}
```

### 3. 业务层验证（Controller）

```php
$distributorId = $form->getData()->getDistributorId();
$distributor = $distributorRepository->find($distributorId);

if (null === $distributor) {
    // 用户ID通过格式验证，但数据库中不存在
    $this->addFlash('error', '分销员不存在');
    return $this->redirectToRoute('manual_upgrade_check');
}
```

---

## 模板渲染

### Twig 模板示例

```twig
{# templates/manual_upgrade/check_form.html.twig #}

{% extends '@EasyAdmin/layout.html.twig' %}

{% block main %}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">手动升级检测</h3>
        </div>
        <div class="card-body">
            {{ form_start(form) }}

            <div class="mb-3">
                {{ form_row(form.distributorId) }}
            </div>

            <div class="mb-3">
                {{ form_row(form.submit) }}
            </div>

            {{ form_end(form) }}
        </div>
    </div>

    <div class="mt-4">
        <h5>使用说明：</h5>
        <ul>
            <li>输入分销员ID后点击"检测升级条件"</li>
            <li>系统将分析该用户是否满足升级条件</li>
            <li>若满足条件，您可以选择执行升级操作</li>
        </ul>
    </div>
{% endblock %}
```

### 渲染后的 HTML（简化版）

```html
<div class="card">
    <div class="card-header">
        <h3 class="card-title">手动升级检测</h3>
    </div>
    <div class="card-body">
        <form name="manual_upgrade_check" method="post" action="/admin/manual-upgrade/check">
            <input type="hidden" name="manual_upgrade_check[_token]" value="abc123xyz">

            <div class="mb-3">
                <label for="manual_upgrade_check_distributorId" class="required">分销员ID</label>
                <input type="number"
                       id="manual_upgrade_check_distributorId"
                       name="manual_upgrade_check[distributorId]"
                       class="form-control"
                       placeholder="请输入分销员ID（例如：12345）"
                       min="1"
                       required
                       autofocus>
                <div class="form-text">提示：可以从分销员列表页面复制ID</div>
            </div>

            <div class="mb-3">
                <button type="submit" class="btn btn-primary">检测升级条件</button>
            </div>
        </form>
    </div>
</div>
```

---

## 错误处理与用户反馈

### 验证错误展示

**格式验证失败**：

```html
<div class="invalid-feedback d-block">
    分销员ID必须大于0
</div>
```

**CSRF 验证失败**：

```html
<div class="alert alert-danger">
    The CSRF token is invalid. Please try to resubmit the form.
</div>
```

**业务验证失败**（Flash 消息）：

```html
<div class="alert alert-error">
    分销员不存在
</div>
```

### 错误消息国际化（可选）

```yaml
# translations/validators.zh_CN.yaml

distributor_id.not_blank: '分销员ID不能为空'
distributor_id.type: '分销员ID必须是整数'
distributor_id.positive: '分销员ID必须大于0'
csrf.invalid: 'CSRF验证失败，请重新提交表单'
```

---

## 扩展场景（P2 优先级）

### 批量输入（用户故事3）

**未来扩展字段**：

```php
->add('distributorIds', TextareaType::class, [
    'label' => '分销员ID列表',
    'required' => true,
    'attr' => [
        'placeholder' => '请输入多个ID，每行一个或用逗号分隔：\n12345\n67890\n或：12345,67890,11111',
        'rows' => 5,
    ],
    'help' => '支持逗号、空格或换行分隔',
    'constraints' => [
        new Assert\NotBlank(),
        new Assert\Regex([
            'pattern' => '/^[\d,\s\n]+$/',
            'message' => '仅允许输入数字、逗号、空格和换行符',
        ]),
    ],
])
```

**解析逻辑**（Service 层）：

```php
public function parseDistributorIds(string $input): array
{
    // 分割并清理输入
    $ids = preg_split('/[,\s\n]+/', trim($input), -1, PREG_SPLIT_NO_EMPTY);

    // 验证并转换为整数
    $validIds = array_filter(array_map('intval', $ids), fn($id) => $id > 0);

    // 去重
    return array_unique($validIds);
}
```

---

## 测试用例

### 单元测试（FormTest）

```php
namespace Tourze\CommissionUpgradeBundle\Tests\Form;

use Symfony\Component\Form\Test\TypeTestCase;
use Tourze\CommissionUpgradeBundle\DTO\ManualUpgradeCheckRequest;
use Tourze\CommissionUpgradeBundle\Form\ManualUpgradeCheckType;

class ManualUpgradeCheckTypeTest extends TypeTestCase
{
    public function testSubmitValidData(): void
    {
        $formData = [
            'distributorId' => 12345,
        ];

        $model = new ManualUpgradeCheckRequest(0);
        $form = $this->factory->create(ManualUpgradeCheckType::class, $model);

        $expected = new ManualUpgradeCheckRequest(12345);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($expected, $form->getData());

        $view = $form->createView();
        $children = $view->children;

        foreach (array_keys($formData) as $key) {
            $this->assertArrayHasKey($key, $children);
        }
    }

    public function testInvalidDistributorId_Zero(): void
    {
        $formData = ['distributorId' => 0];
        $form = $this->factory->create(ManualUpgradeCheckType::class);
        $form->submit($formData);

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->getErrors(true));
    }

    public function testInvalidDistributorId_Negative(): void
    {
        $formData = ['distributorId' => -100];
        $form = $this->factory->create(ManualUpgradeCheckType::class);
        $form->submit($formData);

        $this->assertFalse($form->isValid());
    }

    public function testInvalidDistributorId_String(): void
    {
        $formData = ['distributorId' => 'abc'];
        $form = $this->factory->create(ManualUpgradeCheckType::class);
        $form->submit($formData);

        $this->assertFalse($form->isValid());
    }

    public function testCsrfProtection(): void
    {
        $form = $this->factory->create(ManualUpgradeCheckType::class);
        $options = $form->getConfig()->getOptions();

        $this->assertTrue($options['csrf_protection']);
        $this->assertSame('_token', $options['csrf_field_name']);
        $this->assertSame('manual_upgrade_check', $options['csrf_token_id']);
    }
}
```

---

## 依赖关系

**无外部服务依赖**（纯表单类型）

**使用的 Symfony 组件**：

```json
{
  "symfony/form": "^7.3",
  "symfony/validator": "^7.3"
}
```

---

## 相关文档

- [数据模型（DTO）](../data-model.md#dto)
- [Controller 契约](./manual-upgrade-controller.md#action-1-checkaction)
- [Plan 文档](../plan.md)
