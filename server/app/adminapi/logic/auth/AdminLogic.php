<?php
declare(strict_types=1);

namespace app\adminapi\logic\auth;

use app\common\logic\BaseLogic;
use app\common\service\FileService;
use app\common\service\XlsxExportService;
use app\common\service\org\OrgTenantContext;
use app\common\service\DemoAccountPolicy;
use app\common\service\ApplicationPasswordPolicy;
use app\common\support\ExportPageInfo;
use app\common\support\PaginationInput;
use app\common\support\PositiveIds;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Identity\SelfService\AccountSelfService;
use PeanutAdmin\Kernel\Membership\Application\MemberAdminService;
use think\facade\Db;

/** Compatibility Admin CRUD backed by native accounts and TenantMembers. */
final class AdminLogic extends BaseLogic
{
    private const EXPORT_MAX_ROWS = 25000;
    private const EXPORT_DEFAULT_NAME = '管理员列表';

    public static function normalizeInput(array $params): array
    {
        self::clearError();
        $params = OrgTenantContext::withoutPayloadTenant($params);
        $params['account'] ??= $params['username'] ?? null;
        $params['name'] ??= $params['nickname'] ?? null;
        $params['role_id'] ??= $params['role_ids'] ?? null;
        return $params;
    }

    public static function validationRules(string $scene): array
    {
        self::clearError();
        $rules = [
            'id' => 'require|integer|gt:0',
            'account' => 'require|email|max:255',
            'name' => 'require|length:1,120',
            'avatar' => 'max:512',
            'password' => 'length:12,128',
            'password_confirm' => 'requireWith:password|confirm',
            'role_id' => 'array',
            'dept_id' => 'array',
            'jobs_id' => 'array',
            'disable' => 'require|in:0,1',
            'multipoint_login' => 'require|in:0,1',
        ];
        if ($scene === 'add') {
            $rules['password'] .= '|require';
            $rules['role_id'] .= '|require';
            unset($rules['id']);
        }
        return $rules;
    }

