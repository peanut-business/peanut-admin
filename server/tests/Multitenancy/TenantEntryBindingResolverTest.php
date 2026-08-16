<?php
declare(strict_types=1);

use app\common\service\tenant\TenantEntryBindingResolver;
use app\common\service\tenant\ApplicationHostPolicy;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function entryBindingExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function entryBindingRejects(Closure $operation, string $message): void
{
    try {
        $operation();
    } catch (DomainException) {
        return;
    }
    throw new RuntimeException($message);
}

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec(<<<'SQL'
CREATE TABLE pa_tenant (
  id INTEGER PRIMARY KEY,
  code TEXT NOT NULL UNIQUE,
  status TEXT NOT NULL
);
CREATE TABLE pa_tenant_entry_binding (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tenant_id INTEGER NOT NULL,
  host TEXT NOT NULL,
  client_key TEXT NOT NULL,
  status TEXT NOT NULL,
  UNIQUE (host, client_key)
);
INSERT INTO pa_tenant (id,code,status) VALUES
  (101,'alpha','active'),
  (202,'beta','active'),
  (303,'paused','suspended');
SQL);

$fallbackCalls = 0;
$resolver = new TenantEntryBindingResolver(
    $pdo,
    static function (string $actor, string $operation, string $operationId) use (&$fallbackCalls): TenantSystemContext {
        $fallbackCalls++;
        return new TenantSystemContext(999, $actor, $operation, $operationId);
    },
);
$boundRequest = new class {
    public function host(): string { return 'ALPHA.Example.test:443'; }
};
$unboundRequest = new class {
    public function host(): string { return 'unbound.example.test'; }
};
$platformRequest = new class {
    public function host(): string { return 'platform.example.test'; }
};
$sharedAdminRequest = new class {
    public function host(): string { return 'admin.example.test'; }
};

entryBindingExpect(
    TenantEntryBindingResolver::normalizeHost('Alpha.Example.Test.:443') === 'alpha.example.test',
    'Host normalization changed',
);
entryBindingRejects(
    static fn() => TenantEntryBindingResolver::normalizeHost('tenant@example.test'),
    'Host normalization accepted user-info syntax',
);
entryBindingExpect(
    $resolver->loginTenantCode(
        $unboundRequest,
        TenantEntryBindingResolver::ADMIN_CLIENT,
        'alpha',
    ) === 'alpha',
    'an unconfigured Host rejected an explicit Tenant code',
);
entryBindingExpect(
    $resolver->loginTenantCode(
        $unboundRequest,
        TenantEntryBindingResolver::ADMIN_CLIENT,
        null,
    ) === null,
    'an unconfigured Admin Host invented a Tenant candidate',
);
$fallback = $resolver->system(
    $unboundRequest,
    TenantEntryBindingResolver::MEMBER_CLIENT,
    'member-public-auth',
    'member.login',
    'entry-fallback',
);
entryBindingExpect(
    $fallback->tenantId === 999 && $fallbackCalls === 1,
    'an unconfigured member Host did not use the explicit default fallback',
);

