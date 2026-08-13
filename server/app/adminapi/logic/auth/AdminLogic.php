<?php
declare(strict_types=1);

namespace app\adminapi\logic\auth;

use app\adminapi\service\AdminTokenService;
use app\common\logic\BaseLogic;
use app\common\model\auth\Admin;
use app\common\model\auth\AdminDept;
use app\common\model\auth\AdminJobs;
use app\common\model\auth\AdminRole;
use app\common\model\auth\AdminSession;
use app\common\model\auth\SystemRole;
use app\common\model\dept\Dept;
use app\common\model\dept\Jobs;
use app\common\service\FileService;
use app\common\service\XlsxExportService;
use app\common\service\org\OrgTenantContext;
use app\common\service\org\OrgTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\facade\Db;

class AdminLogic extends BaseLogic
{
    private const EXPORT_MAX_ROWS = 25000;
    private const EXPORT_DEFAULT_NAME = '管理员列表';

    /**
     * 将旧版 username/nickname/role_ids 请求转换为 LikeAdmin 业务字段。
     * 原字段仍由响应输出，保证现有 Peanut 调用方可渐进迁移。
     */
    public static function normalizeInput(array $params): array
    {
        $params = OrgTenantContext::withoutPayloadTenant($params);
        if (!array_key_exists('account', $params) && array_key_exists('username', $params)) {
            $params['account'] = $params['username'];
        }
        if (!array_key_exists('name', $params) && array_key_exists('nickname', $params)) {
            $params['name'] = $params['nickname'];
        }
        if (!array_key_exists('role_id', $params) && array_key_exists('role_ids', $params)) {
            $params['role_id'] = $params['role_ids'];
        }
        return $params;
    }

