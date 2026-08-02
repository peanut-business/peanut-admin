<?php
declare(strict_types=1);

namespace app\adminapi\logic\finance;

use app\common\enum\AccountLogEnum;
use app\common\logic\BaseLogic;
use app\common\model\member\MemberBalanceLog;
use app\common\service\FileService;

/**
 * 账户流水（余额变动）Logic（只读）
 *
 * 数据源为 pa_member_balance_log，查询和响应语义对齐 LikeAdmin 1.9.4。
 */
class AccountLogLogic extends BaseLogic
{
    private const PAGE_SIZE_MAX = 25000;

    /**
     * 列表（分页），联表取会员昵称/编号
     * @param array<string,mixed> $params
     * @return array{lists:array,count:int,pageNo:int,pageSize:int}|false
     */
    public static function lists(array $params): array|false
    {
        try {
            if (in_array((int)($params['export'] ?? 0), [1, 2], true)) {
                throw new \RuntimeException('该列表不支持导出');
            }

            $pageType = (int)($params['page_type'] ?? 1);
            $pageNo = $pageType === 0 ? 1 : max(1, (int)($params['page_no'] ?? 1));
            $pageSize = $pageType === 0
                ? self::PAGE_SIZE_MAX
                : max(1, (int)($params['page_size'] ?? 15));

            $query = MemberBalanceLog::alias('al')
                ->join('member u', 'u.id = al.member_id')
                ->field(
                    'u.nickname,u.account,u.sn,u.avatar,u.mobile,'
                    . 'al.action,al.change_amount,al.left_amount,'
                    . 'al.change_type,al.source_sn,al.create_time'
                );

            if (($params['type'] ?? '') === 'um') {
                $query->whereIn('al.change_type', AccountLogEnum::getUserMoneyChangeTypes());
            }
            if (isset($params['change_type']) && $params['change_type'] !== '') {
                $query->where('al.change_type', (int)$params['change_type']);
            }
            if (!empty($params['user_info'])) {
                $userInfo = trim((string)$params['user_info']);
                $query->where(
                    'u.sn|u.nickname|u.mobile|u.account',
                    'like',
                    '%' . $userInfo . '%'
                );
            }
            if (!empty($params['start_time'])) {
                $query->where('al.create_time', '>=', strtotime((string)$params['start_time']));
            }
            if (!empty($params['end_time'])) {
                $query->where('al.create_time', '<=', strtotime((string)$params['end_time']));
            }

            $count = (clone $query)->count();
            $lists = $query->order('al.id', 'desc')
                ->page($pageNo, $pageSize)
                ->select()
                ->toArray();

            foreach ($lists as &$row) {
                $row['avatar'] = FileService::getFileUrl((string)($row['avatar'] ?? ''));
                $row['change_type_desc'] = AccountLogEnum::getChangeTypeDesc(
                    (int)$row['change_type']
                );
                $symbol = (int)$row['action'] === AccountLogEnum::INC ? '+' : '-';
                $row['change_amount'] = $symbol . number_format(
                    (float)$row['change_amount'],
                    2,
                    '.',
                    ''
                );
                $createTime = $row['create_time'] ?? '';
                $row['create_time'] = empty($createTime)
                    ? ''
                    : (is_numeric($createTime)
                        ? date('Y-m-d H:i:s', (int)$createTime)
                        : (string)$createTime);
            }
            unset($row);

            return [
                'lists' => $lists,
                'count' => $count,
                'pageNo' => $pageNo,
                'pageSize' => $pageSize,
            ];
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }
}
