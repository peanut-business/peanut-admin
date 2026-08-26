<?php
declare(strict_types=1);

/**
 * Source/Reflection-only contract for the reusable admin Tenant CRUD
 * controller templates.
 *
 * This file deliberately does not bootstrap ThinkPHP, create a request, or
 * instantiate a controller, Logic, or Validate class. Composer autoloading is
 * the only runtime setup required for Reflection to inspect the declarations.
 */
require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectAdminTenantCrud(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function reflectAdminTenantCrud(string $class): ReflectionClass
{
    expectAdminTenantCrud(class_exists($class), 'controller class is not autoloadable: ' . $class);
    return new ReflectionClass($class);
}

/** @return array{value:mixed,declaring:string} */
function adminTenantCrudConstant(ReflectionClass $class, string $name): array
{
    $constant = $class->getReflectionConstant($name);
    expectAdminTenantCrud(
        $constant !== null,
        sprintf('%s is missing constant %s', $class->getName(), $name),
    );

    return [
        'value' => $constant->getValue(),
        'declaring' => $constant->getDeclaringClass()->getName(),
    ];
}

function expectAdminTenantCrudConstant(
    ReflectionClass $class,
    string $name,
    mixed $expected,
    ?string $declaringClass = null,
): void {
    $actual = adminTenantCrudConstant($class, $name);
    expectAdminTenantCrud(
        $actual['value'] === $expected,
        sprintf(
            '%s::%s changed: expected %s, got %s',
            $class->getName(),
            $name,
            var_export($expected, true),
            var_export($actual['value'], true),
        ),
    );
    if ($declaringClass !== null) {
        expectAdminTenantCrud(
            $actual['declaring'] === $declaringClass,
            sprintf(
                '%s::%s must be declared by %s, got %s',
                $class->getName(),
                $name,
                $declaringClass,
                $actual['declaring'],
            ),
        );
    }
}

function expectAdminTenantCrudMethod(
    ReflectionClass $class,
    string $name,
    ?string $declaringClass = null,
    bool $public = false,
    bool $protected = false,
): ReflectionMethod {
    expectAdminTenantCrud(
        $class->hasMethod($name),
        sprintf('%s is missing method %s()', $class->getName(), $name),
    );
    $method = $class->getMethod($name);
    if ($declaringClass !== null) {
        expectAdminTenantCrud(
            $method->getDeclaringClass()->getName() === $declaringClass,
            sprintf(
                '%s::%s() must be declared by %s, got %s',
                $class->getName(),
                $name,
                $declaringClass,
                $method->getDeclaringClass()->getName(),
            ),
        );
    }
    if ($public) {
        expectAdminTenantCrud($method->isPublic(), $class->getName() . '::' . $name . '() is not public');
    }
    if ($protected) {
        expectAdminTenantCrud($method->isProtected(), $class->getName() . '::' . $name . '() is not protected');
    }

    return $method;
}

function adminTenantCrudSource(ReflectionClass $class): string
{
    $file = $class->getFileName();
    expectAdminTenantCrud($file !== false, 'cannot locate source for ' . $class->getName());
    return (string) file_get_contents($file);
}

$baseName = 'app\\adminapi\\controller\\AbstractTenantCrudController';
$base = reflectAdminTenantCrud($baseName);
expectAdminTenantCrud($base->isAbstract(), $baseName . ' must remain abstract');
expectAdminTenantCrud(
    $base->getParentClass()?->getName() === 'app\\adminapi\\controller\\BaseAdminController',
    $baseName . ' must extend BaseAdminController directly',
);

// The reusable template owns exactly these six public actions. They must be
// final so a resource cannot silently fork the request/logic/response path.
$actions = ['lists', 'detail', 'add', 'edit', 'delete', 'updateStatus'];
foreach ($actions as $action) {
    $method = expectAdminTenantCrudMethod($base, $action, $baseName, true);
    expectAdminTenantCrud($method->isFinal(), $baseName . '::' . $action . '() must be final');
    expectAdminTenantCrud(
        $method->getReturnType()?->getName() === 'think\\response\\Json',
        $baseName . '::' . $action . '() must return think\\response\\Json',
    );
}
$ownPublicMethods = array_values(array_map(
    static fn(ReflectionMethod $method): string => $method->getName(),
    array_filter(
        $base->getMethods(ReflectionMethod::IS_PUBLIC),
        static fn(ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $baseName,
    ),
));
sort($ownPublicMethods);
$expectedActions = $actions;
sort($expectedActions);
expectAdminTenantCrud(
    $ownPublicMethods === $expectedActions,
    $baseName . ' public action surface changed: ' . implode(', ', $ownPublicMethods),
);

expectAdminTenantCrudConstant($base, 'CRUD_NOT_FOUND_MESSAGE', '数据不存在', $baseName);
expectAdminTenantCrudConstant($base, 'CRUD_ADD_SUCCESS_MESSAGE', '操作成功', $baseName);
expectAdminTenantCrudConstant($base, 'CRUD_EDIT_SUCCESS_MESSAGE', '操作成功', $baseName);
expectAdminTenantCrudConstant($base, 'CRUD_DELETE_SUCCESS_MESSAGE', '操作成功', $baseName);
expectAdminTenantCrudConstant($base, 'CRUD_STATUS_SUCCESS_MESSAGE', '操作成功', $baseName);
expectAdminTenantCrudConstant($base, 'CRUD_VALIDATE_LISTS', false, $baseName);
expectAdminTenantCrudConstant($base, 'CRUD_STATUS_FIELD', 'is_disable', $baseName);
expectAdminTenantCrudConstant($base, 'CRUD_STATUS_SCENE', 'status', $baseName);
$contextHook = expectAdminTenantCrudMethod($base, 'resolveCrudContext', $baseName, false, true);
expectAdminTenantCrud($contextHook->isAbstract(), $baseName . '::resolveCrudContext() must be abstract');
expectAdminTenantCrud(
    $contextHook->getReturnType()?->getName() === 'PeanutAdmin\\Kernel\\Auth\\TenantContext',
    $baseName . '::resolveCrudContext() must return TenantContext',
);

$articleAbstractName = 'app\\Modules\\Official\\Article\\Http\\Controller\\AbstractArticleCrudController';
$articleAbstract = reflectAdminTenantCrud($articleAbstractName);
expectAdminTenantCrud($articleAbstract->isAbstract(), $articleAbstractName . ' must remain abstract');
expectAdminTenantCrud(
    $articleAbstract->getParentClass()?->getName() === $baseName,
    $articleAbstractName . ' must extend AbstractTenantCrudController directly',
);
foreach ([
    'CRUD_ADD_SUCCESS_MESSAGE' => '添加成功',
    'CRUD_EDIT_SUCCESS_MESSAGE' => '编辑成功',
    'CRUD_DELETE_SUCCESS_MESSAGE' => '删除成功',
    'CRUD_STATUS_SUCCESS_MESSAGE' => '修改成功',
    'CRUD_VALIDATE_LISTS' => true,
    'CRUD_STATUS_FIELD' => 'is_show',
] as $constant => $value) {
    expectAdminTenantCrudConstant($articleAbstract, $constant, $value, $articleAbstractName);
}
foreach (['resolveCrudContext', 'renderLists', 'renderDetail', 'validatedInput'] as $hook) {
    expectAdminTenantCrudMethod($articleAbstract, $hook, $articleAbstractName, false, true);
}
expectAdminTenantCrud(
    str_contains(adminTenantCrudSource($articleAbstract), 'ArticleTenantContext::member($this->request)'),
    $articleAbstractName . ' lost its Article Tenant context hook',
);

// Direct subclasses configure only their Logic/Validate pair; their hooks
// remain visible through Reflection and are checked against the source marker
// so this contract never has to instantiate either dependency.
$directTenantCrud = [
    'app\\adminapi\\controller\\dict\\DictTypeController' => [
        'logic' => 'app\\adminapi\\logic\\dict\\DictTypeLogic',
        'validate' => 'app\\adminapi\\validate\\dict\\DictTypeValidate',
        'notFound' => '字典类型不存在',
        'context' => 'DictTenantContext::member($this->request)',
        'extraMethods' => ['all'],
    ],
    'app\\adminapi\\controller\\dict\\DictDataController' => [
        'logic' => 'app\\adminapi\\logic\\dict\\DictDataLogic',
        'validate' => 'app\\adminapi\\validate\\dict\\DictDataValidate',
        'notFound' => '字典数据不存在',
        'context' => 'DictTenantContext::member($this->request)',
        'extraMethods' => ['byType'],
    ],
    'app\\Modules\\Official\\Oauth\\Http\\Controller\\OfficialAccountReplyController' => [
        'logic' => 'app\\Modules\\Official\\Oauth\\Service\\OfficialAccountReplyLogic',
        'validate' => 'app\\Modules\\Official\\Oauth\\Validation\\OfficialAccountReplyValidate',
        'notFound' => '自动回复不存在',
        'context' => 'MemberTenantContext::member($this->request)',
        'extraMethods' => [],
    ],
];
foreach ($directTenantCrud as $className => $contract) {
    $class = reflectAdminTenantCrud($className);
    expectAdminTenantCrud(
        $class->getParentClass()?->getName() === $baseName,
        $className . ' must extend AbstractTenantCrudController directly',
    );
    expectAdminTenantCrudConstant($class, 'CRUD_LOGIC', $contract['logic'], $className);
    expectAdminTenantCrudConstant($class, 'CRUD_VALIDATE', $contract['validate'], $className);
    expectAdminTenantCrudConstant($class, 'CRUD_NOT_FOUND_MESSAGE', $contract['notFound'], $className);
    expectAdminTenantCrudMethod($class, 'resolveCrudContext', $className, false, true);
    $source = adminTenantCrudSource($class);
    expectAdminTenantCrud(str_contains($source, $contract['context']), $className . ' lost its Tenant context hook');
    foreach ($contract['extraMethods'] as $methodName) {
        expectAdminTenantCrudMethod($class, $methodName, $className, true);
    }
}

$reply = reflectAdminTenantCrud('app\\Modules\\Official\\Oauth\\Http\\Controller\\OfficialAccountReplyController');
expectAdminTenantCrudConstant($reply, 'CRUD_DELETE_SUCCESS_MESSAGE', '删除成功', $reply->getName());
expectAdminTenantCrudConstant($reply, 'CRUD_VALIDATE_LISTS', true, $reply->getName());
expectAdminTenantCrudConstant($reply, 'CRUD_STATUS_FIELD', 'status', $reply->getName());
expectAdminTenantCrudMethod($reply, 'renderLists', $reply->getName(), false, true);

// Article resources share the Article-specific template, not the generic
// template directly; this preserves their is_show/list-validation contract.
foreach ([
    'app\\Modules\\Official\\Article\\Http\\Controller\\ArticleController' => [
        'logic' => 'app\\Modules\\Official\\Article\\Service\\ArticleLogic',
        'validate' => 'app\\Modules\\Official\\Article\\Validation\\ArticleValidate',
        'extraMethods' => [],
    ],
    'app\\Modules\\Official\\Article\\Http\\Controller\\ArticleCateController' => [
        'logic' => 'app\\Modules\\Official\\Article\\Service\\ArticleCateLogic',
        'validate' => 'app\\Modules\\Official\\Article\\Validation\\ArticleCateValidate',
        'extraMethods' => ['all'],
    ],
] as $className => $contract) {
    $class = reflectAdminTenantCrud($className);
    expectAdminTenantCrud(
        $class->getParentClass()?->getName() === $articleAbstractName,
        $className . ' must extend AbstractArticleCrudController directly',
    );
    expectAdminTenantCrudConstant($class, 'CRUD_LOGIC', $contract['logic'], $className);
    expectAdminTenantCrudConstant($class, 'CRUD_VALIDATE', $contract['validate'], $className);
    foreach ($contract['extraMethods'] as $methodName) {
        expectAdminTenantCrudMethod($class, $methodName, $className, true);
    }
}

// Dept and Jobs use one Org-specific adapter over the generic Tenant CRUD
// template. Resolve the actual class name via Reflection so the contract is
// independent of whether the adapter lives in controller\ or controller\dept\.
$dept = reflectAdminTenantCrud('app\\adminapi\\controller\\dept\\DeptController');
$jobs = reflectAdminTenantCrud('app\\adminapi\\controller\\dept\\JobsController');
$orgBase = $dept->getParentClass();
expectAdminTenantCrud($orgBase !== false && $orgBase !== null, 'DeptController has no Org CRUD parent');
$orgBaseName = $orgBase->getName();
expectAdminTenantCrud(
    str_ends_with($orgBaseName, '\\AbstractOrgCrudController'),
    'DeptController must use an AbstractOrgCrudController adapter, got ' . $orgBaseName,
);
expectAdminTenantCrud(
    $jobs->getParentClass()?->getName() === $orgBaseName,
    'JobsController must share DeptController\'s Org CRUD adapter',
);
expectAdminTenantCrud($orgBase->isAbstract(), $orgBaseName . ' must remain abstract');
expectAdminTenantCrud(
    $orgBase->getParentClass()?->getName() === $baseName,
    $orgBaseName . ' must extend AbstractTenantCrudController directly',
);
expectAdminTenantCrudConstant($orgBase, 'CRUD_STATUS_FIELD', 'status', $orgBaseName);
expectAdminTenantCrudMethod($orgBase, 'resolveCrudContext', $orgBaseName, false, true);
expectAdminTenantCrudMethod($orgBase, 'validatedInput', $orgBaseName, false, true);
expectAdminTenantCrud(str_contains(adminTenantCrudSource($orgBase), 'OrgTenantContext::member($this->request)'), $orgBaseName . ' lost its Org Tenant context hook');
expectAdminTenantCrud(str_contains(adminTenantCrudSource($orgBase), 'validationRules'), $orgBaseName . ' lost Logic validationRules hook');
foreach ([
    $dept->getName() => [
        'logic' => 'app\\adminapi\\logic\\dept\\DeptLogic',
        'extraMethods' => ['all', 'leaderDept'],
    ],
    $jobs->getName() => [
        'logic' => 'app\\adminapi\\logic\\dept\\JobsLogic',
        'extraMethods' => ['all'],
    ],
] as $className => $contract) {
    expectAdminTenantCrudConstant($className === $dept->getName() ? $dept : $jobs, 'CRUD_LOGIC', $contract['logic'], $className);
    $class = $className === $dept->getName() ? $dept : $jobs;
    expectAdminTenantCrudMethod($class, 'resolveCrudContext', $orgBaseName, false, true);
    foreach ($contract['extraMethods'] as $methodName) {
        expectAdminTenantCrudMethod($class, $methodName, $className, true);
    }
}

// These resources have intentionally different actor, side-effect, response,
// or transaction contracts. They must not acquire the six-action template by
// accidental inheritance.
$mustRemainCustom = [
    'Admin' => [
        'app\\adminapi\\controller\\auth\\AdminController',
    ],
    'Menu' => [
        'app\\adminapi\\controller\\auth\\MenuController',
    ],
    'File' => [
        'app\\Modules\\Official\\File\\Http\\Controller\\FileController',
        'app\\Modules\\Official\\File\\Http\\Controller\\UploadController',
    ],
    'Member' => [
        'app\\Modules\\Official\\Member\\Http\\Controller\\MemberController',
        'app\\Modules\\Official\\Member\\Http\\Controller\\MemberTagController',
    ],
    'Generator' => [
        'app\\adminapi\\controller\\generator\\GeneratorController',
    ],
    'Finance' => [
        'app\\Modules\\Official\\Member\\Http\\Controller\\AccountLogController',
        'app\\Modules\\Official\\Payment\\Http\\Controller\\RechargeController',
        'app\\Modules\\Official\\Payment\\Http\\Controller\\RefundController',
    ],
    'Crontab' => [
        'app\\Modules\\Official\\Task\\Http\\Controller\\CrontabController',
    ],
];
foreach ($mustRemainCustom as $domain => $classes) {
    foreach ($classes as $className) {
        $class = reflectAdminTenantCrud($className);
        expectAdminTenantCrud(
            !$class->isSubclassOf($baseName),
            $domain . ' controller must not inherit ' . $baseName . ': ' . $className,
        );
    }
}

echo "ADMIN-TENANT-CRUD-CONTROLLER-CONTRACT-001 passed\n";
