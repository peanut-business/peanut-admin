<?php
declare(strict_types=1);

use app\common\application\BusinessException;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\ExecutionContext;
use app\common\execution\ExecutionContextStore;
use app\common\http\ApiProblemMapper;
use app\common\http\PageResult;
use app\common\validate\InputValidator;
use app\common\model\TenantOwnedModel;
use app\common\service\module\ModuleExecutionBoundary;
use app\common\tenancy\DataScopePolicy;
use app\common\tenancy\MultiTenantDataScopePolicy;
use app\common\tenancy\PlatformTenantDataGateway;
use app\common\tenancy\StandaloneDataScopePolicy;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Module\ModuleException;
use think\Container;
use think\DbManager;
use think\db\BaseQuery;
use think\db\connector\Mysql;
use think\paginator\driver\Bootstrap;
use think\App;
use think\exception\ValidateException;
use think\Validate;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/vendor/topthink/framework/src/helper.php';

function expectTpq51(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class Tpq51RecordingMysql extends Mysql
{
    /** @var list<array{operation:string,sql:string}> */
    public array $statements = [];

    public function select(BaseQuery $query): array
    {
        $query->parseOptions();
        $this->record('select', $query, $this->builder->select($query));

        return match ($query->getTable()) {
            'pa_tpq51_child' => [
                ['id' => 1, 'tenant_id' => 101, 'parent_id' => 7, 'name' => 'first'],
                ['id' => 2, 'tenant_id' => 101, 'parent_id' => 8, 'name' => 'second'],
            ],
            'pa_tpq51_parent' => [
                ['id' => 7, 'tenant_id' => 101, 'name' => 'parent-a'],
                ['id' => 8, 'tenant_id' => 101, 'name' => 'parent-b'],
            ],
            default => [],
        };
    }

    public function insert(BaseQuery $query, bool $getLastInsID = false): int
    {
        $query->parseOptions();
        $this->record('insert', $query, $this->builder->insert($query));
        return 1;
    }

    public function update(BaseQuery $query): int
    {
        $query->parseOptions();
        $this->record('update', $query, $this->builder->update($query));
        return 1;
    }

    public function delete(BaseQuery $query): int
    {
        $query->parseOptions();
        $this->record('delete', $query, $this->builder->delete($query));
        return 1;
    }

    public function getAutoInc($tableName)
    {
        return null;
    }

    public function getLastInsID(BaseQuery $query, ?string $sequence = null)
    {
        return null;
    }

    public function resetStatements(): void
    {
        $this->statements = [];
    }

    private function record(string $operation, BaseQuery $query, string $sql): void
    {
        $this->statements[] = [
            'operation' => $operation,
            'sql' => $this->getRealSql($sql, $query->getBind()),
        ];
    }
}

abstract class Tpq51TenantModel extends TenantOwnedModel
{
    protected $autoWriteTimestamp = false;

    protected function getOptions(): array
    {
        return parent::getOptions() + [
            'pk' => 'id',
            'schema' => [
                'id' => 'int',
                'tenant_id' => 'int',
                'parent_id' => 'int',
                'name' => 'string',
            ],
        ];
    }
}

final class Tpq51Parent extends Tpq51TenantModel
{
    protected $name = 'tpq51_parent';
}

final class Tpq51Child extends Tpq51TenantModel
{
    protected $name = 'tpq51_child';

    public function owner()
    {
        return $this->belongsTo(Tpq51Parent::class, 'parent_id', 'id');
    }
}

final class Tpq51SceneValidate extends Validate
{
    protected $rule = [
        'narrow' => 'require',
        'default_only' => 'require',
    ];

    protected $scene = [
        'narrow' => ['narrow'],
    ];
}

final class Tpq51UnconnectedPdo extends PDO
{
    public function __construct()
    {
    }
}

function tpq51TenantContext(int $tenantId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        501,
        '01JTPQ51BEHAVIOR0000000001',
        $tenantId,
        1001,
        501,
        'admin-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), 'tpq51-request-' . $tenantId);
}

function tpq51SqlCount(string $sql, string $needle): int
{
    return substr_count(strtolower($sql), strtolower($needle));
}

$container = new Container();
Container::setInstance($container);

$store = new ExecutionContextStore();
$current = new CurrentExecutionContext($store);
$container->instance(ExecutionContextStore::class, $store);
$container->instance(CurrentExecutionContext::class, $current);

$database = new DbManager();
$database->setConfig([
    'default' => 'recording',
    'auto_timestamp' => false,
    'connections' => [
        'recording' => [
            'type' => '\\' . Tpq51RecordingMysql::class,
            'builder' => think\db\builder\Mysql::class,
            'prefix' => 'pa_',
            'fields_strict' => true,
        ],
    ],
]);
$connection = $database->connect();
expectTpq51($connection instanceof Tpq51RecordingMysql, 'TPQ51 recording connector was not selected');

$tenantContext = tpq51TenantContext(101);
$execution = ExecutionContext::tenantAdmin($tenantContext, 'tpq51.behavior');
$container->instance(DataScopePolicy::class, new MultiTenantDataScopePolicy($current));

$connection->resetStatements();
$children = $store->run(
    $execution,
    static fn() => Tpq51Child::with('owner')->select(),
);
expectTpq51(count($children) === 2, 'ThinkORM eager result shape changed');
expectTpq51(count($connection->statements) === 2, 'eager relation query count is not constant');
foreach ($connection->statements as $statement) {
    expectTpq51(
        tpq51SqlCount($statement['sql'], 'tenant_id') === 1,
        'root or relation SQL lost the single Tenant global scope: ' . $statement['sql'],
    );
}

$connection->resetStatements();
$store->run(
    $execution,
    static fn() => Tpq51Child::alias('child')->where('child.id', '>', 0)->select(),
);
$aliasSql = $connection->statements[0]['sql'] ?? '';
expectTpq51(
    tpq51SqlCount($aliasSql, 'tenant_id') === 1 && str_contains($aliasSql, '`child`.`tenant_id`'),
    'alias-aware Tenant scope SQL changed: ' . $aliasSql,
);

$connection->resetStatements();
$created = new Tpq51Child([
    'id' => 3,
    'parent_id' => 7,
    'name' => 'created',
]);
expectTpq51(
    $store->run($execution, static fn() => $created->save()),
    'Tenant Model save did not reach the ThinkORM insert path',
);
$insertSql = $connection->statements[0]['sql'] ?? '';
expectTpq51(
    (int)$created->getData('tenant_id') === 101 && tpq51SqlCount($insertSql, 'tenant_id') === 1,
    'before-insert Tenant ownership hook did not populate the trusted Tenant',
);

$rejected = false;
try {
    $store->run($execution, static fn() => (new Tpq51Child([
        'id' => 4,
        'tenant_id' => 202,
        'parent_id' => 7,
        'name' => 'rejected',
    ]))->save());
} catch (DomainException $exception) {
    $rejected = $exception->getMessage() === 'TENANT_WRITE_OWNERSHIP_MISMATCH';
}
expectTpq51($rejected, 'request payload overrode trusted Tenant ownership');

$connection->resetStatements();
$store->run(
    $execution,
    static fn() => Tpq51Child::where('id', '>', 0)->update(['name' => 'bulk-updated']),
);
$store->run(
    $execution,
    static fn() => Tpq51Child::where('id', '>', 0)->delete(),
);
expectTpq51(
    count($connection->statements) === 2
        && array_column($connection->statements, 'operation') === ['update', 'delete'],
    'bulk update/delete did not reach the ThinkORM write path',
);
foreach ($connection->statements as $statement) {
    expectTpq51(
        tpq51SqlCount($statement['sql'], 'tenant_id') === 1,
        'bulk write lost Tenant global scope: ' . $statement['sql'],
    );
}

$differentTenantRejected = false;
try {
    $store->run($execution, static fn() => $store->run(
        ExecutionContext::tenantAdmin(tpq51TenantContext(202), 'tpq51.different-tenant'),
        static fn() => null,
    ));
} catch (DomainException $exception) {
    $differentTenantRejected = $exception->getMessage() === 'EXECUTION_TENANT_CONTEXT_MISMATCH';
}
expectTpq51($differentTenantRejected, 'nested execution context changed the authoritative Tenant');
expectTpq51($store->isEmpty(), 'rejected Tenant context leaked onto the execution stack');

$connection->resetStatements();
(new PlatformTenantDataGateway())
    ->query(Tpq51Child::class, 'platform-test', 'tpq51.cross-tenant-read')
    ->select();
expectTpq51(
    tpq51SqlCount($connection->statements[0]['sql'] ?? '', 'tenant_id') === 0,
    'explicit Platform Tenant gateway did not preserve its audited scope bypass',
);

$container->instance(DataScopePolicy::class, new StandaloneDataScopePolicy());
$connection->resetStatements();
$standalone = new Tpq51Child([
    'id' => 5,
    'tenant_id' => 999,
    'parent_id' => 7,
    'name' => 'standalone',
]);
expectTpq51($standalone->save(), 'Standalone Model save did not reach ThinkORM');
Tpq51Child::alias('child')->where('child.id', '>', 0)->select();
foreach ($connection->statements as $statement) {
    expectTpq51(
        tpq51SqlCount($statement['sql'], 'tenant_id') === 0,
        'Standalone SQL unexpectedly references tenant_id: ' . $statement['sql'],
    );
}

$paginator = new Bootstrap([
    ['id' => 41],
    ['id' => 42],
], 20, 3, 52);
$page = PageResult::fromPaginator($paginator, 3)->withMetadata(['aggregate' => ['active' => 2]]);
expectTpq51($page->responseData() === [
    'lists' => [['id' => 41], ['id' => 42]],
    'count' => 52,
    'pageNo' => 3,
    'pageSize' => 20,
    'aggregate' => ['active' => 2],
], 'PageResult no longer preserves the public pagination envelope');

$mapper = new ApiProblemMapper();
$businessProblem = $mapper->map(BusinessException::conflict('TPQ51_CONFLICT', 'conflict'));
expectTpq51(
    $businessProblem?->httpStatus === 409
        && $businessProblem->apiCode() === 40900
        && $businessProblem->data() === ['error_code' => 'TPQ51_CONFLICT'],
    'Business exception mapping changed',
);
$moduleProblem = $mapper->map(new ModuleException('MODULE_TENANT_DISABLED', 'internal detail'));
expectTpq51(
    $moduleProblem?->httpStatus === 403
        && $moduleProblem->apiCode() === 40300
        && $moduleProblem->getMessage() === 'Module request was rejected.',
    'Module exception mapping changed or leaked internal detail',
);
expectTpq51($mapper->map(new RuntimeException('unknown')) === null, 'unknown exception was exposed as a public problem');

$contextFailure = false;
try {
    (new ModuleExecutionBoundary(new Tpq51UnconnectedPdo(), $current))->assertWorker('official.article');
} catch (DomainException $exception) {
    $contextFailure = $exception->getMessage() === 'EXECUTION_CONTEXT_REQUIRED';
}
expectTpq51($contextFailure, 'Module execution boundary did not fail closed without trusted context');

$sceneValidator = 'app\\test\\Tpq51SceneValidate';
class_alias(Tpq51SceneValidate::class, $sceneValidator);
$inputValidator = new InputValidator(new App(dirname(__DIR__, 2)), $current);
$inputValidator->validate(['narrow' => 'accepted'], $sceneValidator . '.narrow');
$unknownSceneRejected = false;
try {
    $inputValidator->validate(['narrow' => 'accepted'], $sceneValidator . '.unknown');
} catch (ValidateException $exception) {
    $unknownSceneRejected = $exception->getMessage() === '验证场景不存在'
        && $mapper->map($exception)?->apiCode() === 40000;
}
expectTpq51($unknownSceneRejected, 'unknown validation scene fell back to default rules or escaped the input error envelope');

try {
    $store->run($execution, static function (): void {
        throw new RuntimeException('worker failure');
    });
} catch (RuntimeException $exception) {
    expectTpq51($exception->getMessage() === 'worker failure', 'worker failure identity changed');
}
expectTpq51($store->isEmpty(), 'long-running execution context leaked after failure');

echo "TPQ51-THINKPHP-ARCHITECTURE-BEHAVIOR-MATRIX passed\n";
