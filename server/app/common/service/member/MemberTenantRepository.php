<?php
declare(strict_types=1);

namespace app\common\service\member;

use app\Modules\Official\Member\Model\Member;
use app\Modules\Official\Member\Model\MemberBalanceLog;
use app\Modules\Official\Member\Model\MemberTag;
use app\Modules\Official\Member\Model\MemberTagRelation;
use app\common\service\finance\FinanceTenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class MemberTenantRepository
{
    public static function members(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context)
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

    public static function balanceLogs(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context)
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

    /** @param list<int> $tagIds */
    public static function createTagRelations(TenantContext $context, int $memberId, array $tagIds): void
    {
        $tenantId = MemberTenantContext::tenantId($context);
        (new MemberTagRelation())->insertAll(array_map(
            static fn(int $tagId): array => [
                'tenant_id' => $tenantId,
                'member_id' => $memberId,
                'tag_id' => $tagId,
            ],
            $tagIds,
        ));
    }

    public static function createBalanceLog(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        array $data
    ): MemberBalanceLog
    {
        if ($context instanceof TenantSystemContext) {
            FinanceTenantContext::tenantId($context);
        }
        unset($data['tenant_id']);
        return MemberBalanceLog::create(['tenant_id' => MemberTenantContext::tenantId($context)] + $data);
    }
}
