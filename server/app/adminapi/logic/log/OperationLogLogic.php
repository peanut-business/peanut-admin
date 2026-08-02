<?php
declare(strict_types=1);

namespace app\adminapi\logic\log;

use app\common\logic\BaseLogic;
use app\common\model\log\OperationLog;
use app\common\service\FileService;
use app\common\service\XlsxExportService;

class OperationLogLogic extends BaseLogic
{
    private const EXPORT_MAX_ROWS = 25000;
    private const EXPORT_DEFAULT_NAME = '操作日志';

    /** 分页列表，支持按 用户名/URI/方法 过滤 */
    public static function lists(array $params): array
    {
        $query = self::buildQuery($params);
        $count = $query->count();
        $pageSize = min(self::EXPORT_MAX_ROWS, max(1, (int)($params['page_size'] ?? 15)));
        if ((int)($params['export'] ?? 0) === 1) {
            return self::exportInfo($count, $pageSize);
        }
        if ((int)($params['export'] ?? 0) === 2) {
            return self::export($params, $count, $pageSize);
        }

        $pageNo = max(1, (int)($params['page_no'] ?? 1));
        $lists = self::buildQuery($params)
            ->order('id', 'desc')
            ->page($pageNo, min(100, $pageSize))
            ->select()->toArray();
        $pageSize = min(100, $pageSize);
        return compact('lists', 'count', 'pageNo', 'pageSize');
    }

    private static function buildQuery(array $params)
    {
        $query = OperationLog::where('id', '>', 0);
        if (!empty($params['username'])) {
            $query->where('username', 'like', '%' . trim((string)$params['username']) . '%');
        }
        if (!empty($params['uri'])) {
            $query->where('uri', 'like', '%' . trim((string)$params['uri']) . '%');
        }
        if (!empty($params['method'])) {
            $query->where('method', strtoupper((string)$params['method']));
        }
        if (!empty($params['ip'])) {
            $query->where('ip', 'like', '%' . trim((string)$params['ip']) . '%');
        }
        if (!empty($params['start_time']) && !empty($params['end_time'])) {
            $start = strtotime((string)$params['start_time']);
            $end = strtotime((string)$params['end_time']);
            if ($start !== false && $end !== false) {
                $query->whereBetween('create_time', [$start, $end]);
            }
        }
        return $query;
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
            throw new \RuntimeException('没有数据,无法导出');
        }
        $pageType = (int)($params['page_type'] ?? 0);
        if ($pageType === 1) {
            $pageStart = max(1, (int)($params['page_start'] ?? 1));
            $pageEnd = max($pageStart, (int)($params['page_end'] ?? $pageStart));
            $offset = ($pageStart - 1) * $pageSize;
            $limit = ($pageEnd - $pageStart + 1) * $pageSize;
        } else {
            $offset = 0;
            $limit = min($count, self::EXPORT_MAX_ROWS);
        }
        if ($limit > self::EXPORT_MAX_ROWS || $offset >= $count) {
            throw new \RuntimeException('导出范围无数据或超过25000条限制');
        }
        $rows = self::buildQuery($params)->order('id', 'desc')
            ->limit($offset, $limit)->select()->toArray();
        $sheetRows = array_map(static fn(array $row): array => [
            (int)$row['id'],
            (string)$row['username'],
            (string)$row['ip'],
            (string)$row['uri'],
            (string)$row['method'],
            (string)$row['params'],
            empty($row['create_time']) ? '' : date('Y-m-d H:i:s', (int)$row['create_time']),
        ], $rows);
        $uri = XlsxExportService::create(
            (string)($params['file_name'] ?? self::EXPORT_DEFAULT_NAME),
            ['ID', '管理员', 'IP', '请求地址', '方法', '参数', '操作时间'],
            $sheetRows
        );
        return ['url' => FileService::getFileUrl($uri), 'file_name' => basename($uri)];
    }

    /** 清空全部日志 */
    public static function clear(): void
    {
        // 无条件 delete 需显式 true
        OperationLog::where('id', '>', 0)->delete();
    }
}