    public static function lists(TenantContext $context, array $params): array|false
    {
        self::clearError();
        try {
            $pageSize = max(1, min(
                self::EXPORT_MAX_ROWS,
                (int)($params['page_size'] ?? $params['limit'] ?? 15),
            ));
            $rows = self::rows($context, $params);
            $count = count($rows);
            if ((int)($params['export'] ?? 0) === 1) {
                return self::exportInfo($count, $pageSize);
            }
            if ((int)($params['export'] ?? 0) === 2) {
                return self::export($context, $params, $rows);
            }
            $pagination = PaginationInput::from($params);
            $pageNo = $pagination->page;
            return [
                'lists' => array_slice($rows, ($pageNo - 1) * $pageSize, $pageSize),
                'count' => $count,
                'pageNo' => $pageNo,
                'pageSize' => $pageSize,
            ];
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public static function detail(TenantContext $context, int $id): array
    {
        self::clearError();
        foreach (self::rows($context, ['id' => $id]) as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }
        return [];
    }

    public static function add(TenantContext $context, array $params): bool
    {
        self::clearError();
        $params = self::normalizeInput($params);
        try {
            $roles = self::normalizeIds($params['role_id'] ?? []);
            if ($roles === []) {
                throw new \RuntimeException('请选择角色');
            }
            $department = self::firstId($params['dept_id'] ?? []);
            $service = new MemberAdminService(self::pdo(), ApplicationPasswordPolicy::hasher());
            $member = $service->createPending(
                $context->tenantId,
                (string)$params['account'],
                (string)$params['name'],
                (string)$params['password'],
                $context->memberId,
                $context->accountId,
                $context->requestId,
            );
            if ($department !== null) {
                $member = $service->update(
                    $context->tenantId,
                    (int)$member['id'],
                    (string)$params['name'],
                    $department,
                    (int)$member['revision'],
                    $context->memberId,
                    $context->accountId,
                    $context->requestId,
                );
            }
            $member = $service->replaceRoles(
                $context->tenantId,
                (int)$member['id'],
                $roles,
                (int)$member['revision'],
                $context->memberId,
                $context->accountId,
                $context->requestId,
            );
            if ((int)$params['disable'] === 0) {
                $service->activate(
                    $context->tenantId,
                    (int)$member['id'],
                    (int)$member['revision'],
                    $context->memberId,
                    $context->accountId,
                    $context->requestId,
                );
            }
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public static function edit(TenantContext $context, array $params): bool
    {
        self::clearError();
        $params = self::normalizeInput($params);
        try {
            if (!empty($params['password'])) {
                throw new \RuntimeException('密码只能由账号本人修改');
            }
            $service = new MemberAdminService(self::pdo(), ApplicationPasswordPolicy::hasher());
            $member = $service->get($context->tenantId, (int)$params['id']);
            $member = $service->update(
                $context->tenantId,
                (int)$member['id'],
                (string)$params['name'],
                self::firstId($params['dept_id'] ?? []),
                (int)$member['revision'],
                $context->memberId,
                $context->accountId,
                $context->requestId,
            );
            $roles = self::normalizeIds($params['role_id'] ?? []);
            if ($roles === []) {
                throw new \RuntimeException('请选择角色');
            }
            $member = $service->replaceRoles(
                $context->tenantId,
                (int)$member['id'],
                $roles,
                (int)$member['revision'],
                $context->memberId,
                $context->accountId,
                $context->requestId,
            );
            self::transitionStatus($service, $context, $member, (int)$params['disable']);
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public static function delete(TenantContext $context, int $id, int $selfId = 0): bool
    {
        self::clearError();
        if ($id === $selfId) {
            self::setError('不能操作当前登录的管理员');
            return false;
        }
        try {
            $service = new MemberAdminService(self::pdo(), ApplicationPasswordPolicy::hasher());
            $member = $service->get($context->tenantId, $id);
            $service->leave(
                $context->tenantId,
                $id,
                (int)$member['revision'],
                $context->memberId,
                $context->accountId,
                $context->requestId,
            );
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public static function updateStatus(TenantContext $context, int $id, int $disable, int $selfId = 0): bool
    {
        self::clearError();
        if ($id === $selfId) {
            self::setError('不能操作当前登录的管理员');
            return false;
        }
        try {
            $service = new MemberAdminService(self::pdo(), ApplicationPasswordPolicy::hasher());
            self::transitionStatus($service, $context, $service->get($context->tenantId, $id), $disable);
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public static function editSelf(TenantContext $context, int $memberId, array $params): bool
    {
        self::clearError();
        try {
            if ($memberId !== $context->memberId) {
                throw new \DomainException('TENANT_ADMIN_PRINCIPAL_INVALID');
            }
            $service = new AccountSelfService(self::pdo(), ApplicationPasswordPolicy::hasher());
            $profile = $service->profile($context->tenantId, $memberId, $context->accountId);
            $service->updateProfile(
                $context->tenantId,
                $memberId,
                $context->accountId,
                (string)($params['name'] ?? $params['nickname'] ?? $profile['display_name']),
                array_key_exists('avatar', $params) ? (string)$params['avatar'] : ($profile['avatar_uri'] ?? null),
                $context->requestId,
            );
            if (!empty($params['password'])) {
                DemoAccountPolicy::assertPasswordChangeAllowed(self::pdo(), $context->accountId);
                $service->changePassword(
                    $context->tenantId,
                    $memberId,
                    $context->accountId,
                    $context->sessionKey,
                    (string)($params['password_old'] ?? ''),
                    (string)$params['password'],
                    request()->ip(),
                    request()->header('User-Agent'),
                    $context->requestId,
                );
            }
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    /** @return list<array<string,mixed>> */
    private static function rows(TenantContext $context, array $params): array
    {
        $sql = <<<'SQL'
SELECT tm.id, tm.account_id, tm.display_name, tm.primary_department_id, tm.status,
       tm.created_at, tm.updated_at, a.avatar_uri, a.last_login_at,
       c.identifier_normalized AS username,
       d.name AS department_name,
       GROUP_CONCAT(DISTINCT r.id ORDER BY r.id SEPARATOR ',') AS role_ids,
       GROUP_CONCAT(DISTINCT r.name ORDER BY r.`key` SEPARATOR '/') AS role_name,
       MAX(CASE WHEN r.`key` = 'core.tenant-owner' AND r.is_builtin = 1 AND r.status = 'active' THEN 1 ELSE 0 END) AS root
FROM pa_tenant_member tm
JOIN pa_account a ON a.id = tm.account_id
JOIN pa_credential c ON c.account_id = a.id AND c.identifier_type = 'email'
LEFT JOIN pa_department d ON d.tenant_id = tm.tenant_id AND d.id = tm.primary_department_id
LEFT JOIN pa_member_role mr ON mr.tenant_id = tm.tenant_id AND mr.tenant_member_id = tm.id
LEFT JOIN pa_role r ON r.tenant_id = mr.tenant_id AND r.id = mr.role_id
WHERE tm.tenant_id = :tenant_id
SQL;
        $bindings = ['tenant_id' => $context->tenantId];
        if (!empty($params['account'])) {
            $sql .= ' AND c.identifier_normalized LIKE :account';
            $bindings['account'] = '%' . trim((string)$params['account']) . '%';
        }
        if (!empty($params['name'])) {
            $sql .= ' AND tm.display_name LIKE :display_name';
            $bindings['display_name'] = '%' . trim((string)$params['name']) . '%';
        }
        if (!empty($params['id'])) {
            $sql .= ' AND tm.id = :member_id';
            $bindings['member_id'] = (int)$params['id'];
        }
        if (!empty($params['role_id'])) {
            $sql .= ' AND EXISTS (SELECT 1 FROM pa_member_role filter_mr WHERE filter_mr.tenant_id = tm.tenant_id AND filter_mr.tenant_member_id = tm.id AND filter_mr.role_id = :role_id)';
            $bindings['role_id'] = (int)$params['role_id'];
        }
        $sql .= ' GROUP BY tm.id, tm.account_id, tm.display_name, tm.primary_department_id, tm.status,'
            . ' tm.created_at, tm.updated_at, a.avatar_uri, a.last_login_at,'
            . ' c.identifier_normalized, d.name ORDER BY tm.id DESC';
        $statement = self::pdo()->prepare($sql);
        $statement->execute($bindings);
        $rows = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $roleIds = $row['role_ids'] === null || $row['role_ids'] === ''
                ? []
                : array_map('intval', explode(',', (string)$row['role_ids']));
            $rows[] = [
                'id' => (int)$row['id'],
                'account' => (string)$row['username'],
                'username' => (string)$row['username'],
                'name' => (string)($row['display_name'] ?: $row['username']),
                'nickname' => (string)($row['display_name'] ?: $row['username']),
                'avatar' => FileService::getFileUrl((string)($row['avatar_uri'] ?? '')),
                'root' => (int)$row['root'],
                'disable' => in_array($row['status'], ['active', 'pending'], true) ? 0 : 1,
                'disable_desc' => in_array($row['status'], ['active', 'pending'], true) ? '正常' : '禁用',
                'multipoint_login' => 1,
                'login_time' => (string)($row['last_login_at'] ?? ''),
                'login_ip' => '',
                'create_time' => (string)$row['created_at'],
                'update_time' => (string)$row['updated_at'],
                'role_id' => $roleIds,
                'role_ids' => $roleIds,
                'dept_id' => $row['primary_department_id'] === null ? [] : [(int)$row['primary_department_id']],
                'jobs_id' => [],
                'role_name' => (string)($row['role_name'] ?? ''),
                'dept_name' => (string)($row['department_name'] ?? ''),
                'jobs_name' => '',
                'roles' => [],
            ];
        }
        return $rows;
    }

    /** @param array<string,mixed> $member */
    private static function transitionStatus(MemberAdminService $service, TenantContext $context, array $member, int $disable): void
    {
        if ($disable === 1 && $member['status'] === 'active') {
            $service->suspend($context->tenantId, (int)$member['id'], (int)$member['revision'], $context->memberId, $context->accountId, $context->requestId);
        } elseif ($disable === 0 && in_array($member['status'], ['pending', 'suspended'], true)) {
            $service->activate($context->tenantId, (int)$member['id'], (int)$member['revision'], $context->memberId, $context->accountId, $context->requestId);
        }
    }

    private static function exportInfo(int $count, int $pageSize): array
    {
        return ExportPageInfo::from(
            $count,
            $pageSize,
            self::EXPORT_MAX_ROWS,
            self::EXPORT_DEFAULT_NAME,
        )->toArray();
    }

    /** @param list<array<string,mixed>> $rows */
    private static function export(TenantContext $context, array $params, array $rows): array
    {
        if ($rows === []) {
            throw new \RuntimeException('没有数据，无法导出');
        }
        $rows = array_slice($rows, 0, self::EXPORT_MAX_ROWS);
        $uri = XlsxExportService::createForTenant($context, (string)($params['file_name'] ?? self::EXPORT_DEFAULT_NAME),
            ['账号', '名称', '角色', '部门', '创建时间', '最近登录时间', '最近登录IP', '状态'],
            array_map(static fn(array $row): array => [$row['account'], $row['name'], $row['role_name'], $row['dept_name'], $row['create_time'], $row['login_time'], $row['login_ip'], $row['disable_desc']], $rows));
        return ['url' => FileService::getFileUrl($uri), 'file_name' => basename($uri)];
    }

    private static function firstId(array $ids): ?int
    {
        $ids = self::normalizeIds($ids);
        return $ids[0] ?? null;
    }

    /** @return list<int> */
    private static function normalizeIds(array $ids): array
    {
        return PositiveIds::normalize($ids, [PositiveIds::FILTER_INVALID, PositiveIds::SORT]);
    }

    private static function pdo(): PDO
    {
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('TENANT_DATABASE_CONNECTION_UNAVAILABLE');
        }
        return $pdo;
    }
}