$insert = $pdo->prepare(
    'INSERT INTO pa_tenant_entry_binding (tenant_id,host,client_key,status) VALUES (?,?,?,?)'
);
$insert->execute([101, 'alpha.example.test', TenantEntryBindingResolver::ADMIN_CLIENT, 'active']);
$insert->execute([101, 'alpha.example.test', TenantEntryBindingResolver::MEMBER_CLIENT, 'active']);
$multiTenantHosts = new ApplicationHostPolicy(
    'multi-tenant',
    ['platform.example.test'],
    ['admin.example.test'],
    $resolver,
);
$multiTenantHosts->assertPlatform($platformRequest);
$multiTenantHosts->assertTenantAdmin($sharedAdminRequest);
$multiTenantHosts->assertTenantAdmin($boundRequest);
entryBindingRejects(
    static fn() => $multiTenantHosts->assertPlatform($sharedAdminRequest),
    'the shared Tenant Admin Host reached the Platform control plane',
);
entryBindingRejects(
    static fn() => $multiTenantHosts->assertTenantAdmin($platformRequest),
    'the Platform Host reached the Tenant Admin control plane',
);
entryBindingRejects(
    static fn() => $multiTenantHosts->assertTenantAdmin($unboundRequest),
    'an unknown Host became an implicit shared Tenant Admin entry',
);
(new ApplicationHostPolicy('standalone', [], [], $resolver))->assertTenantAdmin($unboundRequest);
entryBindingExpect(
    $resolver->boundTenantId($boundRequest, TenantEntryBindingResolver::ADMIN_CLIENT) === 101,
    'the bound Admin entry did not expose its continuous Tenant boundary',
);
entryBindingExpect(
    $resolver->boundTenantId($unboundRequest, TenantEntryBindingResolver::ADMIN_CLIENT) === null,
    'an unbound Admin entry invented a continuous Tenant boundary',
);
$resolver->assertTenantAccess($boundRequest, TenantEntryBindingResolver::ADMIN_CLIENT, 101);
entryBindingRejects(
    static fn() => $resolver->assertTenantAccess(
        $boundRequest,
        TenantEntryBindingResolver::ADMIN_CLIENT,
        202,
    ),
    'a bound Admin entry accepted a session for another Tenant',
);
entryBindingExpect(
    $resolver->loginTenantCode(
        $boundRequest,
        TenantEntryBindingResolver::ADMIN_CLIENT,
        null,
    ) === 'alpha',
    'a unique active binding did not limit Admin login',
);
entryBindingExpect(
    $resolver->loginTenantCode(
        $boundRequest,
        TenantEntryBindingResolver::ADMIN_CLIENT,
        'alpha',
    ) === 'alpha',
    'a matching explicit Tenant code was rejected',
);
entryBindingRejects(
    static fn() => $resolver->loginTenantCode(
        $boundRequest,
        TenantEntryBindingResolver::ADMIN_CLIENT,
        'beta',
    ),
    'a conflicting explicit Tenant code bypassed its Host binding',
);
try {
    $insert->execute([202, 'alpha.example.test', TenantEntryBindingResolver::ADMIN_CLIENT, 'active']);
    throw new RuntimeException('database accepted two active owners for one Host/client entry');
} catch (PDOException) {
    // The database, not only the service, owns the one Host/client invariant.
}
$member = $resolver->system(
    $boundRequest,
    TenantEntryBindingResolver::MEMBER_CLIENT,
    'member-public-auth',
    'member.register',
    'entry-bound',
);
entryBindingExpect(
    $member->tenantId === 101 && $fallbackCalls === 1,
    'a bound member entry fell through to the default Tenant',
);

$pdo->exec("UPDATE pa_tenant_entry_binding SET status='disabled' WHERE tenant_id=101 AND client_key='member-api'");
entryBindingRejects(
    static fn() => $resolver->system(
        $boundRequest,
        TenantEntryBindingResolver::MEMBER_CLIENT,
        'member-public-auth',
        'member.login',
        'entry-disabled',
    ),
    'a disabled binding silently used the default Tenant',
);
$pdo->exec("UPDATE pa_tenant_entry_binding SET status='active' WHERE tenant_id=101 AND client_key='member-api'");
$pdo->exec("UPDATE pa_tenant SET status='suspended' WHERE id=101");
entryBindingRejects(
    static fn() => $resolver->loginTenantCode(
        $boundRequest,
        TenantEntryBindingResolver::ADMIN_CLIENT,
        'alpha',
    ),
    'a suspended bound Tenant remained available to Admin login',
);
entryBindingRejects(
    static fn() => $resolver->system(
        $boundRequest,
        TenantEntryBindingResolver::MEMBER_CLIENT,
        'member-public-auth',
        'member.login',
        'entry-suspended',
    ),
    'a suspended bound Tenant silently used the default Tenant',
);
entryBindingExpect($fallbackCalls === 1, 'a configured failure reached the compatibility fallback');

$sessionController = (string)file_get_contents(
    dirname(__DIR__, 2) . '/app/tenant/controller/TenantSessionController.php'
);
$loginMiddleware = (string)file_get_contents(
    dirname(__DIR__, 2) . '/app/adminapi/http/middleware/LoginMiddleware.php'
);
entryBindingExpect(
    str_contains($sessionController, 'TENANT_SWITCH_BOUND_ENTRY')
        && str_contains($sessionController, 'assertTenantAccess('),
    'Tenant challenge selection or switch lost the continuous Host boundary',
);
entryBindingExpect(
    str_contains($loginMiddleware, 'assertTenantAccess(')
        && str_contains($loginMiddleware, 'tenantEntryBound'),
    'Admin session requests lost the continuous Host boundary',
);

$migration = (string)file_get_contents(
    dirname(__DIR__, 2) . '/database/migrations/20260816-tenant-entry-binding.sql'
);
foreach (['pa_tenant_entry_binding', '`tenant_id`', '`host`', '`client_key`', 'fk_tenant_entry_binding_tenant'] as $token) {
    entryBindingExpect(str_contains($migration, $token), 'Tenant entry migration lost contract token: ' . $token);
}
entryBindingExpect(
    str_contains($migration, 'UNIQUE KEY `uk_tenant_entry_binding` (`host`, `client_key`)'),
    'Tenant entry migration lost the one Host/client invariant',
);
entryBindingExpect(
    str_contains($migration, "CHECK (`status` IN ('active', 'disabled'))"),
    'Tenant entry migration lost its binding status constraint',
);

echo "MT07-TENANT-ENTRY-BINDING-001 passed\n";
