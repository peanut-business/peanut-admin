<?php
declare(strict_types=1);

namespace app\modules\official\oauth\contracts;

use app\modules\official\oauth\contracts\dto\OAuthAttemptRecord;
use app\modules\official\oauth\contracts\dto\OAuthCompletionRecord;
use app\modules\official\oauth\contracts\dto\OAuthIdentityRecord;
use app\modules\official\oauth\contracts\dto\OAuthPrincipalRecord;
use PeanutAdmin\Kernel\Context\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** Framework-neutral persistence boundary for OAuth-owned records. */
interface OAuthPersistence
{
    public function createAttempt(TenantContext|TenantSystemContext $context, array $data): void;

    public function attemptForUpdate(TenantContext|TenantSystemContext $context, string $stateHash): ?OAuthAttemptRecord;

    public function markAttemptUsed(TenantContext|TenantSystemContext $context, int $id, int $usedAt): void;

    public function createCompletion(TenantContext|TenantSystemContext $context, array $data): void;

    public function completionForUpdate(TenantContext|TenantSystemContext $context, string $tokenHash): ?OAuthCompletionRecord;

    public function markCompletionUsed(TenantContext|TenantSystemContext $context, int $id, int $usedAt): void;

    public function identityBySubjectForUpdate(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $provider,
        string $clientKey,
        string $subject,
    ): ?OAuthIdentityRecord;

    public function identityByMemberForUpdate(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $provider,
        string $clientKey,
        int $memberId,
    ): ?OAuthIdentityRecord;

    public function principalByUnionForUpdate(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $provider,
        string $unionScope,
        string $unionId,
    ): ?OAuthPrincipalRecord;

    public function createPrincipal(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        array $data,
    ): OAuthPrincipalRecord;

    public function createIdentity(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        array $data,
    ): void;

    public function updateIdentityPrincipal(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        int $identityId,
        int $principalId,
    ): void;

    public function wechatSubjectForMember(
        AuthenticatedMemberContext|TenantContext $context,
        int $memberId,
        int $terminal,
    ): string;
}
