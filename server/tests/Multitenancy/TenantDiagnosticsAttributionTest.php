<?php
declare(strict_types=1);

use PeanutAdmin\Kernel\Tenancy\TenantScope;
use PeanutAdmin\OpsConsole\Logs\TenantDiagnosticAttributes;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectTenantDiagnostics(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$scope = TenantScope::fromTrustedContext(101, 'crontab:v1:tenant=101:job=7:window=42');
$attributes = TenantDiagnosticAttributes::fromScope($scope);
expectTenantDiagnostics($attributes === [
    'scope' => 'tenant',
    'tenant_id' => 101,
    'correlation_id' => 'crontab:v1:tenant=101:job=7:window=42',
], 'trusted Tenant diagnostic attributes changed shape');
expectTenantDiagnostics(
    TenantDiagnosticAttributes::fromScope($scope) === $attributes,
    'one trusted command scope did not retain a stable correlation ID'
);

$serverRoot = dirname(__DIR__, 2);
$refund = (string)file_get_contents($serverRoot . '/app/command/RefundReconcile.php');
$demo = (string)file_get_contents($serverRoot . '/app/command/CrontabDemo.php');
expectTenantDiagnostics($refund !== '' && $demo !== '', 'Tenant-aware command source is unavailable');

$refundRequire = strpos($refund, 'ScheduledTenantContext::require()');
$refundAttributes = strpos($refund, 'TenantDiagnosticAttributes::fromScope($scope)');
$refundQuery = strpos($refund, 'FinanceTenantRepository::records($scope)');
expectTenantDiagnostics(
    $refundRequire !== false && $refundAttributes !== false && $refundQuery !== false
        && $refundRequire < $refundAttributes && $refundAttributes < $refundQuery,
    'refund reconciliation no longer refuses before diagnostics and business queries'
);

$events = [
    'refund_reconcile_related_data_missing',
    'refund_reconcile_gateway_query_failed',
    'refund_reconcile_gateway_status_unknown',
    'refund_reconcile_persist_failed',
];
expectTenantDiagnostics(substr_count($refund, 'Log::warning(') === count($events), 'refund warning event count changed');
foreach ($events as $event) {
    expectTenantDiagnostics(
        str_contains($refund, "Log::warning('{$event}', \$diagnostics + ["),
        "{$event} lost structured Tenant attribution"
    );
}
expectTenantDiagnostics(
    !str_contains($refund, '$e->getMessage()'),
    'refund diagnostics expose exception messages that may contain sensitive provider data'
);
expectTenantDiagnostics(
    !str_contains($refund, "'receipt' =>") && !str_contains($refund, "'token' =>")
        && !str_contains($refund, "'password' =>") && !str_contains($refund, "'secret' =>"),
    'refund diagnostic attributes contain prohibited sensitive fields'
);

$demoRequire = strpos($demo, 'ScheduledTenantContext::require()');
$demoAttributes = strpos($demo, 'TenantDiagnosticAttributes::fromScope($scope)');
$demoLog = strpos($demo, 'Log::info($msg, $diagnostics)');
expectTenantDiagnostics(
    $demoRequire !== false && $demoAttributes !== false && $demoLog !== false
        && $demoRequire < $demoAttributes && $demoAttributes < $demoLog,
    'demo command lost fail-closed structured Tenant attribution'
);
expectTenantDiagnostics(
    str_contains($demo, "'[crontab:demo] tenant_id=%d executed at %s'")
        && str_contains($demo, '$output->writeln($msg)'),
    'demo command output compatibility changed'
);

echo "MT03-TENANT-DIAGNOSTICS-ATTRIBUTION-001 passed\n";
