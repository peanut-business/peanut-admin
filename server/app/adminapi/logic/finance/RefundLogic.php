<?php
declare(strict_types=1);

namespace app\adminapi\logic\finance;

use app\common\enum\RefundEnum;
use app\common\logic\BaseLogic;
use app\common\model\refund\RefundLog;
use app\common\model\refund\RefundRecord;
use app\common\service\FileService;

/** 退款统计、记录和操作日志查询。 */
class RefundLogic extends BaseLogic
{
    private const PAGE_SIZE_MAX = 25000;

    /** Peanut 按实际退款金额汇总；当前全额退款时与参考订单金额口径一致。 */
    public static function stat(): array
    {
        $records = RefundRecord::field('refund_amount,refund_status')->select()->toArray();
        $total = $ing = $success = $error = 0.0;

        foreach ($records as $record) {
            $amount = (float)$record['refund_amount'];
            $total += $amount;
            match ((int)$record['refund_status']) {
                RefundEnum::REFUND_ING => $ing += $amount,
                RefundEnum::REFUND_SUCCESS => $success += $amount,
                RefundEnum::REFUND_ERROR => $error += $amount,
                default => null,
            };
        }

        return [
            'total' => round($total, 2),
            'ing' => round($ing, 2),
            'success' => round($success, 2),
            'error' => round($error, 2),
        ];
    }

    /**
     * @return array{lists:array,count:int,page_no:int,page_size:int,extend:array}|false
     */
    public static function lists(array $params): array|false
    {
        try {
            if (in_array((int)($params['export'] ?? 0), [1, 2], true)) {
                throw new \RuntimeException('该列表不支持导出');
            }

            $extendQuery = self::buildBaseQuery($params, false);
            $extendRows = $extendQuery->fieldRaw(
                'count(r.id) as total'
                . ',count(if(r.refund_status=' . RefundEnum::REFUND_ING . ',1,null)) as ing'
                . ',count(if(r.refund_status=' . RefundEnum::REFUND_SUCCESS . ',1,null)) as `success`'
                . ',count(if(r.refund_status=' . RefundEnum::REFUND_ERROR . ',1,null)) as error'
            )->select()->toArray();
            $extend = $extendRows[0] ?? [];

            $query = self::buildBaseQuery($params, true);
            $count = (clone $query)->count();
            $pageType = (int)($params['page_type'] ?? 1);
            $pageNo = $pageType === 0
                ? 1
                : max(1, (int)($params['page_no'] ?? $params['page'] ?? 1));
            $pageSize = $pageType === 0
                ? self::PAGE_SIZE_MAX
                : max(1, (int)($params['page_size'] ?? $params['limit'] ?? 15));

            $lists = $query
                ->field('r.*,u.nickname,u.avatar')
                ->order('r.id', 'desc')
                ->page($pageNo, $pageSize)
                ->select()
                ->toArray();

            foreach ($lists as &$item) {
                $item['id'] = (int)$item['id'];
                $item['user_id'] = (int)$item['user_id'];
                $item['order_id'] = (int)$item['order_id'];
                $item['refund_way'] = (int)$item['refund_way'];
                $item['refund_type'] = (int)$item['refund_type'];
                $item['refund_status'] = (int)$item['refund_status'];
                $item['refund_type_text'] = RefundEnum::getTypeDesc($item['refund_type']);
                $item['refund_status_text'] = RefundEnum::getStatusDesc($item['refund_status']);
                $item['refund_way_text'] = RefundEnum::getWayDesc($item['refund_way']);
                $item['avatar'] = FileService::getFileUrl((string)($item['avatar'] ?? ''));
                $item['create_time'] = self::formatTime($item['create_time'] ?? 0);
                unset($item['refund_msg']);
            }
            unset($item);

            return [
                'lists' => $lists,
                'count' => $count,
                'page_no' => $pageNo,
                'page_size' => $pageSize,
                'extend' => [
                    'total' => (int)($extend['total'] ?? 0),
                    'ing' => (int)($extend['ing'] ?? 0),
                    'success' => (int)($extend['success'] ?? 0),
                    'error' => (int)($extend['error'] ?? 0),
                ],
            ];
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    /** 最新日志在前；支付渠道原始报文不对管理页面暴露。 */
    public static function refundLog(int $recordId): array
    {
        $lists = (new RefundLog())
            ->order(['id' => 'desc'])
            ->where('record_id', $recordId)
            ->hidden(['refund_msg'])
            ->append(['handler', 'refund_status_text'])
            ->select()
            ->toArray();

        foreach ($lists as &$item) {
            $item['id'] = (int)$item['id'];
            $item['record_id'] = (int)$item['record_id'];
            $item['user_id'] = (int)$item['user_id'];
            $item['handle_id'] = (int)$item['handle_id'];
            $item['refund_status'] = (int)$item['refund_status'];
            $item['create_time'] = self::formatTime($item['create_time'] ?? 0);
        }
        unset($item);
        return $lists;
    }

    private static function buildBaseQuery(array $params, bool $withStatus)
    {
        $query = RefundRecord::alias('r')->join('member u', 'u.id = r.user_id');

        if (!empty($params['sn'])) {
            $query->where('r.sn', trim((string)$params['sn']));
        }
        if (!empty($params['order_sn'])) {
            $query->where('r.order_sn', trim((string)$params['order_sn']));
        }
        if (isset($params['refund_type']) && $params['refund_type'] !== '') {
            $query->where('r.refund_type', (int)$params['refund_type']);
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
            $query->where('r.create_time', '>=', strtotime((string)$params['start_time']));
        }
        if (!empty($params['end_time'])) {
            $query->where('r.create_time', '<=', strtotime((string)$params['end_time']));
        }
        if ($withStatus && isset($params['refund_status']) && $params['refund_status'] !== '') {
            $query->where('r.refund_status', (int)$params['refund_status']);
        }

        return $query;
    }

    private static function formatTime(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }
        return is_numeric($value) ? date('Y-m-d H:i:s', (int)$value) : (string)$value;
    }
}
