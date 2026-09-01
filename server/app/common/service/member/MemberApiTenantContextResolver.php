<?php
declare(strict_types=1);

namespace app\common\service\member;

use app\Modules\Official\Member\Model\Member;
use app\common\tenancy\PlatformTenantDataGateway;
use PDO;

/** Restores application-member identity from a verified JWT subject and authoritative ownership. */
final class MemberApiTenantContextResolver
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PlatformTenantDataGateway $tenantData,
    ) {
    }

    public function resolve(int $memberId, string $token, string $requestId): AuthenticatedMemberContext
    {
        if ($memberId < 1 || $token === '' || $requestId === '') {
            throw new \DomainException('MEMBER_TENANT_CONTEXT_UNAVAILABLE');
        }

        $member = $this->tenantData
            ->query(Member::class, 'api.member-auth', 'resolve-tenant-context')
            ->where('id', $memberId)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->field(['id', 'tenant_id'])
            ->find();
        if ($member === null) {
            throw new \DomainException('MEMBER_TENANT_CONTEXT_UNAVAILABLE');
        }
        $row = $member->toArray();
        $tenantId = (int)($row['tenant_id'] ?? 0);
        if ($tenantId < 1 || (int)($row['id'] ?? 0) !== $memberId) {
            throw new \DomainException('MEMBER_TENANT_CONTEXT_UNAVAILABLE');
        }
        $tenant = $this->pdo->prepare(
            "SELECT 1 FROM pa_tenant WHERE id = :tenant_id AND status = 'active' LIMIT 1"
        );
        $tenant->execute(['tenant_id' => $tenantId]);
        if ($tenant->fetchColumn() === false) {
            throw new \DomainException('MEMBER_TENANT_CONTEXT_UNAVAILABLE');
        }

        return new AuthenticatedMemberContext(
            $tenantId,
            $memberId,
            hash('sha256', $token),
            $requestId,
        );
    }
}
