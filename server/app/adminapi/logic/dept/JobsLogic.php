<?php
declare(strict_types=1);

namespace app\adminapi\logic\dept;

use app\common\logic\BaseLogic;
use app\common\model\auth\AdminJobs;
use app\common\model\dept\Jobs;
use app\common\service\FileService;
use app\common\service\XlsxExportService;
use think\facade\Db;

class JobsLogic extends BaseLogic
{
    private const EXPORT_MAX_ROWS = 25000;
    private const EXPORT_DEFAULT_NAME = '岗位列表';

    /** 将 Peanut 旧版 is_disable 请求转换为 LikeAdmin status 契约。 */
    public static function normalizeInput(array $params): array
    {
        if (!array_key_exists('status', $params) && array_key_exists('is_disable', $params)) {
            $params['status'] = (int)$params['is_disable'] === 0 ? 1 : 0;
        }
        return $params;
    }

    /**
     * 岗位分页列表；export=1 返回导出信息，export=2 生成 XLSX 并返回 URL。
     *
     * @return array|false
     */
    public static function lists(array $params): array|false
    {
        $params = self::normalizeInput($params);
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
                ->append(['status_desc'])
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

    /** 全部正常岗位（供选择器使用）。 */
    public static function all(): array
    {
        return Jobs::where('status', 1)
            ->field('id,name,code,status,is_disable')
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();
    }

    public static function detail(int $id): array
    {
        $jobs = Jobs::findOrEmpty($id);
        if ($jobs->isEmpty()) {
            return [];
        }
        return self::formatRows([$jobs->append(['status_desc'])->toArray()])[0];
    }

    public static function add(array $params): bool
    {
        $params = self::normalizeInput($params);
        Db::startTrans();
        try {
            self::assertUnique((string)$params['name'], (string)$params['code']);
            $status = (int)$params['status'];
            Jobs::create([
                'name'       => trim((string)$params['name']),
                'code'       => trim((string)$params['code']),
                'sort'       => (int)($params['sort'] ?? 0),
                'status'     => $status,
                'is_disable' => $status === 1 ? 0 : 1,
                'remark'     => (string)($params['remark'] ?? ''),
            ]);
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
            $id = (int)$params['id'];
            $jobs = Jobs::where('id', $id)->lock(true)->findOrEmpty();
            if ($jobs->isEmpty()) {
                throw new \RuntimeException('岗位不存在');
            }
            self::assertUnique((string)$params['name'], (string)$params['code'], $id);
            $status = (int)$params['status'];
            $jobs->save([
                'name'       => trim((string)$params['name']),
                'code'       => trim((string)$params['code']),
                'sort'       => (int)($params['sort'] ?? 0),
                'status'     => $status,
                'is_disable' => $status === 1 ? 0 : 1,
                'remark'     => (string)($params['remark'] ?? ''),
            ]);
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function delete(int $id): bool
    {
        Db::startTrans();
        try {
            $jobs = Jobs::where('id', $id)->lock(true)->findOrEmpty();
            if ($jobs->isEmpty()) {
                throw new \RuntimeException('岗位不存在');
            }
            if (AdminJobs::where('jobs_id', $id)->count() > 0) {
                throw new \RuntimeException('已关联管理员，暂不可删除');
            }
            $jobs->delete();
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function updateStatus(int $id, int $status): bool
    {
        Db::startTrans();
        try {
            $jobs = Jobs::where('id', $id)->lock(true)->findOrEmpty();
            if ($jobs->isEmpty()) {
                throw new \RuntimeException('岗位不存在');
            }
            $jobs->save([
                'status' => $status,
                'is_disable' => $status === 1 ? 0 : 1,
            ]);
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    private static function buildListQuery(array $params)
    {
        $query = Jobs::where([]);
        if (!empty($params['code'])) {
            $query->where('code', trim((string)$params['code']));
        }
        if (!empty($params['name'])) {
            $query->whereLike('name', '%' . trim((string)$params['name']) . '%');
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int)$params['status']);
        }
        return $query->order(['sort' => 'desc', 'id' => 'desc']);
    }

    private static function assertUnique(string $name, string $code, int $exceptId = 0): void
    {
        $nameQuery = Jobs::where('name', trim($name));
        $codeQuery = Jobs::where('code', trim($code));
        if ($exceptId > 0) {
            $nameQuery->where('id', '<>', $exceptId);
            $codeQuery->where('id', '<>', $exceptId);
        }
        if ($nameQuery->count() > 0) {
            throw new \RuntimeException('岗位名称已存在');
        }
        if ($codeQuery->count() > 0) {
            throw new \RuntimeException('岗位编码已存在');
        }
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
            ->append(['status_desc'])
            ->limit($offset, $limit)
            ->select()
            ->toArray();
        $rows = self::formatRows($rows);
        $uri = XlsxExportService::create(
            (string)($params['file_name'] ?? self::EXPORT_DEFAULT_NAME),
            ['岗位编码', '岗位名称', '备注', '状态', '添加时间'],
            array_map(static fn(array $row): array => [
                $row['code'],
                $row['name'],
                $row['remark'],
                $row['status_desc'],
                $row['create_time'],
            ], $rows)
        );

        return [
            'url' => FileService::getFileUrl($uri),
            'file_name' => basename($uri),
        ];
    }

    private static function formatRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['sort'] = (int)$row['sort'];
            $row['status'] = (int)$row['status'];
            $row['is_disable'] = $row['status'] === 1 ? 0 : 1;
            $row['status_desc'] = $row['status'] === 1 ? '正常' : '停用';
            $row['create_time'] = self::formatTime($row['create_time'] ?? 0);
            $row['update_time'] = self::formatTime($row['update_time'] ?? 0);
        }
        unset($row);
        return $rows;
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
}
