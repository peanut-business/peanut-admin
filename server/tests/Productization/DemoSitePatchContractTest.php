<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$read = static function (string $path) use ($expect): string {
    $source = file_get_contents($path);
    $expect(is_string($source), 'source is unavailable: ' . $path);
    return $source;
};

$bootstrap = $read($root . '/server/app/platform/service/ApplicationTenantBootstrapService.php');
foreach ([
    'pa_role_permission',
    'pa_notice_scene',
    'pa_decorate_page',
    'pa_decorate_tabbar',
    'pa_transaction_setting',
    'pa_external_channel_binding',
] as $table) {
    $expect(str_contains($bootstrap, $table), 'new Tenant bootstrap omits ' . $table);
}
$provisioner = $read($root . '/server/app/platform/service/PdoTenantOwnerAdminProvisioner.php');
$expect(
    str_contains($provisioner, 'ApplicationTenantBootstrapService')
        && str_contains($provisioner, '->provision('),
    'Tenant owner provisioning does not initialize application-owned capabilities'
);

$seed = $read($root . '/server/database/seed-multi-tenant-demo.php');
foreach ([
    "PEANUT_DEMO_MODE') !== 'enabled",
    'peanut-admin-production-candidate-mysql84',
    'PEANUT_DEMO_TENANT_A_EMAIL',
    'PEANUT_DEMO_TENANT_B_EMAIL',
    'PEANUT_DEMO_SHARED_PASSWORD',
    'pa_tenant_entry_binding',
] as $token) {
    $expect(str_contains($seed, $token), 'demo seed lost required guard: ' . $token);
}

$admin = $read($root . '/server/app/adminapi/logic/auth/AdminLogic.php');
$expect(
    str_contains($admin, 'DemoAccountPolicy::assertPasswordChangeAllowed'),
    'demo password mutation is not rejected by the Server'
);

$invitation = $read($root . '/server/app/platform/invitation/TenantOwnerInvitationPublicService.php');
$expect(
    str_contains($invitation, 'ApplicationTenantBootstrapService')
        && str_contains($invitation, "(string)\$invitation['tenant_code']"),
    'public owner acceptance does not initialize application-owned Tenant defaults'
);

$deploy = $read($root . '/scripts/deploy-release');
foreach (['--confirm-destroy', '--overlay', 'seed-multi-tenant-demo.php', 'down --volumes'] as $token) {
    $expect(str_contains($deploy, $token), 'deployment flow lost contract token: ' . $token);
}
$expect(
    substr_count($deploy, '"${compose[@]}" run -T --rm --no-deps --entrypoint php') === 5,
    'remote one-shot Compose commands must not consume the deployment heredoc'
);

echo "DEMO-SITE-PATCH-CONTRACT-001 passed\n";
