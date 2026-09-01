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
use app\common\execution\ExecutionContextAccess;
use app\common\execution\AdminExecutionContext;
use app\common\execution\ConsumerExecutionContext;
use app\common\execution\SystemExecutionContext;

final class MemberTenantRepository
{
    public static function members(AuthenticatedMemberContext|TenantContext|TenantSystemContext|null $context = null)
    {
        self::tenantId($context);
        return Member::where([]);
    }

    public static function tags(TenantContext|TenantSystemContext|null $context = null)
    {
        self::tenantId($context);
        return MemberTag::where([]);
    }

    public static function relations(TenantContext|TenantSystemContext|null $context = null)
    {
        self::tenantId($context);
        return MemberTagRelation::where([]);
    }

    public static function balanceLogs(AuthenticatedMemberContext|TenantContext|TenantSystemContext|null $context = null)
    {
        self::tenantId($context);
        return MemberBalanceLog::where([]);
    }

    public static function createMember(TenantContext|TenantSystemContext|null $context, array $data): Member
    {
        self::tenantId($context);
        unset($data['tenant_id']);
        return Member::create($data);
    }

    public static function createTag(TenantContext|null $context, array $data): MemberTag
    {
        self::tenantId($context);
        unset($data['tenant_id']);
        return MemberTag::create($data);
    }

    /** @param list<int> $tagIds */
    public static function createTagRelations(TenantContext $context, int $memberId, array $tagIds): void
    {
        self::tenantId($context);
        (new MemberTagRelation())->saveAll(array_map(
            static fn(int $tagId): array => [
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
        return MemberBalanceLog::create($data);
    }

    private static function tenantId(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext|null $context,
    ): int {
        if ($context === null) {
            $execution = ExecutionContextAccess::current();
            $context = match (true) {
                $execution instanceof AdminExecutionContext => $execution->tenant,
                $execution instanceof ConsumerExecutionContext => $execution->member ?? $execution->publicTenant,
                $execution instanceof SystemExecutionContext => $execution->system,
                default => null,
            };
        }
        return MemberTenantContext::tenantId($context);
    }
}
