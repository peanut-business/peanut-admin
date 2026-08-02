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
use think\facade\Db;
use ZipArchive;

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

    /**
     * 管理员分页列表；export=1 返回导出信息，export=2 生成 XLSX 并返回 URL。
     *
     * @return array|false
     */
    public static function lists(array $params): array|false
    {
        try {
            $count = self::buildListQuery($params)->count();
            $pageSize = (int)($params['page_size'] ?? 15);
            $pageSize = max(1, min(self::EXPORT_MAX_ROWS, $pageSize));

            if ((int)($params['export'] ?? 0) === 1) {
                return self::exportInfo($count, $pageSize);
            }

            if ((int)($params['export'] ?? 0) === 2) {
                return self::export($params, $count, $pageSize);
            }

            $pageNo = max(1, (int)($params['page_no'] ?? 1));
            $rows = self::buildListQuery($params)
                ->field([
                    'id', 'username', 'nickname', 'avatar', 'root', 'disable',
                    'login_time', 'login_ip', 'multipoint_login', 'create_time', 'update_time',
                ])
                ->page($pageNo, $pageSize)
                ->select()
                ->toArray();

            return [
                'lists' => self::formatRows($rows),
                'count' => $count,
                'pageNo' => $pageNo,
                'pageSize' => $pageSize,
            ];
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function detail(int $id): array
    {
        $admin = Admin::field([
            'id', 'username', 'nickname', 'avatar', 'root', 'disable',
            'login_time', 'login_ip', 'multipoint_login', 'create_time', 'update_time',
        ])->findOrEmpty($id);
        if ($admin->isEmpty()) {
            return [];
        }
        return self::formatRows([$admin->toArray()])[0];
    }

    public static function add(array $params): bool
    {
        $params = self::normalizeInput($params);
        Db::startTrans();
        try {
            $roleIds = self::normalizeIds($params['role_id'] ?? []);
            if ($roleIds === []) {
                throw new \RuntimeException('请选择角色');
            }
            $salt = bin2hex(random_bytes(4));
            $admin = Admin::create([
                'username' => (string)$params['account'],
                'nickname' => (string)$params['name'],
                'password' => (string)$params['password'],
                'salt' => $salt,
                'avatar' => (string)($params['avatar'] ?? ''),
                'disable' => (int)$params['disable'],
                'multipoint_login' => (int)$params['multipoint_login'],
            ]);

            self::replaceRelations(
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

    public static function edit(array $params): bool
    {
        $params = self::normalizeInput($params);
        Db::startTrans();
        try {
            $admin = Admin::where('id', (int)$params['id'])->lock(true)->findOrEmpty();
            if ($admin->isEmpty()) {
                throw new \RuntimeException('管理员不存在');
            }
            if ((int)$admin->root === 1 && (int)$params['disable'] === 1) {
                throw new \RuntimeException('超级管理员不允许被禁用');
            }

            $currentRoleIds = self::normalizeIds(AdminRole::where('admin_id', $admin->id)->column('role_id'));
            $newRoleIds = self::normalizeIds($params['role_id'] ?? []);
            if ((int)$admin->root !== 1 && $newRoleIds === []) {
                throw new \RuntimeException('请选择角色');
            }
            $roleChanged = $currentRoleIds !== $newRoleIds;
            $statusChanged = (int)$admin->disable !== (int)$params['disable'];

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
            Admin::update($data);

            $deptIds = array_key_exists('dept_id', $params)
                ? self::normalizeIds($params['dept_id'])
                : self::normalizeIds(AdminDept::where('admin_id', $admin->id)->column('dept_id'));
            $jobsIds = array_key_exists('jobs_id', $params)
                ? self::normalizeIds($params['jobs_id'])
                : self::normalizeIds(AdminJobs::where('admin_id', $admin->id)->column('jobs_id'));
            self::replaceRelations((int)$admin->id, $newRoleIds, $deptIds, $jobsIds);

            if ($statusChanged || $roleChanged) {
                self::forceExpireSessions((int)$admin->id);
            }

            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function delete(int $id, int $selfId = 0): bool
    {
        if ($selfId > 0 && $id === $selfId) {
            self::setError('不能操作当前登录的管理员');
            return false;
        }

        Db::startTrans();
        try {
            $admin = Admin::where('id', $id)->lock(true)->findOrEmpty();
            if ($admin->isEmpty()) {
                throw new \RuntimeException('管理员不存在');
            }
            if ((int)$admin->root === 1) {
                throw new \RuntimeException('超级管理员不允许被删除');
            }

            self::forceExpireSessions($id);
            Admin::destroy($id);
            AdminRole::where('admin_id', $id)->delete();
            AdminDept::where('admin_id', $id)->delete();
            AdminJobs::where('admin_id', $id)->delete();

            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function updateStatus(int $id, int $disable, int $selfId = 0): bool
    {
        if ($selfId > 0 && $id === $selfId) {
            self::setError('不能操作当前登录的管理员');
            return false;
        }

        Db::startTrans();
        try {
            $admin = Admin::where('id', $id)->lock(true)->findOrEmpty();
            if ($admin->isEmpty()) {
                throw new \RuntimeException('管理员不存在');
            }
            if ((int)$admin->root === 1) {
                throw new \RuntimeException('超级管理员不允许被禁用');
            }
            if ((int)$admin->disable !== $disable) {
                $admin->save(['disable' => $disable]);
                self::forceExpireSessions($id);
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
    public static function editSelf(int $adminId, array $params): bool
    {
        $admin = Admin::findOrEmpty($adminId);
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

        Admin::update($data);
        return true;
    }

    private static function buildListQuery(array $params)
    {
        $query = Admin::where([]);
        if (!empty($params['account'])) {
            $query->whereLike('username', '%' . trim((string)$params['account']) . '%');
        }
        if (!empty($params['name'])) {
            $query->whereLike('nickname', '%' . trim((string)$params['name']) . '%');
        }
        if (isset($params['role_id']) && $params['role_id'] !== '') {
            $adminIds = AdminRole::where('role_id', (int)$params['role_id'])->column('admin_id');
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

    private static function export(array $params, int $count, int $pageSize): array
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

        $rows = self::buildListQuery($params)
            ->field([
                'id', 'username', 'nickname', 'avatar', 'root', 'disable',
                'login_time', 'login_ip', 'multipoint_login', 'create_time', 'update_time',
            ])
            ->limit($offset, $limit)
            ->select()
            ->toArray();
        $rows = self::formatRows($rows);
        $uri = self::createXlsx($rows, (string)($params['file_name'] ?? self::EXPORT_DEFAULT_NAME));

        return [
            'url' => FileService::getFileUrl($uri),
            'file_name' => basename($uri),
        ];
    }

    private static function createXlsx(array $rows, string $requestedName): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('服务器未安装 ZipArchive 扩展，无法导出 XLSX');
        }

        $name = trim($requestedName) !== '' ? trim($requestedName) : self::EXPORT_DEFAULT_NAME;
        $name = preg_replace('/[\\\\\/:*?"<>|]+/u', '_', $name) ?: self::EXPORT_DEFAULT_NAME;
        $name = preg_replace('/\.xlsx$/i', '', $name) ?: self::EXPORT_DEFAULT_NAME;
        $fileName = $name . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.xlsx';
        $directory = public_path('storage/exports');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('导出目录创建失败');
        }
        $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

        $headers = ['账号', '名称', '角色', '部门', '创建时间', '最近登录时间', '最近登录IP', '状态'];
        $sheetRows = [$headers];
        foreach ($rows as $row) {
            $sheetRows[] = [
                $row['account'],
                $row['name'],
                $row['role_name'],
                $row['dept_name'],
                $row['create_time'],
                $row['login_time'],
                $row['login_ip'],
                $row['disable_desc'],
            ];
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('导出文件创建失败');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="管理员列表" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', self::worksheetXml($sheetRows));
        $zip->close();

        return 'storage/exports/' . $fileName;
    }

    private static function worksheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $xml .= '<row r="' . $number . '">';
            foreach (array_values($row) as $columnIndex => $value) {
                $cell = self::columnName($columnIndex + 1) . $number;
                $text = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string)$value) ?? '';
                $text = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $xml .= '<c r="' . $cell . '" t="inlineStr"><is><t xml:space="preserve">'
                    . $text . '</t></is></c>';
            }
            $xml .= '</row>';
        }
        return $xml . '</sheetData></worksheet>';
    }

    private static function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }
        return $name;
    }

    private static function formatRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $adminIds = array_map(static fn(array $row): int => (int)$row['id'], $rows);
        $roleMap = self::relationMap(AdminRole::whereIn('admin_id', $adminIds)->select()->toArray(), 'role_id');
        $deptMap = self::relationMap(AdminDept::whereIn('admin_id', $adminIds)->select()->toArray(), 'dept_id');
        $jobsMap = self::relationMap(AdminJobs::whereIn('admin_id', $adminIds)->select()->toArray(), 'jobs_id');
        $roleNames = SystemRole::column('name', 'id');
        $deptNames = Dept::column('name', 'id');
        $jobsNames = Jobs::column('name', 'id');

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

    private static function replaceRelations(int $adminId, array $roleIds, array $deptIds, array $jobsIds): void
    {
        AdminRole::where('admin_id', $adminId)->delete();
        AdminDept::where('admin_id', $adminId)->delete();
        AdminJobs::where('admin_id', $adminId)->delete();

        self::insertRelations(AdminRole::class, $adminId, 'role_id', $roleIds);
        self::insertRelations(AdminDept::class, $adminId, 'dept_id', $deptIds);
        self::insertRelations(AdminJobs::class, $adminId, 'jobs_id', $jobsIds);
    }

    private static function insertRelations(string $modelClass, int $adminId, string $field, array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $rows = array_map(
            static fn(int $id): array => ['admin_id' => $adminId, $field => $id],
            $ids
        );
        (new $modelClass())->insertAll($rows);
    }

    private static function forceExpireSessions(int $adminId): void
    {
        $tokens = AdminSession::where('admin_id', $adminId)->column('token');
        foreach ($tokens as $token) {
            AdminTokenService::expireToken((string)$token, true);
        }
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
