<?php
declare(strict_types=1);

namespace app\common\service\tenant;

use PDO;
use PeanutAdmin\Kernel\Identity\EmailAddress;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Membership\TenantMemberStatus;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoIdentityRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoMembershipRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoPlatformRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTenantRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;
use PeanutAdmin\Kernel\Platform\Bootstrap\BootstrapService;
use RuntimeException;

final class DefaultTenantBootstrap
{
    public const MIGRATION = '20260812-default-tenant-bootstrap.sql';
    public const CORE_SOURCE_COMMIT = 'ef06da45c9e77ae4b194bfc1f859ec007aa0e022';

    /** @var array{tenant_id:int,account_id:int,member_id:int,operator_id:int}|null */
    private ?array $prepared = null;

    public function __construct(private readonly PDO $pdo) {}

    /** @return array{tenant_id:int,account_id:int,member_id:int,operator_id:int,status:string} */
    public function prepare(string $email, string $password): array
    {
        $email = EmailAddress::fromString($email)->value();
        $owner = $this->preflightLegacy($password);
        $this->ensureCoreSchema();

        if ($this->tableExists('pa_default_tenant_bootstrap')) {
            $completed = $this->completedState();
            if ($completed !== null) {
                $this->assertCompleted($completed);
                return $completed + ['operator_id' => $this->operatorId(), 'status' => 'already_bootstrapped'];
            }
        }

        $tenantCount = $this->count('pa_tenant');
        $operatorCount = $this->count('pa_platform_operator');
        if ($tenantCount === 0 && $operatorCount === 0) {
            $this->prepared = (new PdoTransactionManager($this->pdo))->run(
                function () use ($email, $password, $owner): array {
                    $bootstrap = $this->bootstrapService();
                    $platform = $bootstrap->bootstrapPlatformOwner(
                        $email,
                        $password,
                        (string)$owner['nickname'],
                        'mt02-platform-bootstrap'
                    );
                    $candidate = $bootstrap->provisionTenantOwnerCandidate(
                        $platform->operatorId,
                        'default',
                        'Peanut Admin',
                        $email,
                        null,
                        (string)$owner['nickname'],
                        'mt02-default-owner'
                    );
                    $bootstrap->activateTenantOwner(
                        $platform->operatorId,
                        $candidate->tenantId,
                        $candidate->memberId,
                        'mt02-default-owner-activate'
                    );
                    $bootstrap->activateTenant(
                        $platform->operatorId,
                        $candidate->tenantId,
                        'mt02-default-tenant-activate'
                    );
                    return [
                        'tenant_id' => $candidate->tenantId,
                        'account_id' => $candidate->accountId,
                        'member_id' => $candidate->memberId,
                        'operator_id' => $platform->operatorId,
                    ];
                }
            );
        } else {
            $this->prepared = $this->recoverPrepared($email, $password, $owner);
        }

        return $this->prepared + ['status' => 'prepared'];
    }

    /** @return array{tenant_id:int,account_id:int,member_id:int,status:string} */
    public function complete(): array
    {
        if ($this->prepared === null) {
            $completed = $this->completedState();
            if ($completed === null) {
                throw new RuntimeException('MT02_BOOTSTRAP_NOT_PREPARED');
            }
            $this->assertCompleted($completed);
            return $completed + ['status' => 'already_bootstrapped'];
        }

        $context = $this->prepared;
        (new PdoTransactionManager($this->pdo))->run(function () use ($context): void {
            $this->mapDepartments($context);
            $this->mapRoles($context);
            $this->mapAdmins($context);
            $this->mapRelations($context);
            $now = $this->now();
            $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_default_tenant_bootstrap (
    id, tenant_id, owner_account_id, owner_member_id, core_source_commit,
    schema_digest, status, completed_at, created_at, updated_at
) VALUES (
    1, :tenant_id, :account_id, :member_id, :source_commit,
    :schema_digest, 'completed', :completed_at, :created_at, :updated_at
)
SQL);
            $statement->execute([
                'tenant_id' => $context['tenant_id'],
                'account_id' => $context['account_id'],
                'member_id' => $context['member_id'],
                'source_commit' => self::CORE_SOURCE_COMMIT,
                'schema_digest' => self::schemaDigest(),
                'completed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            (new PdoAuditRepository($this->pdo))->appendTenantSystem(
                $context['tenant_id'],
                'tenant.legacy-admin-mapping.completed',
                'application.mt02.bootstrap',
                'mt02-legacy-mapping',
                [
                    'admin_count' => $this->count('pa_admin'),
                    'role_count' => $this->count('pa_system_role'),
                    'department_count' => $this->count('pa_dept'),
                ]
            );
            $this->assertMappings($context['tenant_id']);
        });

        return [
            'tenant_id' => $context['tenant_id'],
            'account_id' => $context['account_id'],
            'member_id' => $context['member_id'],
            'status' => 'bootstrapped',
        ];
    }

