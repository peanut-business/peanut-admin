<?php
declare(strict_types=1);

namespace app\common\service\member;

use app\common\model\member\Member;
use app\common\model\member\MemberBalanceLog;
use app\common\model\member\MemberTag;
use app\common\model\member\MemberTagRelation;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class MemberTenantRepository
{
    public static function members(TenantContext|TenantSystemContext $context)
    {
        return Member::where('tenant_id', MemberTenantContext::tenantId($context));
    }

    public static function tags(TenantContext|TenantSystemContext $context)
    {
        return MemberTag::where('tenant_id', MemberTenantContext::tenantId($context));
    }

    public static function relations(TenantContext|TenantSystemContext $context)
    {
        return MemberTagRelation::where('tenant_id', MemberTenantContext::tenantId($context));
    }

    public static function balanceLogs(TenantContext|TenantSystemContext $context)
    {
        return MemberBalanceLog::where('tenant_id', MemberTenantContext::tenantId($context));
    }

    public static function createMember(TenantContext|TenantSystemContext $context, array $data): Member
    {
        unset($data['tenant_id']);
        return Member::create(['tenant_id' => MemberTenantContext::tenantId($context)] + $data);
    }

    public static function createTag(TenantContext $context, array $data): MemberTag
    {
        unset($data['tenant_id']);
        return MemberTag::create(['tenant_id' => MemberTenantContext::tenantId($context)] + $data);
    }

    public static function createBalanceLog(TenantContext $context, array $data): MemberBalanceLog
    {
        unset($data['tenant_id']);
        return MemberBalanceLog::create(['tenant_id' => MemberTenantContext::tenantId($context)] + $data);
    }
}
