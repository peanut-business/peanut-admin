<?php
declare(strict_types=1);

use app\Modules\Fixture\DeliveryRecord\Contracts\DeliveryRecordCommands;
use app\Modules\Fixture\DeliveryRecord\Http\DeliveryRecordHttpHandler;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use PeanutAdmin\Kernel\Module\ModuleException;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function fixtureHttpExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function fixtureHttpRejects(Closure $operation, string $errorCode): void
{
    try {
        $operation();
    } catch (ModuleException $exception) {
        fixtureHttpExpect(
            $exception->errorCode === $errorCode,
            "unexpected Module refusal: {$exception->errorCode}"
        );
        return;
    }
    throw new RuntimeException("expected Module refusal: {$errorCode}");
}

$context = TenantContext::fromValidatedSession(new ValidatedTenantSession(
    31,
    '01JFIXTUREHTTPBOUNDARY0000001',
    11,
    21,
    31,
    'admin-web',
    new DateTimeImmutable('now', new DateTimeZone('UTC')),
    1
), 'fixture-http-boundary');
$root = ['id' => 31, 'tenant_id' => 11, 'account_id' => 21, 'root' => 1];

$disabledCommands = new class implements DeliveryRecordCommands {
    public int $calls = 0;

    public function record(TenantContext $context, string $reference): array
    {
        ++$this->calls;
        throw new ModuleException('MODULE_TENANT_DISABLED', 'disabled');
    }

    public function list(TenantContext $context): array
    {
        ++$this->calls;
        throw new ModuleException('MODULE_TENANT_DISABLED', 'disabled');
    }
};
$handler = new DeliveryRecordHttpHandler($disabledCommands);
fixtureHttpRejects(
    static fn() => $handler->lists($context, $root),
    'MODULE_TENANT_DISABLED'
);
fixtureHttpExpect($disabledCommands->calls === 1, 'root request did not reach the guarded Module command exactly once');

$systemContext = new TenantSystemContext(11, 'fixture.system', 'list', 'fixture-system-1');
fixtureHttpRejects(
    static fn() => $handler->lists($systemContext, $root),
    'CONTEXT_TENANT_REQUIRED'
);
fixtureHttpExpect($disabledCommands->calls === 1, 'system actor reached the Module command');

fixtureHttpRejects(
    static fn() => $handler->lists($context, ['id' => 31, 'tenant_id' => 12, 'account_id' => 21, 'root' => 1]),
    'AUTHORIZATION_PERMISSION_DENIED'
);
fixtureHttpExpect($disabledCommands->calls === 1, 'mismatched root principal reached the Module command');

$routeSource = (string)file_get_contents(dirname(__DIR__, 2)
    . '/app/Modules/Fixture/DeliveryRecord/Http/routes.php');
$routeBootstrap = (string)file_get_contents(dirname(__DIR__, 2)
    . '/route/fixture_delivery_record.php');
fixtureHttpExpect(
    substr_count($routeSource, 'LoginMiddleware::class') === 2,
    'fixture HTTP routes lost the Tenant Admin session guard'
);
fixtureHttpExpect(
    !str_contains($routeSource, 'AuthMiddleware::class'),
    'fixture HTTP route reintroduced the generic root-bypass permission middleware'
);
fixtureHttpExpect(
    str_contains($routeBootstrap, 'Http/routes.php'),
    'ThinkPHP route bootstrap lost the Module-owned routes'
);

echo "FIXTURE-MODULE-HTTP-BOUNDARY-001 passed\n";
