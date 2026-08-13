<?php
declare(strict_types=1);

// The fast CI fixture scanner executes every PHP fixture directly. This inspector
// is active only when the MT05 harness supplies an explicit matrix mode.
if (getenv('MT05_MODE') === false || getenv('MT05_MODE') === '') {
    return;
}

function requiredEnvironment(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        throw new RuntimeException("Missing environment: {$name}");
    }
    return $value;
}

function expectInvariant(bool $condition, string $code): void
{
    if (!$condition) {
        throw new RuntimeException($code);
    }
}

function tableExists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $statement->execute([$table]);
    return (int)$statement->fetchColumn() === 1;
}

try {
    $mode = requiredEnvironment('MT05_MODE');
    $deploymentMode = requiredEnvironment('MT05_DEPLOYMENT_MODE');
    $candidate = requiredEnvironment('MT05_CANDIDATE');
    $runId = requiredEnvironment('MT05_RUN_ID');
    $database = requiredEnvironment('DB_NAME');

    expectInvariant(
        in_array($mode, ['standalone-empty', 'v1-forward', 'multitenant-empty'], true),
        'MT05_MODE_INVALID'
    );
    expectInvariant(
        in_array($deploymentMode, ['standalone', 'multi-tenant'], true),
        'MT05_DEPLOYMENT_MODE_INVALID'
    );
    expectInvariant(preg_match('/^[0-9a-f]{40}$/', $candidate) === 1, 'MT05_CANDIDATE_INVALID');

    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            requiredEnvironment('DB_HOST'),
            requiredEnvironment('DB_PORT'),
            $database
        ),
        requiredEnvironment('DB_USER'),
        requiredEnvironment('DB_PASS'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    foreach ([
        'pa_schema_migration',
        'pa_account',
        'pa_tenant',
        'pa_tenant_member',
        'pa_role',
        'pa_member_role',
        'pa_default_tenant_bootstrap',
        'pa_module_installation',
        'pa_tenant_module',
    ] as $requiredTable) {
        expectInvariant(tableExists($pdo, $requiredTable), 'MT05_REQUIRED_TABLE_MISSING:' . $requiredTable);
    }

    $migrationDir = dirname(__DIR__, 3) . '/database/migrations';
    $migrationFiles = glob($migrationDir . '/*.sql') ?: [];
    sort($migrationFiles, SORT_STRING);
    $expectedLedger = [];
    foreach ($migrationFiles as $file) {
        $checksum = hash_file('sha256', $file);
        expectInvariant(is_string($checksum), 'MT05_MIGRATION_CHECKSUM_FAILED:' . basename($file));
        $expectedLedger[basename($file)] = $checksum;
    }

    $ledger = $pdo->query(
        'SELECT migration, checksum, batch, status, started_at, applied_at, error '
        . 'FROM pa_schema_migration ORDER BY migration'
    )->fetchAll();
    expectInvariant(count($ledger) === count($expectedLedger), 'MT05_LEDGER_COUNT_MISMATCH');
    foreach ($ledger as $row) {
        $name = (string)$row['migration'];
        expectInvariant(isset($expectedLedger[$name]), 'MT05_LEDGER_UNKNOWN_MIGRATION:' . $name);
        expectInvariant(hash_equals($expectedLedger[$name], (string)$row['checksum']), 'MT05_LEDGER_CHECKSUM_MISMATCH:' . $name);
        expectInvariant((string)$row['status'] === 'applied', 'MT05_LEDGER_NOT_APPLIED:' . $name);
        expectInvariant($row['applied_at'] !== null, 'MT05_LEDGER_APPLIED_AT_MISSING:' . $name);
        expectInvariant((string)$row['error'] === '', 'MT05_LEDGER_ERROR_PRESENT:' . $name);
    }

    $tableCount = (int)$pdo->query(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
    )->fetchColumn();
    expectInvariant($tableCount > 0, 'MT05_TABLE_COUNT_EMPTY');

    $defaultRows = $pdo->query(
        "SELECT id, code, name, display_name, status FROM pa_tenant WHERE code = 'default' ORDER BY id"
    )->fetchAll();
    expectInvariant(count($defaultRows) === 1, 'MT05_DEFAULT_TENANT_COUNT_INVALID');
    $defaultTenant = $defaultRows[0];
    expectInvariant((string)$defaultTenant['status'] === 'active', 'MT05_DEFAULT_TENANT_NOT_ACTIVE');

    $ownerStatement = $pdo->prepare(<<<'SQL'
SELECT b.tenant_id, b.owner_account_id, b.owner_member_id, b.status AS bootstrap_status,
       a.status AS account_status, tm.status AS member_status, COUNT(DISTINCT r.id) AS owner_role_count
FROM pa_default_tenant_bootstrap b
JOIN pa_account a ON a.id = b.owner_account_id
JOIN pa_tenant_member tm
  ON tm.tenant_id = b.tenant_id AND tm.id = b.owner_member_id AND tm.account_id = b.owner_account_id
JOIN pa_member_role mr
  ON mr.tenant_id = tm.tenant_id AND mr.tenant_member_id = tm.id
JOIN pa_role r
  ON r.tenant_id = mr.tenant_id AND r.id = mr.role_id AND r.`key` = 'core.tenant-owner'
WHERE b.id = 1 AND b.tenant_id = ?
GROUP BY b.tenant_id, b.owner_account_id, b.owner_member_id, b.status, a.status, tm.status
SQL);
    $ownerStatement->execute([(int)$defaultTenant['id']]);
    $owners = $ownerStatement->fetchAll();
    expectInvariant(count($owners) === 1, 'MT05_DEFAULT_OWNER_COUNT_INVALID');
    $owner = $owners[0];
    expectInvariant((string)$owner['bootstrap_status'] === 'completed', 'MT05_BOOTSTRAP_NOT_COMPLETED');
    expectInvariant((string)$owner['account_status'] === 'active', 'MT05_OWNER_ACCOUNT_NOT_ACTIVE');
    expectInvariant((string)$owner['member_status'] === 'active', 'MT05_OWNER_MEMBER_NOT_ACTIVE');
    expectInvariant((int)$owner['owner_role_count'] === 1, 'MT05_OWNER_ROLE_INVALID');

    $platformOperator = null;
    if ($mode === 'multitenant-empty') {
        $platformOperators = $pdo->query(<<<'SQL'
SELECT po.id, po.account_id, po.display_name, po.status, a.status AS account_status
FROM pa_platform_operator po
JOIN pa_account a ON a.id = po.account_id
WHERE po.status = 'active'
ORDER BY po.id
SQL)->fetchAll();
        expectInvariant(count($platformOperators) === 1, 'MT05_PLATFORM_OPERATOR_COUNT_INVALID');
        $platformOperator = $platformOperators[0];
        expectInvariant(
            (int)$platformOperator['account_id'] !== (int)$owner['owner_account_id'],
            'MT05_PLATFORM_OPERATOR_REUSES_DEFAULT_OWNER_ACCOUNT'
        );
        expectInvariant(
            (string)$platformOperator['account_status'] === 'active',
            'MT05_PLATFORM_OPERATOR_ACCOUNT_NOT_ACTIVE'
        );
        $platformMembershipStatement = $pdo->prepare(
            'SELECT COUNT(*) FROM pa_tenant_member WHERE account_id = ?'
        );
        $platformMembershipStatement->execute([(int)$platformOperator['account_id']]);
        expectInvariant(
            (int)$platformMembershipStatement->fetchColumn() === 0,
            'MT05_PLATFORM_OPERATOR_HAS_TENANT_MEMBERSHIP'
        );
    }

    $moduleInstallationCount = (int)$pdo->query('SELECT COUNT(*) FROM pa_module_installation')->fetchColumn();
    $tenantModuleCount = (int)$pdo->query('SELECT COUNT(*) FROM pa_tenant_module')->fetchColumn();
    $enabledTenantModuleCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM pa_tenant_module WHERE status = 'enabled'"
    )->fetchColumn();
    $invalidModuleInstallations = (int)$pdo->query(
        "SELECT COUNT(*) FROM pa_module_installation "
        . "WHERE status NOT IN ('installing','active','upgrading','maintenance','failed')"
    )->fetchColumn();
    $invalidTenantModules = (int)$pdo->query(
        "SELECT COUNT(*) FROM pa_tenant_module WHERE status NOT IN ('disabled','enabled','expired')"
    )->fetchColumn();
    $orphanTenantModules = (int)$pdo->query(<<<'SQL'
SELECT COUNT(*) FROM pa_tenant_module tm
LEFT JOIN pa_tenant t ON t.id = tm.tenant_id
LEFT JOIN pa_module_installation mi ON mi.module_key = tm.module_key
WHERE t.id IS NULL OR mi.id IS NULL
SQL)->fetchColumn();
    expectInvariant($invalidModuleInstallations === 0, 'MT05_MODULE_INSTALLATION_STATUS_INVALID');
    expectInvariant($invalidTenantModules === 0, 'MT05_TENANT_MODULE_STATUS_INVALID');
    expectInvariant($orphanTenantModules === 0, 'MT05_TENANT_MODULE_REFERENCE_INVALID');

    $result = [
        'schema_version' => 1,
        'candidate_commit' => $candidate,
        'run_id' => $runId,
        'mode' => $mode,
        'deployment_mode' => $deploymentMode,
        'database' => $database,
        'migration_ledger' => [
            'count' => count($ledger),
            'rows' => $ledger,
        ],
        'table_count' => $tableCount,
        'default_tenant' => $defaultTenant,
        'owner' => $owner,
        'module_baseline' => [
            'installation_count' => $moduleInstallationCount,
            'tenant_module_count' => $tenantModuleCount,
            'enabled_tenant_module_count' => $enabledTenantModuleCount,
            'invalid_installation_status_count' => $invalidModuleInstallations,
            'invalid_tenant_module_status_count' => $invalidTenantModules,
            'orphan_tenant_module_count' => $orphanTenantModules,
        ],
        'status' => 'passed',
    ];
    if ($platformOperator !== null) {
        $result['platform_operator'] = $platformOperator;
    }
    echo json_encode(
        $result,
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    ), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'MT05 invariant failure: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
