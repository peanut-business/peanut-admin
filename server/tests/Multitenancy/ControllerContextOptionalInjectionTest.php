<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use app\adminapi\controller\BaseAdminController;
use app\api\controller\BaseApiController;
use app\platform\controller\BasePlatformController;
use app\common\execution\AdminExecutionContext;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\ExecutionContextStore;
use app\platform\context\PlatformOperatorContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\App;
use think\Container;
use think\Request;

function expectOptionalInjection(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// 1. 初始化容器与执行上下文
$app = new App();
Container::setInstance($app);

$store = new ExecutionContextStore();
$current = new CurrentExecutionContext($store);
$request = new Request();

$app->instance(App::class, $app);
$app->instance(Request::class, $request);
$app->instance(ExecutionContextStore::class, $store);
$app->instance(CurrentExecutionContext::class, $current);

// 2. 定义派生测试类（模拟无需显式声明 executionContext 的精简业务控制器）
class SampleAdminController extends BaseAdminController
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function exposedContext(): CurrentExecutionContext
    {
        return $this->executionContext();
    }

    public function exposedAdminId(): int
    {
        return $this->adminId;
    }
}

class SampleApiController extends BaseApiController
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function exposedContext(): CurrentExecutionContext
    {
        return $this->executionContext;
    }

    public function exposedMemberId(): int
    {
        return $this->memberId;
    }
}

class SamplePlatformController extends BasePlatformController
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function exposedContext(): CurrentExecutionContext
    {
        return $this->executionContext;
    }

    public function exposedPlatformContext(): ?PlatformOperatorContext
    {
        return $this->platformContext;
    }
}

// 3. 测试 Admin 控制器：未传上下文时自动从容器回退
$adminController = new SampleAdminController($app);
expectOptionalInjection(
    $adminController->exposedContext() === $current,
    'SampleAdminController failed to fallback to container CurrentExecutionContext',
);

// 4. 测试 Admin 控制器：显式传入时优先使用传入实例
$mockStore = new ExecutionContextStore();
$mockCurrent = new CurrentExecutionContext($mockStore);
$explicitAdminController = new class($app, $mockCurrent) extends BaseAdminController {
    public function exposedContext(): CurrentExecutionContext
    {
        return $this->executionContext();
    }
};
expectOptionalInjection(
    $explicitAdminController->exposedContext() === $mockCurrent,
    'Explicit CurrentExecutionContext injection was ignored in BaseAdminController',
);

// 5. 测试 Api 控制器：未传上下文时自动从容器回退
$apiController = new SampleApiController($app);
expectOptionalInjection(
    $apiController->exposedContext() === $current,
    'SampleApiController failed to fallback to container CurrentExecutionContext',
);

// 6. 测试 Platform 控制器：未传上下文时自动从容器回退
$platformController = new SamplePlatformController($app);
expectOptionalInjection(
    $platformController->exposedContext() === $current,
    'SamplePlatformController failed to fallback to container CurrentExecutionContext',
);

// 7. 测试生命周期与上下文提取
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;

$validatedSession = new ValidatedTenantSession(
    301,
    '01JMT02OPTIONAL0000000000001',
    101,
    201,
    301,
    'admin-web',
    new DateTimeImmutable('2031-01-01T00:00:00Z'),
    1,
);
$tenantContext = TenantContext::fromValidatedSession($validatedSession, 'req-test-admin-001');
$adminExecution = new AdminExecutionContext(
    $tenantContext,
    'http.admin.test',
    ['id' => 301, 'name' => 'admin-tester'],
);

$store->run($adminExecution, function () use ($app) {
    $activeAdminController = new SampleAdminController($app);
    expectOptionalInjection(
        $activeAdminController->exposedAdminId() === 301,
        'BaseAdminController initialize() did not extract adminId from CurrentExecutionContext',
    );
});

echo "CONTROLLER-CONTEXT-OPTIONAL-INJECTION-001 passed\n";
