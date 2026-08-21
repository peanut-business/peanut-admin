<?php
declare(strict_types=1);

namespace app\adminapi\logic\member;

use DateTimeImmutable;
use PDO;
use app\common\enum\AccountLogEnum;
use app\common\enum\MemberChannelEnum;
use app\common\logic\BaseLogic;
use app\common\model\member\Member;
use app\common\model\member\MemberTagRelation;
use app\common\service\FileService;
use app\common\service\MemberBalanceService;
use app\common\service\member\MemberTenantContext;
use app\common\service\member\MemberTenantRepository;
use app\common\service\XlsxExportService;
use app\common\support\ExportPageInfo;
use app\common\support\PaginationInput;
use app\common\support\PositiveIds;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Idempotency\IdempotencyKey;
use PeanutAdmin\Kernel\Idempotency\PdoIdempotencyRepository;
use think\facade\Db;

class MemberLogic extends BaseLogic
{
    private const EXPORT_MAX_ROWS = 25000;
    private const EXPORT_DEFAULT_NAME = '用户列表';

    /**
     * 用户分页列表；export=1 返回导出信息，export=2 生成 XLSX 并返回 URL。
     *
     * @return array|false
     */
    public static function lists(TenantContext $context, array $params): array|false
    {
        try {
            $count = self::buildListQuery($context, $params)->count();
            $pageSize = (int)($params['page_size'] ?? $params['limit'] ?? 15);
            $pageSize = max(1, min(self::EXPORT_MAX_ROWS, $pageSize));

            if ((int)($params['export'] ?? 0) === 1) {
                return self::exportInfo($count, $pageSize);
            }
            if ((int)($params['export'] ?? 0) === 2) {
                return self::export($context, $params, $count, $pageSize);
            }

            $pagination = PaginationInput::from($params);
            $pageNo = $pagination->page;
            $rows = self::buildListQuery($context, $params)
                ->page($pageNo, $pageSize)
                ->select()
                ->toArray();
            $rows = self::hydrateTags($context, $rows);

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

    public static function detail(TenantContext $context, int $id): array
    {
        $member = MemberTenantRepository::members($context)->field([
            'id', 'sn', 'account', 'nickname', 'avatar', 'real_name',
            'sex', 'mobile', 'create_time', 'login_time', 'channel',
            'user_money',
        ])->findOrEmpty($id);
        if ($member->isEmpty()) {
            return [];
        }

        $data = $member->toArray();
        $data['id'] = (int)$data['id'];
        $data['sex'] = (int)$member->getData('sex');
        $data['channel'] = MemberChannelEnum::getDesc((int)$member->getData('channel'));
        $data['create_time'] = self::formatTime($member->getData('create_time'));
        $data['login_time'] = self::formatTime($member->getData('login_time'));
        $data['user_money'] = (float)$member->getData('user_money');
        $data['balance'] = $data['user_money'];
        return $data;
    }

    private static function buildListQuery(TenantContext $context, array $params)
    {
        $query = MemberTenantRepository::members($context);
        if (!empty($params['keyword'])) {
            $keyword = trim((string)$params['keyword']);
            $query->where('sn|nickname|mobile|account', 'like', '%' . $keyword . '%');
        }
        if (!empty($params['channel'])) {
            $query->where('channel', (int)$params['channel']);
        }
        if (!empty($params['create_time_start'])) {
            $query->where('create_time', '>=', strtotime((string)$params['create_time_start']));
        }
        if (!empty($params['create_time_end'])) {
            $query->where('create_time', '<=', strtotime((string)$params['create_time_end']));
        }

        // Peanut 原有状态筛选作为兼容扩展保留，不替代参考筛选字段。
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int)$params['status']);
        }
        return $query->order('id', 'desc');
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
            ->limit($offset, $limit)
            ->select()
            ->toArray();
        $rows = self::hydrateTags($context, $rows);
        $rows = self::formatRows($rows);
        $uri = XlsxExportService::createForTenant(
            $context,
            (string)($params['file_name'] ?? self::EXPORT_DEFAULT_NAME),
            ['用户编号', '用户昵称', '账号', '手机号码', '注册来源', '注册时间'],
            array_map(static fn(array $row): array => [
                $row['sn'],
                $row['nickname'],
                $row['account'],
                $row['mobile'],
                $row['channel'],
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
        $sexDesc = [0 => '未知', 1 => '男', 2 => '女'];
        foreach ($rows as &$row) {
            $sex = (int)($row['sex'] ?? 0);
            $channel = (int)($row['channel'] ?? 0);
            $row['id'] = (int)$row['id'];
            $row['sex_value'] = $sex;
            $row['sex'] = $sexDesc[$sex] ?? '未知';
            $row['channel_value'] = $channel;
            $row['channel'] = MemberChannelEnum::getDesc($channel);
            $row['status'] = (int)($row['status'] ?? 1);
            $row['is_disable'] = $row['status'] === 1 ? 0 : 1;
            $row['user_money'] = (float)($row['user_money'] ?? 0);
            $row['balance'] = $row['user_money'];
            $row['total_recharge_amount'] = (float)($row['total_recharge_amount'] ?? 0);
            $row['create_time'] = self::formatTime($row['create_time'] ?? 0);
            $row['update_time'] = self::formatTime($row['update_time'] ?? 0);
            $row['login_time'] = self::formatTime($row['login_time'] ?? 0);
            $row['tag_ids'] = array_map('intval', array_column($row['tags'] ?? [], 'id'));
        }
        unset($row);
        return $rows;
    }

    private static function hydrateTags(TenantContext $context, array $rows): array
    {
        $memberIds = array_values(array_unique(array_map('intval', array_column($rows, 'id'))));
        if ($memberIds === []) {
            return $rows;
        }
        $relations = MemberTenantRepository::relations($context)
            ->whereIn('member_id', $memberIds)->select()->toArray();
        $tagIds = array_values(array_unique(array_map('intval', array_column($relations, 'tag_id'))));
        $tags = $tagIds === [] ? [] : MemberTenantRepository::tags($context)
            ->whereIn('id', $tagIds)->column('*', 'id');
        $byMember = [];
        foreach ($relations as $relation) {
            $tag = $tags[(int)$relation['tag_id']] ?? null;
            if ($tag !== null) {
                $byMember[(int)$relation['member_id']][] = $tag;
            }
        }
        foreach ($rows as &$row) {
            $row['tags'] = $byMember[(int)$row['id']] ?? [];
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

    public static function add(TenantContext $context, array $params): bool
    {
        Db::startTrans();
        try {
            $member = MemberTenantRepository::createMember($context, [
                'sn'       => Member::generateSn($context),
                'nickname' => $params['nickname'],
                'avatar'   => $params['avatar']   ?? '',
                'mobile'   => $params['mobile']   ?? '',
                'email'    => $params['email']    ?? '',
                'sex'      => (int)($params['sex'] ?? 0),
                'birthday' => $params['birthday']  ?? null,
                'status'   => (int)($params['status'] ?? 1),
            ]);
            if (!empty($params['tag_ids'])) {
                self::syncTags($context, (int)$member->id, $params['tag_ids']);
            }
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function editProfile(TenantContext $context, array $params): bool
    {
        Db::startTrans();
        try {
            $member = MemberTenantRepository::members($context)->where('id', (int)$params['id'])->findOrEmpty();
            if ($member->isEmpty()) {
                throw new \RuntimeException('用户不存在');
            }
            $data = [];
            foreach (['nickname', 'avatar', 'mobile', 'email', 'birthday'] as $f) {
                if (isset($params[$f])) $data[$f] = $params[$f];
            }
            foreach (['sex', 'status'] as $f) {
                if (isset($params[$f])) $data[$f] = (int)$params[$f];
            }
            $member->save($data);
            if (array_key_exists('tag_ids', $params)) {
                self::syncTags($context, (int)$params['id'], (array)($params['tag_ids'] ?? []));
            }
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    /** LikeAdmin 后台用户详情的单字段更新语义。 */
    public static function setUserInfo(TenantContext $context, array $params): bool
    {
        try {
            $updated = MemberTenantRepository::members($context)->where('id', (int)$params['id'])->update([
                (string)$params['field'] => $params['value'],
            ]);
            if ($updated !== 1) {
                throw new \RuntimeException('用户不存在');
            }
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function updateStatus(TenantContext $context, int $id, int $status): bool
    {
        try {
            if (MemberTenantRepository::members($context)->where('id', $id)->update(['status' => $status]) !== 1) {
                throw new \RuntimeException('用户不存在');
            }
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    /** 调整用户余额并写入分类账户流水。 */
    public static function adjustUserMoney(
        TenantContext $context,
        array $params,
        int $adminId,
        string $idempotencyKey,
    ): bool
    {
        Db::startTrans();
        try {
            $action = (int)$params['action'];
            $memberId = (int)$params['user_id'];
            $amountCents = MemberBalanceService::moneyToCents(abs((float)$params['num']));
            $remark = (string)($params['remark'] ?? '');
            $idempotency = self::balanceAdjustmentIdempotency()->beginTenant(
                MemberTenantContext::tenantId($context),
                $adminId,
                'member.balance.adjust',
                IdempotencyKey::fromString($idempotencyKey),
                self::balanceAdjustmentRequestHash($memberId, $action, $amountCents, $remark),
                new DateTimeImmutable('+24 hours'),
            );
            if (!$idempotency->acquiredForExecution()) {
                if (!$idempotency->replayable()) {
                    throw new \RuntimeException('余额调账请求仍在处理中，请稍后查询流水');
                }
                Db::commit();
                return true;
            }

            $changeType = $action === AccountLogEnum::INC
                ? AccountLogEnum::USER_MONEY_INC_ADMIN
                : AccountLogEnum::USER_MONEY_DEC_ADMIN;
            MemberBalanceService::applyInTransaction(
                $context,
                $memberId,
                $changeType,
                $action,
                $amountCents,
                '',
                $remark,
                [],
                $adminId
            );
            self::balanceAdjustmentIdempotency()->completeTenant(
                $idempotency->id,
                200,
                ['success' => true],
            );
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    /** Peanut 旧版 signed amount API 的兼容入口。 */
    public static function adjustBalance(
        TenantContext $context,
        int $id,
        float $amount,
        string $remark,
        int $adminId,
        string $idempotencyKey,
    ): bool
    {
        if ($amount == 0.0) {
            self::setError('调整金额不能为 0');
            return false;
        }

        return self::adjustUserMoney($context, [
            'user_id' => $id,
            'action' => $amount > 0 ? AccountLogEnum::INC : AccountLogEnum::DEC,
            'num' => abs($amount),
            'remark' => $remark,
        ], $adminId, $idempotencyKey);
    }

    private static function balanceAdjustmentIdempotency(): PdoIdempotencyRepository
    {
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('数据库连接不可用');
        }
        return new PdoIdempotencyRepository($pdo);
    }

    private static function balanceAdjustmentRequestHash(
        int $memberId,
        int $action,
        int $amountCents,
        string $remark,
    ): string {
        return hash('sha256', json_encode([
            'member_id' => $memberId,
            'action' => $action,
            'amount_cents' => $amountCents,
            'remark' => $remark,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** 全量替换标签关联 */
    private static function syncTags(TenantContext $context, int $memberId, array $tagIds): void
    {
        if (MemberTenantRepository::members($context)->where('id', $memberId)->findOrEmpty()->isEmpty()) {
            throw new \RuntimeException('用户不存在');
        }
        $tagIds = PositiveIds::normalize(
            $tagIds,
            [PositiveIds::REJECT_INVALID],
            '包含不存在的会员标签',
        );
        if ($tagIds !== [] && MemberTenantRepository::tags($context)->whereIn('id', $tagIds)->count() !== count($tagIds)) {
            throw new \RuntimeException('包含不存在的会员标签');
        }
        MemberTenantRepository::relations($context)->where('member_id', $memberId)->delete();
        if (!empty($tagIds)) {
            $tenantId = MemberTenantContext::tenantId($context);
            $rows = array_map(fn($tid) => ['tenant_id' => $tenantId, 'member_id' => $memberId, 'tag_id' => (int)$tid], $tagIds);
            (new MemberTagRelation)->insertAll($rows);
        }
    }
}
