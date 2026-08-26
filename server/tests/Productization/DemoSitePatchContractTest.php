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
$run = static function (array $arguments, string $workingDirectory): array {
    $command = implode(' ', array_map('escapeshellarg', $arguments));
    $output = [];
    $exitCode = 0;
    exec('cd ' . escapeshellarg($workingDirectory) . ' && ' . $command . ' 2>&1', $output, $exitCode);
    return ['exit_code' => $exitCode, 'output' => implode("\n", $output)];
};
$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }
    foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            $removeTree($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
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
$workbench = $read($root . '/server/app/adminapi/logic/WorkbenchLogic.php');
$expect(
    str_contains($workbench, 'AdminAuthorizationService')
        && str_contains($workbench, "self::menuContainsPath(\$moduleMenus, '/system/file')"),
    'workbench file shortcut is not derived from the effective Tenant Module menu'
);
$overlayBuilder = $read($root . '/scripts/build-demo-site-patch');
$expect(
    str_contains($overlayBuilder, 'plugins.lock')
        && str_contains($overlayBuilder, 'plugins/official.file/plugin.json')
        && str_contains($overlayBuilder, 'server/app/adminapi/logic/WorkbenchLogic.php')
        && str_contains($overlayBuilder, 'server/app/command/TenantModuleProfile.php')
        && str_contains($overlayBuilder, 'server/app/command/PluginReconcile.php')
        && str_contains($overlayBuilder, 'server/app/platform/service/module/ProductTenantModuleProfileService.php')
        && str_contains($overlayBuilder, 'server/app/common/service/ProductAssetReferenceService.php')
        && str_contains($overlayBuilder, 'server/app/common/service/RichTextResourceService.php')
        && str_contains($overlayBuilder, 'server/app/common/service/decoration/DecorationSchemaService.php')
        && str_contains($overlayBuilder, 'server/app/common/service/file/FileObjectNamespace.php')
        && str_contains($overlayBuilder, 'web/src/components/menu/index.vue')
        && str_contains($overlayBuilder, 'web/src/core/tenant-session.ts')
        && str_contains($overlayBuilder, 'web/src/layout/default-layout.vue')
        && str_contains($overlayBuilder, 'web/src/modules/official-file/contribution.ts'),
    'demo overlay omits capability-aware menu or product-profile Runtime'
);
$expect(
    str_contains($overlayBuilder, 'migration_target_version')
        && str_contains($overlayBuilder, 'peanut-release:')
        && str_contains($overlayBuilder, 'version_greater_than'),
    'demo overlay does not bind its application migration target to metadata'
);
$expect(
    preg_match('/files=\(\n(?<files>.*?)\n\)/s', $overlayBuilder, $fileMatch) === 1,
    'demo overlay file closure is unavailable to the executable contract'
);
$temporaryRoot = sys_get_temp_dir() . '/peanut-demo-overlay-contract-' . bin2hex(random_bytes(6));
$repository = $temporaryRoot . '/repository';
$archive = $temporaryRoot . '/overlay.tar';
mkdir($repository . '/scripts', 0700, true);
file_put_contents($repository . '/scripts/build-demo-site-patch', $overlayBuilder);
chmod($repository . '/scripts/build-demo-site-patch', 0700);
try {
    $paths = preg_split('/\R/', trim((string)$fileMatch['files'])) ?: [];
    foreach ($paths as $path) {
        $path = trim($path);
        $expect(preg_match('#^[A-Za-z0-9._/-]+$#D', $path) === 1, 'invalid overlay fixture path: ' . $path);
        $absolute = $repository . '/' . $path;
        if (!is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0700, true);
        }
        $contents = str_ends_with($path, '.sql')
            ? "-- peanut-release: 3.0.6\nSELECT 1;\n"
            : $path . "\n";
        file_put_contents($absolute, $contents);
    }
    foreach ([
        ['git', 'init', '-q'],
        ['git', 'add', '.'],
        ['git', '-c', 'user.name=Contract', '-c', 'user.email=contract@example.test', 'commit', '-qm', 'fixture'],
        ['git', '-c', 'user.name=Contract', '-c', 'user.email=contract@example.test', 'tag', '-am', 'fixture', 'v3.0.5'],
    ] as $command) {
        $result = $run($command, $repository);
        $expect($result['exit_code'] === 0, 'cannot prepare overlay fixture repository: ' . $result['output']);
    }
    $head = trim((string)$run(['git', 'rev-parse', 'HEAD'], $repository)['output']);
    $build = $run([$repository . '/scripts/build-demo-site-patch', 'v3.0.5', $archive], $repository);
    $expect($build['exit_code'] === 0, 'clean overlay fixture failed to build: ' . $build['output']);
    $metadataResult = $run(['tar', '-xOf', $archive, './DEMO_PATCH_METADATA.json'], $repository);
    $expect($metadataResult['exit_code'] === 0, 'generated overlay metadata is unavailable');
    $metadata = json_decode($metadataResult['output'], true, 32, JSON_THROW_ON_ERROR);
    $expect(
        is_array($metadata) && ($metadata['overlay_commit'] ?? null) === $head,
        'generated overlay metadata does not bind the exact clean HEAD commit'
    );
    $expect(
        ($metadata['base_tag'] ?? null) === 'v3.0.5'
            && ($metadata['migration_target_version'] ?? null) === '3.0.6',
        'generated overlay metadata does not raise the migration target to the maximum release marker'
    );
    $migrationMetadata = array_values(array_filter(
        is_array($metadata['files'] ?? null) ? $metadata['files'] : [],
        static fn(mixed $file): bool => is_array($file)
            && ($file['path'] ?? null) === 'server/database/migrations/20260822-classify-official-article-permissions.sql'
    ));
    $expect(
        count($migrationMetadata) === 1
            && preg_match('/^[0-9a-f]{64}$/D', (string)($migrationMetadata[0]['sha256'] ?? '')) === 1,
        'generated overlay metadata does not bind the migration used to derive its target version'
    );

    file_put_contents($repository . '/untracked.txt', "dirty\n");
    $dirtyBuild = $run([$repository . '/scripts/build-demo-site-patch', 'v3.0.5', $archive . '.dirty'], $repository);
    $expect(
        $dirtyBuild['exit_code'] !== 0
            && str_contains($dirtyBuild['output'], 'source checkout has tracked or untracked changes'),
        'demo overlay builder does not reject an untracked dirty file'
    );
} finally {
    $removeTree($temporaryRoot);
}
$profile = $read($root . '/server/app/platform/service/module/ProductTenantModuleProfileService.php');
$expect(
    str_contains($profile, 'TenantModuleManager')
        && str_contains($profile, 'VerifiedTenantModuleRepository')
        && str_contains($profile, 'appendTenantSystem')
        && str_contains($profile, "'product_profile'")
        && !str_contains($profile, 'INSERT INTO pa_tenant_module'),
    'product profile bypasses the canonical TenantModule runtime or audit boundary'
);
$expect(
    str_contains($profile, "'standalone'")
        && str_contains($profile, "'demo'")
        && substr_count($profile, "'official.") >= 11,
    'standalone and demo product profiles are incomplete'
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
    'major release change ${current_tag} -> ${tag} requires --fresh',
    'mktemp ./.env.deploy.',
    'mv -f -- "$temporary" .env',
    'server/database/install.php',
    'server/database/install.php --migrate --target-version="$version"',
    'plugin:reconcile --official-locked',
    'tenant-module:apply-profile standalone',
    'tenant-module:apply-profile demo',
    'printf \'DEMO_MODE_SET=%q\\n\' "$DEMO_MODE_SET"',
    '--target-version="$migration_target_version"',
    'demo overlay migration target version does not match its migration files',
    'demo overlay metadata identity is invalid',
    'demo_overlay_commit=',
] as $token) {
    $expect(str_contains($deploy, $token), 'deployment flow lost contract token: ' . $token);
}
$expect(
    str_contains($deploy, 'requires distinct default Admin, Platform, Tenant A and Tenant B emails'),
    'fresh demo deployment does not reject identity collisions before database work'
);
$metadataValidationPosition = strpos($deploy, "jq -e --arg tag \"\$tag\" --arg commit \"\$expected_commit\"");
$buildPosition = strpos($deploy, '"${candidate_compose[@]}" build');
$destroyPosition = strpos($deploy, '"${current_compose[@]}" down --volumes');
$expect(
    $metadataValidationPosition !== false
        && $buildPosition !== false
        && $destroyPosition !== false
        && $metadataValidationPosition < $buildPosition
        && $buildPosition < $destroyPosition,
    'fresh deployment does not validate overlay migration identity before build and destructive work'
);
$freshMigration = 'server/database/install.php --migrate --target-version="$migration_target_version"';
$updateMigration = 'server/database/install.php --migrate --target-version="$version"';
$freshMigrationPosition = strpos($deploy, $freshMigration);
$reconcilePosition = strpos($deploy, 'server/think plugin:reconcile --official-locked', (int)$freshMigrationPosition);
$expect(
    substr_count($deploy, $freshMigration) === 1
        && substr_count($deploy, $updateMigration) === 1
        && $freshMigrationPosition !== false
        && $reconcilePosition !== false
        && $freshMigrationPosition < $reconcilePosition,
    'deployment does not keep tag migration for update while applying the verified overlay target before reconcile'
);
$expect(
    str_contains($deploy, 'computed_migration_target="$version"')
        && str_contains($deploy, '.files[].path | select(startswith("server/database/migrations/"))')
        && str_contains($deploy, '[[ "$migration_target_version" == "$computed_migration_target" ]]'),
    'deployment does not recompute the overlay migration maximum from its declared migration files'
);
$expect(
    substr_count($deploy, '"${compose[@]}" run -T --rm --no-deps --entrypoint php') === 12,
    'remote one-shot Compose commands must not consume the deployment heredoc'
);
$expect(
    substr_count($deploy, '</dev/null') === 12,
    'remote one-shot Compose commands must close inherited standard input'
);

$index = $read($root . '/server/app/api/logic/IndexLogic.php');
$expect(
    str_contains($index, 'in_array($host, $sharedHosts, true)')
        && str_contains($index, "return ['enabled' => false, 'email' => '', 'password' => ''];"),
    'demo public config does not fail closed for unknown Hosts'
);

echo "DEMO-SITE-PATCH-CONTRACT-001 passed\n";
