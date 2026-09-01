<?php
declare(strict_types=1);

namespace app\Modules\Official\Task\Model;

use app\common\model\TenantOwnedModel;
use think\facade\Db;
use think\model\concern\SoftDelete;

/**
 * 定时任务模型
 */
class Crontab extends TenantOwnedModel
{
    use SoftDelete;
    protected $name = 'crontab';
    protected $deleteTime = 'delete_time';

    /** @return null|array{id:int,account_id:int,authorization_revision:int} */
    public static function activeTenantOwner(int $tenantId, ?int $memberId, ?int $accountId): ?array
    {
        $query = Db::name('tenant_member')->alias('member')
            ->join('account account', "account.id = member.account_id AND account.status = 'active'")
            ->join('member_role membership', 'membership.tenant_id = member.tenant_id AND membership.tenant_member_id = member.id')
            ->join('role role', "role.tenant_id = membership.tenant_id AND role.id = membership.role_id AND role.`key` = 'core.tenant-owner' AND role.is_builtin = 1 AND role.status = 'active'")
            ->where('member.tenant_id', $tenantId)
            ->where('member.status', 'active');
        if ($memberId !== null && $accountId !== null) {
            $query->where('member.id', $memberId)->where('member.account_id', $accountId);
        }
        $owner = $query->field('member.id,member.account_id,member.authorization_revision')
            ->order('member.id')
            ->find();

        return is_array($owner) ? [
            'id' => (int)$owner['id'],
            'account_id' => (int)$owner['account_id'],
            'authorization_revision' => (int)$owner['authorization_revision'],
        ] : null;
    }
}
