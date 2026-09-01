<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Infrastructure\Persistence;

use app\Modules\Official\Member\Model\Member;
use app\Modules\Official\Member\Model\MemberBalanceLog;
use app\Modules\Official\Member\Model\MemberTag;
use app\Modules\Official\Member\Model\MemberTagRelation;
use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use app\common\persistence\ConvertsModelPage;

final class MemberTenantRepository
{
    use ConvertsModelPage;

    public static function members(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context)
    {
        self::tenantId($context);
        return Member::where([]);
    }

    public static function tags(TenantContext|TenantSystemContext $context)
    {
        self::tenantId($context);
        return MemberTag::where([]);
    }

    public static function relations(TenantContext|TenantSystemContext $context)
    {
        self::tenantId($context);
        return MemberTagRelation::where([]);
    }

    public static function balanceLogs(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context)
    {
        self::tenantId($context);
        return MemberBalanceLog::where([]);
    }

    public static function createMember(TenantContext|TenantSystemContext $context, array $data): Member
    {
        self::tenantId($context);
        unset($data['tenant_id']);
        return Member::create($data);
    }

    public static function createTag(TenantContext $context, array $data): MemberTag
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
        unset($data['tenant_id']);
        return MemberBalanceLog::create($data);
    }

    public static function nextMemberSn(TenantContext|TenantSystemContext $context): string
    {
        return Member::generateSn($context);
    }

    public static function nextBalanceLogSn(TenantContext|TenantSystemContext $context): string
    {
        return MemberBalanceLog::generateSn($context);
    }

    private static function tenantId(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
    ): int {
        return $context->tenantId;
    }
}
