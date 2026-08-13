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

/** Restores an async context from the signed envelope and current database authority. */
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
  a.id AS admin_id,
  a.root,
  tm.authorization_revision,
  EXISTS (
    SELECT 1
    FROM pa_admin_role ar
    JOIN pa_system_role r
      ON r.tenant_id = ar.tenant_id
     AND r.id = ar.role_id
     AND r.is_disable = 0
     AND r.delete_time IS NULL
    JOIN pa_system_role_menu rm
      ON rm.tenant_id = ar.tenant_id
     AND rm.role_id = ar.role_id
    JOIN pa_system_menu menu
      ON menu.id = rm.menu_id
     AND menu.is_disable = 0
     AND LOWER(menu.perms) = 'log/export'
    WHERE ar.tenant_id = m.tenant_id
      AND ar.admin_id = a.id
  ) AS permission_owned
FROM pa_legacy_admin_tenant_map m
JOIN pa_admin a
  ON a.tenant_id = m.tenant_id
 AND a.id = m.legacy_admin_id
 AND a.disable = 0
 AND a.delete_time IS NULL
JOIN pa_tenant t
  ON t.id = m.tenant_id
 AND t.status = 'active'
JOIN pa_account account
  ON account.id = m.account_id
 AND account.status = 'active'
JOIN pa_tenant_member tm
  ON tm.tenant_id = m.tenant_id
 AND tm.id = m.tenant_member_id
 AND tm.account_id = m.account_id
 AND tm.status = 'active'
WHERE m.tenant_id = :tenant_id
  AND m.account_id = :account_id
  AND m.tenant_member_id = :member_id
LIMIT 1
SQL);
        $statement->execute([
            'tenant_id' => $envelope->tenantId,
            'account_id' => $envelope->accountId,
            'member_id' => $envelope->memberId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)
            || ((int)$row['root'] !== 1 && (int)$row['permission_owned'] !== 1)
            || (int)$row['authorization_revision'] < 1
        ) {
            throw $this->denied();
        }

        $session = new ValidatedTenantSession(
            (int)$row['admin_id'],
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