    public static function validationRules(string $scene): array
    {
        $rules = [
            'id' => 'require|integer|gt:0',
            'account' => 'require|length:1,32',
            'name' => 'require|length:1,16',
            'avatar' => 'max:255',
            'password' => 'length:6,32',
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

    /**
     * 管理员分页列表；export=1 返回导出信息，export=2 生成 XLSX 并返回 URL。
     *
     * @return array|false
     */
    public static function lists(TenantContext $context, array $params): array|false
    {
        try {
            $count = self::buildListQuery($context, $params)->count();
            $pageSize = (int)($params['page_size'] ?? 15);
            $pageSize = max(1, min(self::EXPORT_MAX_ROWS, $pageSize));

            if ((int)($params['export'] ?? 0) === 1) {
                return self::exportInfo($count, $pageSize);
            }

            if ((int)($params['export'] ?? 0) === 2) {
                return self::export($context, $params, $count, $pageSize);
            }

            $pageNo = max(1, (int)($params['page_no'] ?? 1));
            $rows = self::buildListQuery($context, $params)
                ->field([
                    'id', 'username', 'nickname', 'avatar', 'root', 'disable',
                    'login_time', 'login_ip', 'multipoint_login', 'create_time', 'update_time',
                ])
                ->page($pageNo, $pageSize)
                ->select()
                ->toArray();

            return [
                'lists' => self::formatRows($context, $rows),
                'count' => $count,
                'pageNo' => $pageNo,
                'pageSize' => $pageSize,
            ];
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function detail(TenantContext $context, int $id): array
    {
        $admin = self::admins($context)->field([
            'id', 'username', 'nickname', 'avatar', 'root', 'disable',
            'login_time', 'login_ip', 'multipoint_login', 'create_time', 'update_time',
        ])->where('id', $id)->findOrEmpty();
        if ($admin->isEmpty()) {
            return [];
        }
        return self::formatRows($context, [$admin->toArray()])[0];
    }

    public static function add(TenantContext $context, array $params): bool
    {
        $params = self::normalizeInput($params);
        Db::startTrans();
        try {
            $roleIds = self::normalizeIds($params['role_id'] ?? []);
            if ($roleIds === []) {
                throw new \RuntimeException('请选择角色');
            }
            self::assertUnique($context, (string)$params['account'], (string)$params['name']);
            self::assertRelationsOwned($context, $roleIds, self::normalizeIds($params['dept_id'] ?? []), self::normalizeIds($params['jobs_id'] ?? []));
            $salt = bin2hex(random_bytes(4));
            $admin = OrgTenantRepository::create($context, Admin::class, [
                'username' => (string)$params['account'],
                'nickname' => (string)$params['name'],
                'password' => (string)$params['password'],
                'salt' => $salt,
                'avatar' => (string)($params['avatar'] ?? ''),
                'disable' => (int)$params['disable'],
                'multipoint_login' => (int)$params['multipoint_login'],
            ]);

            self::replaceRelations(
                $context,
                (int)$admin->id,
                $roleIds,
                self::normalizeIds($params['dept_id'] ?? []),
                self::normalizeIds($params['jobs_id'] ?? [])
            );

            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function edit(TenantContext $context, array $params): bool
    {
        $params = self::normalizeInput($params);
        Db::startTrans();
        try {
            $admin = self::admins($context)->where('id', (int)$params['id'])->lock(true)->findOrEmpty();
            if ($admin->isEmpty()) {
                throw new \RuntimeException('管理员不存在');
            }
            if ((int)$admin->root === 1 && (int)$params['disable'] === 1) {
                throw new \RuntimeException('超级管理员不允许被禁用');
            }

            $currentRoleIds = self::normalizeIds(self::relations($context, AdminRole::class)->where('admin_id', $admin->id)->column('role_id'));
            $newRoleIds = self::normalizeIds($params['role_id'] ?? []);
            if ((int)$admin->root !== 1 && $newRoleIds === []) {
                throw new \RuntimeException('请选择角色');
            }
            $roleChanged = $currentRoleIds !== $newRoleIds;
            $statusChanged = (int)$admin->disable !== (int)$params['disable'];
            self::assertUnique($context, (string)$params['account'], (string)$params['name'], (int)$admin->id);

            $data = [
                'id' => (int)$admin->id,
                'username' => (string)$params['account'],
                'nickname' => (string)$params['name'],
                'disable' => (int)$params['disable'],
                'multipoint_login' => (int)$params['multipoint_login'],
            ];
            if (array_key_exists('avatar', $params)) {
                $data['avatar'] = (string)$params['avatar'];
            }
            if (!empty($params['password'])) {
                $data['salt'] = bin2hex(random_bytes(4));
                $data['password'] = (string)$params['password'];
            }
            $admin->save($data);

            $deptIds = array_key_exists('dept_id', $params)
                ? self::normalizeIds($params['dept_id'])
                : self::normalizeIds(self::relations($context, AdminDept::class)->where('admin_id', $admin->id)->column('dept_id'));
            $jobsIds = array_key_exists('jobs_id', $params)
                ? self::normalizeIds($params['jobs_id'])
                : self::normalizeIds(self::relations($context, AdminJobs::class)->where('admin_id', $admin->id)->column('jobs_id'));
            self::assertRelationsOwned($context, $newRoleIds, $deptIds, $jobsIds);
            self::replaceRelations($context, (int)$admin->id, $newRoleIds, $deptIds, $jobsIds);

            if ($statusChanged || $roleChanged) {
                self::forceExpireSessions($context, (int)$admin->id);
            }

            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function delete(TenantContext $context, int $id, int $selfId = 0): bool
    {
        if ($selfId > 0 && $id === $selfId) {
            self::setError('不能操作当前登录的管理员');
            return false;
        }

        Db::startTrans();
        try {
            $admin = self::admins($context)->where('id', $id)->lock(true)->findOrEmpty();
            if ($admin->isEmpty()) {
                throw new \RuntimeException('管理员不存在');
            }
            if ((int)$admin->root === 1) {
                throw new \RuntimeException('超级管理员不允许被删除');
            }

            self::forceExpireSessions($context, $id);
            $admin->delete();
            self::relations($context, AdminRole::class)->where('admin_id', $id)->delete();
            self::relations($context, AdminDept::class)->where('admin_id', $id)->delete();
            self::relations($context, AdminJobs::class)->where('admin_id', $id)->delete();

            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function updateStatus(TenantContext $context, int $id, int $disable, int $selfId = 0): bool
    {
        if ($selfId > 0 && $id === $selfId) {
            self::setError('不能操作当前登录的管理员');
            return false;
        }

        Db::startTrans();
        try {
            $admin = self::admins($context)->where('id', $id)->lock(true)->findOrEmpty();
            if ($admin->isEmpty()) {
                throw new \RuntimeException('管理员不存在');
            }
            if ((int)$admin->root === 1) {
                throw new \RuntimeException('超级管理员不允许被禁用');
            }
            if ((int)$admin->disable !== $disable) {
                $admin->save(['disable' => $disable]);
                self::forceExpireSessions($context, $id);
            }

            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    /** 编辑当前登录管理员的个人信息，保留 Peanut 既有密码哈希。 */
    public static function editSelf(TenantContext $context, int $adminId, array $params): bool
    {
        $admin = self::admins($context)->where('id', $adminId)->findOrEmpty();
        if ($admin->isEmpty()) {
            self::setError('管理员不存在');
            return false;
        }

        $data = [
            'id' => $adminId,
            'nickname' => (string)($params['name'] ?? $params['nickname'] ?? ''),
        ];
        if (isset($params['avatar'])) {
            $data['avatar'] = (string)$params['avatar'];
        }
        if (!empty($params['password'])) {
            $old = (string)($params['password_old'] ?? '');
            if (!hash_equals((string)$admin->password, md5(md5($old) . $admin->salt))) {
                self::setError('当前密码错误');
                return false;
            }
            $data['salt'] = bin2hex(random_bytes(4));
            $data['password'] = (string)$params['password'];
        }

        $admin->save($data);
        return true;
    }

    private static function buildListQuery(TenantContext $context, array $params)
    {
        $query = self::admins($context);
        if (!empty($params['account'])) {
            $query->whereLike('username', '%' . trim((string)$params['account']) . '%');
        }
        if (!empty($params['name'])) {
            $query->whereLike('nickname', '%' . trim((string)$params['name']) . '%');
        }
        if (isset($params['role_id']) && $params['role_id'] !== '') {
            $adminIds = self::relations($context, AdminRole::class)->where('role_id', (int)$params['role_id'])->column('admin_id');
            $query->whereIn('id', $adminIds === [] ? [-1] : $adminIds);
        }

        $field = in_array(($params['field'] ?? ''), ['id', 'create_time'], true)
            ? (string)$params['field']
            : 'id';
        $order = strtolower((string)($params['order_by'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        return $query->order([$field => $order]);
    }

    private static function exportInfo(int $count, int $pageSize): array
    {
        $sumPage = max(1, (int)ceil($count / $pageSize));
        return [
            'count' => $count,
            'page_size' => $pageSize,
            'sum_page' => $sumPage,
            'max_page' => (int)floor(self::EXPORT_MAX_ROWS / $pageSize),
            'all_max_size' => self::EXPORT_MAX_ROWS,
            'page_start' => 1,
            'page_end' => min($sumPage, 200),
            'file_name' => self::EXPORT_DEFAULT_NAME,
        ];
    }

    private static function export(TenantContext $context, array $params, int $count, int $pageSize): array
    {
        if ($count === 0) {
            throw new \RuntimeException('没有数据，无法导出');
        }

        $pageType = (int)($params['page_type'] ?? 0);
        if ($pageType === 1) {
            $pageStart = max(1, (int)($params['page_start'] ?? 1));
            $pageEnd = max($pageStart, (int)($params['page_end'] ?? $pageStart));
            $offset = ($pageStart - 1) * $pageSize;
            $limit = ($pageEnd - $pageStart + 1) * $pageSize;
            if ($limit > self::EXPORT_MAX_ROWS) {
                throw new \RuntimeException('已超出系统导出限制，当前最多导出25000条记录');
            }
            if ($offset >= $count) {
                throw new \RuntimeException('所选分页范围没有数据，无法导出');
            }
        } else {
            $offset = 0;
            $limit = min($count, self::EXPORT_MAX_ROWS);
        }

        $rows = self::buildListQuery($context, $params)
            ->field([
                'id', 'username', 'nickname', 'avatar', 'root', 'disable',
                'login_time', 'login_ip', 'multipoint_login', 'create_time', 'update_time',
            ])
            ->limit($offset, $limit)
            ->select()
            ->toArray();
        $rows = self::formatRows($context, $rows);
        $uri = XlsxExportService::createForTenant(
            $context,
            (string)($params['file_name'] ?? self::EXPORT_DEFAULT_NAME),
            ['账号', '名称', '角色', '部门', '创建时间', '最近登录时间', '最近登录IP', '状态'],
            array_map(static fn(array $row): array => [
                $row['account'],
                $row['name'],
                $row['role_name'],
                $row['dept_name'],
                $row['create_time'],
                $row['login_time'],
                $row['login_ip'],
                $row['disable_desc'],
            ], $rows)
        );

        return [
            'url' => FileService::getFileUrl($uri),
            'file_name' => basename($uri),
        ];
    }

    private static function formatRows(TenantContext $context, array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $adminIds = array_map(static fn(array $row): int => (int)$row['id'], $rows);
        $roleMap = self::relationMap(self::relations($context, AdminRole::class)->whereIn('admin_id', $adminIds)->select()->toArray(), 'role_id');
        $deptMap = self::relationMap(self::relations($context, AdminDept::class)->whereIn('admin_id', $adminIds)->select()->toArray(), 'dept_id');
        $jobsMap = self::relationMap(self::relations($context, AdminJobs::class)->whereIn('admin_id', $adminIds)->select()->toArray(), 'jobs_id');
        $roleNames = OrgTenantRepository::query($context, SystemRole::class)->column('name', 'id');
        $deptNames = OrgTenantRepository::query($context, Dept::class)->column('name', 'id');
        $jobsNames = OrgTenantRepository::query($context, Jobs::class)->column('name', 'id');

        foreach ($rows as &$row) {
            $id = (int)$row['id'];
            $roleIds = $roleMap[$id] ?? [];
            $deptIds = $deptMap[$id] ?? [];
            $jobsIds = $jobsMap[$id] ?? [];
            $roles = [];
            foreach ($roleIds as $roleId) {
                if (isset($roleNames[$roleId])) {
                    $roles[] = ['id' => $roleId, 'name' => $roleNames[$roleId]];
                }
            }

            $row = [
                'id' => $id,
                'account' => (string)$row['username'],
                'name' => (string)$row['nickname'],
                'username' => (string)$row['username'],
                'nickname' => (string)$row['nickname'],
                'avatar' => FileService::getFileUrl((string)($row['avatar'] ?? '')),
                'root' => (int)$row['root'],
                'disable' => (int)$row['disable'],
                'disable_desc' => (int)$row['disable'] === 1 ? '禁用' : '正常',
                'multipoint_login' => (int)($row['multipoint_login'] ?? 1),
                'login_time' => self::formatTime($row['login_time'] ?? 0),
                'login_ip' => (string)($row['login_ip'] ?? ''),
                'create_time' => self::formatTime($row['create_time'] ?? 0),
                'update_time' => self::formatTime($row['update_time'] ?? 0),
                'role_id' => $roleIds,
                'role_ids' => $roleIds,
                'dept_id' => $deptIds,
                'jobs_id' => $jobsIds,
                'role_name' => (int)$row['root'] === 1
                    ? '系统管理员'
                    : self::joinNames($roleIds, $roleNames),
                'dept_name' => self::joinNames($deptIds, $deptNames),
                'jobs_name' => self::joinNames($jobsIds, $jobsNames),
                'roles' => $roles,
            ];
        }
        unset($row);
        return $rows;
    }

    private static function relationMap(array $rows, string $relationField): array
    {
        $map = [];
        foreach ($rows as $row) {
            $adminId = (int)$row['admin_id'];
            $relationId = (int)$row[$relationField];
            if ($relationId > 0) {
                $map[$adminId][] = $relationId;
            }
        }
        foreach ($map as &$ids) {
            $ids = self::normalizeIds($ids);
        }
        unset($ids);
        return $map;
    }

    private static function joinNames(array $ids, array $names): string
    {
        $result = [];
        foreach ($ids as $id) {
            if (isset($names[$id])) {
                $result[] = $names[$id];
            }
        }
        return implode('/', $result);
    }

    private static function formatTime($value): string
    {
        if (empty($value)) {
            return '';
        }
        if (!is_numeric($value)) {
            return (string)$value;
        }
        return date('Y-m-d H:i:s', (int)$value);
    }

    private static function replaceRelations(TenantContext $context, int $adminId, array $roleIds, array $deptIds, array $jobsIds): void
    {
        self::relations($context, AdminRole::class)->where('admin_id', $adminId)->delete();
        self::relations($context, AdminDept::class)->where('admin_id', $adminId)->delete();
        self::relations($context, AdminJobs::class)->where('admin_id', $adminId)->delete();

        self::insertRelations($context, AdminRole::class, $adminId, 'role_id', $roleIds);
        self::insertRelations($context, AdminDept::class, $adminId, 'dept_id', $deptIds);
        self::insertRelations($context, AdminJobs::class, $adminId, 'jobs_id', $jobsIds);
    }

    private static function insertRelations(TenantContext $context, string $modelClass, int $adminId, string $field, array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $rows = array_map(
            static fn(int $id): array => ['tenant_id' => OrgTenantContext::tenantId($context), 'admin_id' => $adminId, $field => $id],
            $ids
        );
        (new $modelClass())->insertAll($rows);
    }

    private static function forceExpireSessions(TenantContext $context, int $adminId): void
    {
        if (self::admins($context)->where('id', $adminId)->count() !== 1) {
            return;
        }
        $tokens = AdminSession::where('admin_id', $adminId)->column('token');
        foreach ($tokens as $token) {
            AdminTokenService::expireToken((string)$token, true);
        }
    }

    private static function admins(TenantContext $context)
    {
        return OrgTenantRepository::query($context, Admin::class);
    }

    private static function relations(TenantContext $context, string $modelClass)
    {
        return OrgTenantRepository::query($context, $modelClass);
    }

    private static function assertUnique(TenantContext $context, string $account, string $name, int $exceptId = 0): void
    {
        foreach ([['username', trim($account), '账号已存在'], ['nickname', trim($name), '名称已存在']] as [$field, $value, $message]) {
            $query = self::admins($context)->where($field, $value);
            if ($exceptId > 0) {
                $query->where('id', '<>', $exceptId);
            }
            if ($query->count() > 0) {
                throw new \RuntimeException($message);
            }
        }
    }

    private static function assertRelationsOwned(TenantContext $context, array $roleIds, array $deptIds, array $jobsIds): void
    {
        OrgTenantRepository::assertOwnedIds($context, SystemRole::class, $roleIds, '选择的角色不存在');
        OrgTenantRepository::assertOwnedIds($context, Dept::class, $deptIds, '选择的部门不存在');
        OrgTenantRepository::assertOwnedIds($context, Jobs::class, $jobsIds, '选择的岗位不存在');
    }

    /** @return int[] */
    private static function normalizeIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }
}