    /** @return array<string,mixed> */
    private function preflightLegacy(string $password): array
    {
        if ($password === '') {
            throw new RuntimeException('MT02_OWNER_PASSWORD_REQUIRED');
        }
        $roots = $this->rows(
            'SELECT id, nickname, password, salt FROM pa_admin '
            . 'WHERE root = 1 AND disable = 0 AND delete_time IS NULL ORDER BY id'
        );
        if (count($roots) !== 1) {
            throw new RuntimeException('MT02_OWNER_ROOT_AMBIGUOUS');
        }
        $owner = $roots[0];
        $legacyHash = md5(md5($password) . (string)$owner['salt']);
        if (!hash_equals((string)$owner['password'], $legacyHash)) {
            throw new RuntimeException('MT02_OWNER_CREDENTIAL_INVALID');
        }

        $this->assertNoOrphans('pa_admin_role', 'admin_id', 'pa_admin');
        $this->assertNoOrphans('pa_admin_role', 'role_id', 'pa_system_role');
        $this->assertNoOrphans('pa_admin_dept', 'admin_id', 'pa_admin');
        $this->assertNoOrphans('pa_admin_dept', 'dept_id', 'pa_dept');
        $this->assertNoOrphans('pa_admin_jobs', 'admin_id', 'pa_admin');
        $this->assertNoOrphans('pa_admin_jobs', 'jobs_id', 'pa_jobs');
        $this->assertNoOrphans('pa_system_role_menu', 'role_id', 'pa_system_role');
        $this->assertNoOrphans('pa_system_role_menu', 'menu_id', 'pa_system_menu');
        $this->assertDepartmentTree();
        return $owner;
    }

    private function ensureCoreSchema(): void
    {
        $present = [];
        foreach (KernelSchema::tableNames() as $table) {
            if ($this->tableExists($table)) {
                $present[] = $table;
            }
        }
        if ($present !== [] && count($present) !== count(KernelSchema::tableNames())) {
            throw new RuntimeException('MT02_CORE_SCHEMA_PARTIAL');
        }
        if ($present === []) {
            foreach (KernelSchema::tableNames() as $table) {
                $this->pdo->exec(KernelSchema::createSql($table));
            }
            $this->pdo->exec(KernelSchema::addTenantMemberDepartmentForeignKeySql());
        }
        foreach (['pa_account', 'pa_tenant', 'pa_tenant_member', 'pa_role', 'pa_department'] as $table) {
            if (!$this->tableExists($table)) {
                throw new RuntimeException('MT02_CORE_SCHEMA_INCOMPLETE');
            }
        }
    }

