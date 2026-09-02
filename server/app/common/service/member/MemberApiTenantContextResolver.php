<?php
declare(strict_types=1);

namespace app\common\service\member;

use app\Modules\Official\Member\Contracts\MemberSubjectLookup;
use PDO;
use PeanutAdmin\Kernel\Context\AuthenticatedMemberContext;

/** Restores application-member identity from a verified JWT subject and authoritative ownership. */
final class MemberApiTenantContextResolver
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly MemberSubjectLookup $members,
    ) {
    }

    public function resolve(int $memberId, string $token, string $requestId): AuthenticatedMemberContext
    {
        if ($memberId < 1 || $token === '' || $requestId === '') {
            throw new \DomainException('MEMBER_TENANT_CONTEXT_UNAVAILABLE');
        }

        $tenantId = $this->members->tenantId($memberId);
        if ($tenantId === null) {
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
