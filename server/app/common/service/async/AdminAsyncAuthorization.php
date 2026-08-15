<?php
declare(strict_types=1);

namespace app\common\service\async;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\ImportExport\Application\ImportExportService;
use PeanutAdmin\Kernel\Async\AsyncAuthorizationRevalidator;
use PeanutAdmin\Kernel\Async\VerifiedJobEnvelope;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Context\AuthorizationDecision;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;

/** Revalidates queued Admin work against current native member RBAC. */
final readonly class AdminAsyncAuthorization implements AsyncAuthorizationRevalidator
{
    public function __construct(private PDO $pdo)
    {
    }

    public function reauthorize(VerifiedJobEnvelope $envelope): AuthorizedOperationContext
    {
        if (!hash_equals(ImportExportService::RESOURCE_KEY, $envelope->resourceKey)
            || !hash_equals('create', $envelope->operation)
            || $envelope->requestedTargets !== []
        ) {
            throw $this->denied();
        }

        $statement = $this->pdo->prepare(<<<'SQL'
SELECT
  tm.authorization_revision,
  EXISTS (
    SELECT 1
    FROM pa_member_role mr
    JOIN pa_role r
      ON r.tenant_id = mr.tenant_id
     AND r.id = mr.role_id
     AND r.status = 'active'
    WHERE mr.tenant_id = tm.tenant_id
      AND mr.tenant_member_id = tm.id
      AND r.`key` = 'core.tenant-owner'
      AND r.is_builtin = 1
  ) AS is_owner,
  EXISTS (
    SELECT 1
    FROM pa_member_role mr
    JOIN pa_role r
      ON r.tenant_id = mr.tenant_id
     AND r.id = mr.role_id
     AND r.status = 'active'
    JOIN pa_role_permission rp
      ON rp.tenant_id = r.tenant_id
     AND rp.role_id = r.id
    JOIN pa_permission p
      ON p.id = rp.permission_id
     AND p.status = 'active'
     AND p.`key` = 'log/export'
    WHERE mr.tenant_id = tm.tenant_id
      AND mr.tenant_member_id = tm.id
  ) AS permission_owned
FROM pa_tenant_member tm
JOIN pa_tenant t ON t.id = tm.tenant_id AND t.status = 'active'
JOIN pa_account a ON a.id = tm.account_id AND a.status = 'active'
WHERE tm.tenant_id = :tenant_id
  AND tm.account_id = :account_id
  AND tm.id = :member_id
  AND tm.status = 'active'
LIMIT 1
SQL);
        $statement->execute([
            'tenant_id' => $envelope->tenantId,
            'account_id' => $envelope->accountId,
            'member_id' => $envelope->memberId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)
            || ((int)$row['is_owner'] !== 1 && (int)$row['permission_owned'] !== 1)
            || (int)$row['authorization_revision'] < 1
        ) {
            throw $this->denied();
        }

        $session = new ValidatedTenantSession(
            $envelope->memberId,
            'async-' . hash('sha256', $envelope->operationId),
            $envelope->tenantId,
            $envelope->accountId,
            $envelope->memberId,
            'admin-async-worker',
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
            (int)$row['authorization_revision'],
        );
        $context = TenantContext::fromValidatedSession($session, $envelope->traceId);

        return AuthorizedOperationContext::fromDecision(AuthorizationDecision::allow(
            $context,
            $envelope->resourceKey,
            $envelope->operation,
            $envelope->requestedTargets,
            hash('sha256', implode("\0", [
                (string)$context->tenantId,
                (string)$context->memberId,
                (string)$context->authorizationRevision,
                'log/export',
                $envelope->operationId,
            ])),
        ));
    }

    private function denied(): AuthException
    {
        return new AuthException('CONTEXT_SYSTEM_ACTOR_INVALID', 403);
    }
}
