<?php
declare(strict_types=1);

namespace app\adminapi\logic\log;

use app\common\logic\BaseLogic;
use app\common\model\log\OperationLog;

class OperationLogLogic extends BaseLogic
{
    /** 分页列表，支持按 用户名/URI/方法 过滤 */
    public static function lists(array $params): array
    {
        $where = [];
        if (!empty($params['username'])) {
            $where[] = ['username', 'like', '%' . $params['username'] . '%'];
        }
        if (!empty($params['uri'])) {
            $where[] = ['uri', 'like', '%' . $params['uri'] . '%'];
        }
        if (!empty($params['method'])) {
            $where[] = ['method', '=', strtoupper((string)$params['method'])];
        }

        $pageNo   = max(1, (int)($params['page_no'] ?? 1));
        $pageSize = min(100, max(1, (int)($params['page_size'] ?? 15)));

        $count = OperationLog::where($where)->count();
        $lists = OperationLog::where($where)
            ->order('id', 'desc')
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        return compact('lists', 'count', 'pageNo', 'pageSize');
    }

    /** 清空全部日志 */
    public static function clear(): void
    {
        // 无条件 delete 需显式 true
        OperationLog::where('id', '>', 0)->delete();
    }
}