    /** @param array<string,mixed> $owner @return array{tenant_id:int,account_id:int,member_id:int,operator_id:int} */
    private function recoverPrepared(string $email, string $password, array $owner): array
    {
        if ($this->count('pa_tenant') !== 1 || $this->count('pa_platform_operator') !== 1) {
            throw new RuntimeException('MT02_CORE_STATE_AMBIGUOUS');
        }
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT t.id AS tenant_id, a.id AS account_id, tm.id AS member_id, po.id AS operator_id,
       c.secret_hash, tm.display_name
FROM pa_tenant t
JOIN pa_tenant_member tm ON tm.tenant_id = t.id AND tm.status = 'active'
JOIN pa_member_role mr ON mr.tenant_id = tm.tenant_id AND mr.tenant_member_id = tm.id
JOIN pa_role r ON r.tenant_id = mr.tenant_id AND r.id = mr.role_id
  AND r.`key` = 'core.tenant-owner' AND r.is_builtin = 1
JOIN pa_account a ON a.id = tm.account_id AND a.status = 'active'
JOIN pa_credential c ON c.account_id = a.id AND c.identifier_type = 'email'
  AND c.identifier_normalized = :email AND c.status = 'active'
JOIN pa_platform_operator po ON po.account_id = a.id AND po.status = 'active'
WHERE t.code = 'default' AND t.status = 'active'
SQL);
        $statement->execute(['email' => $email]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1
            || !password_verify($password, (string)$rows[0]['secret_hash'])
            || (string)$rows[0]['display_name'] !== (string)$owner['nickname']) {
            throw new RuntimeException('MT02_CORE_STATE_UNTRUSTED');
        }
        return [
            'tenant_id' => (int)$rows[0]['tenant_id'],
            'account_id' => (int)$rows[0]['account_id'],
            'member_id' => (int)$rows[0]['member_id'],
            'operator_id' => (int)$rows[0]['operator_id'],
        ];
    }

    /** @param array{tenant_id:int,account_id:int,member_id:int,operator_id:int} $context */
    private function mapDepartments(array $context): void
    {
        $pending = $this->rows('SELECT * FROM pa_dept ORDER BY id');
        $mapped = [0 => null];
        while ($pending !== []) {
            $progress = false;
            foreach ($pending as $index => $row) {
                $parent = (int)$row['pid'];
                if (!array_key_exists($parent, $mapped)) {
                    continue;
                }
                $now = $this->now();
                $status = $row['delete_time'] !== null ? 'archived' : ((int)$row['status'] === 1 ? 'active' : 'disabled');
                $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_department (
    tenant_id, parent_id, code, name, sort_order, status, archived_at, created_at, updated_at
) VALUES (
    :tenant_id, :parent_id, :code, :name, :sort_order, :status, :archived_at, :created_at, :updated_at
)
SQL);
                $statement->execute([
                    'tenant_id' => $context['tenant_id'],
                    'parent_id' => $mapped[$parent],
                    'code' => 'legacy.dept.' . $row['id'],
                    'name' => (string)$row['name'],
                    'sort_order' => (int)$row['sort'],
                    'status' => $status,
                    'archived_at' => $status === 'archived' ? $now : null,
                    'created_at' => $this->legacyTime((int)$row['create_time']),
                    'updated_at' => $this->legacyTime((int)$row['update_time']),
                ]);
                $departmentId = (int)$this->pdo->lastInsertId();
                $this->insertMap('pa_legacy_dept_tenant_map', 'legacy_dept_id', (int)$row['id'], 'department_id', $departmentId, $context['tenant_id']);
                $mapped[(int)$row['id']] = $departmentId;
                unset($pending[$index]);
                $progress = true;
            }
            $pending = array_values($pending);
            if (!$progress) {
                throw new RuntimeException('MT02_DEPARTMENT_TREE_INVALID');
            }
        }
    }

    /** @param array{tenant_id:int,account_id:int,member_id:int,operator_id:int} $context */
    private function mapRoles(array $context): void
    {
        foreach ($this->rows('SELECT * FROM pa_system_role ORDER BY id') as $row) {
            $now = $this->now();
            $archived = array_key_exists('delete_time', $row) && $row['delete_time'] !== null;
            $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_role (
    tenant_id, `key`, name, description, is_builtin, status, archived_at, created_at, updated_at
) VALUES (
    :tenant_id, :role_key, :name, :description, 0, :status, :archived_at, :created_at, :updated_at
)
SQL);
            $statement->execute([
                'tenant_id' => $context['tenant_id'],
                'role_key' => 'legacy.role.' . $row['id'],
                'name' => (string)$row['name'],
                'description' => (string)$row['desc'],
                'status' => $archived ? 'archived' : 'active',
                'archived_at' => $archived ? $now : null,
                'created_at' => $this->legacyTime((int)$row['create_time']),
                'updated_at' => $this->legacyTime((int)$row['update_time']),
            ]);
            $this->insertMap('pa_legacy_role_tenant_map', 'legacy_role_id', (int)$row['id'], 'role_id', (int)$this->pdo->lastInsertId(), $context['tenant_id']);
        }
    }

    /** @param array{tenant_id:int,account_id:int,member_id:int,operator_id:int} $context */
    private function mapAdmins(array $context): void
    {
        $identity = new PdoIdentityRepository($this->pdo);
        $memberships = new PdoMembershipRepository($this->pdo);
        foreach ($this->rows('SELECT * FROM pa_admin ORDER BY id') as $row) {
            if ((int)$row['root'] === 1 && (int)$row['disable'] === 0 && $row['delete_time'] === null) {
                $accountId = $context['account_id'];
                $memberId = $context['member_id'];
            } else {
                $account = $identity->createAccount((string)$row['nickname']);
                $member = $memberships->createPending($context['tenant_id'], $account->id, (string)$row['nickname']);
                $status = $row['delete_time'] !== null
                    ? TenantMemberStatus::Left
                    : ((int)$row['disable'] === 1 ? TenantMemberStatus::Suspended : TenantMemberStatus::Active);
                if ($status === TenantMemberStatus::Suspended) {
                    $member = $memberships->transition($context['tenant_id'], $member->id, TenantMemberStatus::Active);
                }
                $member = $memberships->transition($context['tenant_id'], $member->id, $status);
                $accountId = $account->id;
                $memberId = $member->id;
            }
            $this->insertAdminMap((int)$row['id'], $accountId, $memberId, $context['tenant_id']);
        }
    }

    /** @param array{tenant_id:int,account_id:int,member_id:int,operator_id:int} $context */
    private function mapRelations(array $context): void
    {
        $tenantId = $context['tenant_id'];
        $this->pdo->exec(<<<SQL
INSERT INTO pa_member_role (tenant_id, tenant_member_id, role_id, assigned_at)
SELECT {$tenantId}, am.tenant_member_id, rm.role_id, CURRENT_TIMESTAMP(3)
FROM pa_admin_role ar
JOIN pa_legacy_admin_tenant_map am ON am.tenant_id = {$tenantId} AND am.legacy_admin_id = ar.admin_id
JOIN pa_legacy_role_tenant_map rm ON rm.tenant_id = {$tenantId} AND rm.legacy_role_id = ar.role_id
SQL);
        $this->pdo->exec(<<<SQL
UPDATE pa_tenant_member tm
JOIN pa_legacy_admin_tenant_map am ON am.tenant_id = tm.tenant_id AND am.tenant_member_id = tm.id
LEFT JOIN (
    SELECT ad.admin_id, MIN(dm.department_id) AS department_id
    FROM pa_admin_dept ad
    JOIN pa_legacy_dept_tenant_map dm ON dm.tenant_id = {$tenantId} AND dm.legacy_dept_id = ad.dept_id
    JOIN pa_department d ON d.tenant_id = dm.tenant_id AND d.id = dm.department_id AND d.status = 'active'
    WHERE ad.tenant_id = {$tenantId}
    GROUP BY ad.admin_id
) primary_dept ON primary_dept.admin_id = am.legacy_admin_id
SET tm.primary_department_id = primary_dept.department_id,
    tm.authorization_revision = tm.authorization_revision + 1,
    tm.updated_at = CURRENT_TIMESTAMP(3)
WHERE tm.tenant_id = {$tenantId}
SQL);
    }

    private function assertMappings(int $tenantId): void
    {
        foreach ([
            ['pa_admin', 'pa_legacy_admin_tenant_map'],
            ['pa_system_role', 'pa_legacy_role_tenant_map'],
            ['pa_dept', 'pa_legacy_dept_tenant_map'],
        ] as [$source, $map]) {
            if ($this->count($source) !== (int)$this->pdo->query("SELECT COUNT(*) FROM {$map} WHERE tenant_id = {$tenantId}")->fetchColumn()) {
                throw new RuntimeException('MT02_MAPPING_INCOMPLETE');
            }
        }
        foreach (['pa_admin', 'pa_system_role', 'pa_dept', 'pa_jobs', 'pa_admin_role', 'pa_admin_dept', 'pa_admin_jobs', 'pa_system_role_menu'] as $table) {
            $foreign = (int)$this->pdo->query("SELECT COUNT(*) FROM {$table} WHERE tenant_id <> {$tenantId}")->fetchColumn();
            if ($foreign !== 0) {
                throw new RuntimeException('MT02_TENANT_BACKFILL_INCOMPLETE');
            }
        }
        $owners = (int)$this->pdo->query(<<<SQL
SELECT COUNT(DISTINCT tm.id) FROM pa_tenant_member tm
JOIN pa_member_role mr ON mr.tenant_id = tm.tenant_id AND mr.tenant_member_id = tm.id
JOIN pa_role r ON r.tenant_id = mr.tenant_id AND r.id = mr.role_id
WHERE tm.tenant_id = {$tenantId} AND tm.status = 'active' AND r.`key` = 'core.tenant-owner'
SQL)->fetchColumn();
        if ($owners !== 1) {
            throw new RuntimeException('MT02_OWNER_MAPPING_INVALID');
        }
    }

    /** @return array{tenant_id:int,account_id:int,member_id:int}|null */
    private function completedState(): ?array
    {
        if (!$this->tableExists('pa_default_tenant_bootstrap')) {
            return null;
        }
        $row = $this->pdo->query("SELECT * FROM pa_default_tenant_bootstrap WHERE id = 1 AND status = 'completed'")->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : [
            'tenant_id' => (int)$row['tenant_id'],
            'account_id' => (int)$row['owner_account_id'],
            'member_id' => (int)$row['owner_member_id'],
        ];
    }

    /** @param array{tenant_id:int,account_id:int,member_id:int} $completed */
    private function assertCompleted(array $completed): void
    {
        $row = $this->pdo->query('SELECT core_source_commit, schema_digest FROM pa_default_tenant_bootstrap WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        if ($row === false
            || !hash_equals(self::CORE_SOURCE_COMMIT, (string)$row['core_source_commit'])
            || !hash_equals(self::schemaDigest(), (string)$row['schema_digest'])) {
            throw new RuntimeException('MT02_BOOTSTRAP_IDENTITY_MISMATCH');
        }
        $this->assertMappings($completed['tenant_id']);
    }

    private function bootstrapService(): BootstrapService
    {
        return new BootstrapService(
            new PdoTransactionManager($this->pdo),
            new PdoIdentityRepository($this->pdo),
            new PdoTenantRepository($this->pdo),
            new PdoMembershipRepository($this->pdo),
            new PdoPlatformRepository($this->pdo),
            new PdoAuditRepository($this->pdo),
            new PasswordHasher()
        );
    }

    private function assertNoOrphans(string $relation, string $foreignKey, string $target): void
    {
        $targetKey = str_ends_with($foreignKey, '_id') ? 'id' : $foreignKey;
        $count = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM {$relation} r LEFT JOIN {$target} t ON t.{$targetKey} = r.{$foreignKey} WHERE t.{$targetKey} IS NULL"
        )->fetchColumn();
        if ($count !== 0) {
            throw new RuntimeException('MT02_LEGACY_ORPHAN_RELATION');
        }
    }

    private function assertDepartmentTree(): void
    {
        $rows = $this->rows('SELECT id, pid FROM pa_dept ORDER BY id');
        $parents = [];
        foreach ($rows as $row) {
            $parents[(int)$row['id']] = (int)$row['pid'];
        }
        foreach ($parents as $id => $parent) {
            $seen = [$id => true];
            $depth = 1;
            while ($parent !== 0) {
                if (!isset($parents[$parent]) || isset($seen[$parent]) || ++$depth > 10) {
                    throw new RuntimeException('MT02_DEPARTMENT_TREE_INVALID');
                }
                $seen[$parent] = true;
                $parent = $parents[$parent];
            }
        }
    }

    private function insertMap(string $table, string $legacyColumn, int $legacyId, string $coreColumn, int $coreId, int $tenantId): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO {$table} (tenant_id, {$legacyColumn}, {$coreColumn}, created_at) VALUES (?, ?, ?, ?)"
        );
        $statement->execute([$tenantId, $legacyId, $coreId, $this->now()]);
    }

    private function insertAdminMap(int $legacyId, int $accountId, int $memberId, int $tenantId): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_legacy_admin_tenant_map (
    tenant_id, legacy_admin_id, account_id, tenant_member_id, created_at
) VALUES (?, ?, ?, ?, ?)
SQL);
        $statement->execute([$tenantId, $legacyId, $accountId, $memberId, $this->now()]);
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $statement->execute([$table]);
        return (int)$statement->fetchColumn() === 1;
    }

    private function count(string $table): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    /** @return list<array<string,mixed>> */
    private function rows(string $sql): array
    {
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function operatorId(): int
    {
        return (int)$this->pdo->query("SELECT id FROM pa_platform_operator WHERE status = 'active' ORDER BY id LIMIT 1")->fetchColumn();
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s.000');
    }

    private function legacyTime(int $timestamp): string
    {
        return $timestamp > 0 ? gmdate('Y-m-d H:i:s.000', $timestamp) : $this->now();
    }

    private static function schemaDigest(): string
    {
        $sql = [];
        foreach (KernelSchema::tableNames() as $table) {
            $sql[] = KernelSchema::createSql($table);
        }
        $sql[] = KernelSchema::addTenantMemberDepartmentForeignKeySql();
        return hash('sha256', implode("\n", $sql));
    }
}
