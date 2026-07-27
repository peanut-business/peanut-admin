<?php
declare(strict_types=1);

namespace app\adminapi\logic\member;

use app\common\logic\BaseLogic;
use app\common\model\member\Member;
use app\common\model\member\MemberBalanceLog;
use app\common\model\member\MemberTag;
use app\common\model\member\MemberTagRelation;
use think\facade\Db;

class MemberLogic extends BaseLogic
{
    public static function lists(array $params): array
    {
        $query = Member::with(['tags']);

        if (!empty($params['keyword'])) {
            $kw = $params['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->whereLike('nickname', "%{$kw}%")
                  ->whereOr('mobile', $kw)
                  ->whereOr('sn', $kw);
            });
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int)$params['status']);
        }

        return $query->order('id', 'desc')->select()->toArray();
    }

    public static function detail(int $id): array
    {
        $m = Member::with(['tags'])->findOrEmpty($id);
        if ($m->isEmpty()) return [];
        $data           = $m->toArray();
        $data['tag_ids'] = array_column($data['tags'] ?? [], 'id');
        return $data;
    }

    public static function add(array $params): bool
    {
        Db::startTrans();
        try {
            $member = Member::create([
                'sn'       => Member::generateSn(),
                'nickname' => $params['nickname'],
                'avatar'   => $params['avatar']   ?? '',
                'mobile'   => $params['mobile']   ?? '',
                'email'    => $params['email']    ?? '',
                'sex'      => (int)($params['sex'] ?? 0),
                'birthday' => $params['birthday']  ?? null,
                'status'   => (int)($params['status'] ?? 1),
            ]);
            if (!empty($params['tag_ids'])) {
                self::syncTags($member->id, $params['tag_ids']);
            }
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
        Db::startTrans();
        try {
            $data = ['id' => $params['id']];
            foreach (['nickname', 'avatar', 'mobile', 'email', 'birthday'] as $f) {
                if (isset($params[$f])) $data[$f] = $params[$f];
            }
            foreach (['sex', 'status'] as $f) {
                if (isset($params[$f])) $data[$f] = (int)$params[$f];
            }
            Member::update($data);
            if (array_key_exists('tag_ids', $params)) {
                self::syncTags((int)$params['id'], (array)($params['tag_ids'] ?? []));
            }
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
        Member::update(['id' => $id, 'status' => $status]);
        return true;
    }

    /**
     * 手动调整余额（正数增加，负数减少）
     */
    public static function adjustBalance(int $id, float $amount, string $remark, int $adminId): bool
    {
        if ($amount == 0) { self::setError('调整金额不能为 0'); return false; }
        Db::startTrans();
        try {
            /** @var Member $member */
            $member = Member::lockForUpdate()->findOrEmpty($id);
            if ($member->isEmpty()) { self::setError('会员不存在'); Db::rollback(); return false; }

            $after = round((float)$member->balance + $amount, 2);
            if ($after < 0) { self::setError('余额不足，调整后余额不能为负数'); Db::rollback(); return false; }

            Member::update(['id' => $id, 'balance' => $after]);
            MemberBalanceLog::create([
                'member_id'     => $id,
                'change_amount' => $amount,
                'after_amount'  => $after,
                'source_type'   => 0,
                'remark'        => $remark,
                'admin_id'      => $adminId,
            ]);
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    /** 全量替换标签关联 */
    private static function syncTags(int $memberId, array $tagIds): void
    {
        MemberTagRelation::where('member_id', $memberId)->delete();
        if (!empty($tagIds)) {
            $rows = array_map(fn($tid) => ['member_id' => $memberId, 'tag_id' => (int)$tid], $tagIds);
            (new MemberTagRelation)->insertAll($rows);
        }
    }
}
