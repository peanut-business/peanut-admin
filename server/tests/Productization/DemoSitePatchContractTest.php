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
    'demoMultiAssertSeedState',
    'demoMultiEnsureSharedOwner',
    'demoMultiAssertFinalState',
    '$transactions->run(',
    '$passwords->verify(',
] as $token) {
    $expect(str_contains($seed, $token), 'demo seed lost required guard: ' . $token);
}
$expect(
    str_contains($seed, "demoMultiBinding(\$pdo, (int)\$defaultTenant['id'], \$sharedAdminHost, ['member-api']);"),
    'shared Admin demo Host must leave admin-web unbound so account-driven Tenant selection is exercised'
);
$expect(
    str_contains($seed, "'tenant-a'")
        && str_contains($seed, "'tenant-b'")
        && str_contains($seed, "['default', 'tenant-a', 'tenant-b']"),
    'demo seed does not preserve the default Tenant plus independent A/B Tenant codes'
);

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
foreach ([
    '--confirm-destroy',
    '--overlay',
    'seed-multi-tenant-demo.php',
    'down --volumes',
    '--fresh requires --confirm-destroy $TARGET',
    'major release change ${current_tag} -> ${TAG} requires --fresh',
    'mktemp ./.env.deploy.',
    'mv -f -- "$temporary" .env',
    'server/database/install.php',
] as $token) {
    $expect(str_contains($deploy, $token), 'deployment flow lost contract token: ' . $token);
}
$expect(
    str_contains($deploy, 'requires distinct default Admin, Platform, Tenant A and Tenant B emails'),
    'fresh demo deployment does not reject identity collisions before database work'
);
$buildPosition = strpos($deploy, '"${candidate_compose[@]}" build');
$destroyPosition = strpos($deploy, '"${current_compose[@]}" down --volumes');
$expect(
    $buildPosition !== false && $destroyPosition !== false && $buildPosition < $destroyPosition,
    'fresh deployment destroys the running target before the candidate image build succeeds'
);
$expect(
    substr_count($deploy, '"${compose[@]}" run -T --rm --no-deps --entrypoint php') === 5,
    'remote one-shot Compose commands must not consume the deployment heredoc'
);
$expect(
    substr_count($deploy, '</dev/null') === 4,
    'remote one-shot Compose commands must close inherited standard input'
);

$index = $read($root . '/server/app/api/logic/IndexLogic.php');
$expect(
    str_contains($index, 'in_array($host, $sharedHosts, true)')
        && str_contains($index, "return ['enabled' => false, 'email' => '', 'password' => ''];"),
    'demo public config does not fail closed for unknown Hosts'
);

echo "DEMO-SITE-PATCH-CONTRACT-001 passed\n";
