<?php
declare(strict_types=1);

namespace app\platform\service;

use app\common\service\audit\AuditContractHost;
use app\common\service\tenant\TenantEntryBindingResolver;
use app\platform\context\PlatformOperatorContext;
use PDO;
use PeanutAdmin\Kernel\Audit\AuditOutcome;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;

final readonly class TenantEntryBindingAdminService
{
    public function __construct(
        private PDO $pdo,
        private PlatformOperatorSessionService $sessions
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function lists(PlatformOperatorContext $context, ?int $tenantId = null): array
    {
        $this->sessions->assertAllowed($context, 'platform.tenant.read');
        $where = $tenantId === null ? '' : 'WHERE b.tenant_id = :tenant_id';
        $statement = $this->pdo->prepare(<<<SQL
SELECT b.id, b.tenant_id, t.code AS tenant_code, t.name AS tenant_name,
       b.host, b.client_key, b.status, b.created_at, b.updated_at
FROM pa_tenant_entry_binding b
JOIN pa_tenant t ON t.id = b.tenant_id
{$where}
ORDER BY b.host, b.client_key, b.id
SQL);
        $statement->execute($tenantId === null ? [] : ['tenant_id' => $tenantId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed> */
    public function enable(
        PlatformOperatorContext $context,
        int $tenantId,
        string $host,
        string $clientKey,
        string $changeReason
    ): array {
        $this->sessions->assertAllowed($context, 'platform.tenant.update');
        $host = TenantEntryBindingResolver::normalizeHost($host);
        $clientKey = trim($clientKey);
        if (!in_array($clientKey, [
            TenantEntryBindingResolver::ADMIN_CLIENT,
            TenantEntryBindingResolver::MEMBER_CLIENT,
        ], true)) {
            throw new \DomainException('TENANT_ENTRY_CLIENT_INVALID');
        }
        if ($tenantId < 1 || trim($changeReason) === '') {
            throw new \DomainException('TENANT_ENTRY_INPUT_INVALID');
        }

        return (new PdoTransactionManager($this->pdo))->run(function () use (
            $context,
            $tenantId,
            $host,
            $clientKey,
            $changeReason
        ): array {
            $tenant = $this->pdo->prepare(
                "SELECT id, code, name, status FROM pa_tenant WHERE id = :id FOR UPDATE"
            );
            $tenant->execute(['id' => $tenantId]);
            $tenantRow = $tenant->fetch(PDO::FETCH_ASSOC);
            if (!is_array($tenantRow) || $tenantRow['status'] !== 'active') {
                throw new \DomainException('TENANT_ENTRY_TENANT_UNAVAILABLE');
            }

            $existing = $this->pdo->prepare(<<<'SQL'
SELECT id, tenant_id, status FROM pa_tenant_entry_binding
WHERE host = :host AND client_key = :client_key
LIMIT 1
FOR UPDATE
SQL);
            $existing->execute([
                'host' => $host,
                'client_key' => $clientKey,
            ]);
            $existingRow = $existing->fetch(PDO::FETCH_ASSOC);
            if (!is_array($existingRow)) {
                $insert = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_tenant_entry_binding (tenant_id, host, client_key, status)
VALUES (:tenant_id, :host, :client_key, 'active')
SQL);
                $insert->execute([
                    'tenant_id' => $tenantId,
                    'host' => $host,
                    'client_key' => $clientKey,
                ]);
                $bindingId = (int)$this->pdo->lastInsertId();
            } else {
                $bindingId = (int)$existingRow['id'];
                if ($existingRow['status'] === 'active'
                    && (int)$existingRow['tenant_id'] !== $tenantId) {
                    throw new \DomainException('TENANT_ENTRY_BINDING_CONFLICT');
                }
                $update = $this->pdo->prepare(
                    "UPDATE pa_tenant_entry_binding SET tenant_id = :tenant_id, status = 'active' WHERE id = :id"
                );
                $update->execute(['tenant_id' => $tenantId, 'id' => $bindingId]);
            }

            $this->audit($context, 'tenant.entry-binding.enabled', $changeReason, [
                'tenant_id' => $tenantId,
                'binding_id' => $bindingId,
                'host' => $host,
                'client_key' => $clientKey,
            ]);
            return [
                'id' => $bindingId,
                'tenant_id' => $tenantId,
                'tenant_code' => $tenantRow['code'],
                'tenant_name' => $tenantRow['name'],
                'host' => $host,
                'client_key' => $clientKey,
                'status' => 'active',
            ];
        });
    }

    /** @return array{id:int,tenant_id:int,status:string} */
    public function disable(
        PlatformOperatorContext $context,
        int $bindingId,
        string $changeReason
    ): array {
        $this->sessions->assertAllowed($context, 'platform.tenant.update');
        if ($bindingId < 1 || trim($changeReason) === '') {
            throw new \DomainException('TENANT_ENTRY_INPUT_INVALID');
        }

        return (new PdoTransactionManager($this->pdo))->run(function () use (
            $context,
            $bindingId,
            $changeReason
        ): array {
            $statement = $this->pdo->prepare(
                'SELECT id, tenant_id, host, client_key, status FROM pa_tenant_entry_binding WHERE id = :id FOR UPDATE'
            );
            $statement->execute(['id' => $bindingId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                throw new \DomainException('TENANT_ENTRY_BINDING_NOT_FOUND');
            }
            if ($row['status'] !== 'disabled') {
                $update = $this->pdo->prepare(
                    "UPDATE pa_tenant_entry_binding SET status = 'disabled' WHERE id = :id"
                );
                $update->execute(['id' => $bindingId]);
            }
            $this->audit($context, 'tenant.entry-binding.disabled', $changeReason, [
                'tenant_id' => (int)$row['tenant_id'],
                'binding_id' => $bindingId,
                'host' => $row['host'],
                'client_key' => $row['client_key'],
            ]);
            return [
                'id' => $bindingId,
                'tenant_id' => (int)$row['tenant_id'],
                'status' => 'disabled',
            ];
        });
    }

    /** @param array<string,int|string> $metadata */
    private function audit(
        PlatformOperatorContext $context,
        string $eventType,
        string $reason,
        array $metadata
    ): void {
        AuditContractHost::fromPdo($this->pdo)->recordPlatform(
            $eventType,
            'platform.tenant.update',
            $context->core->requestId,
            $context->core->operatorId,
            $context->core->accountId,
            $metadata,
            AuditOutcome::Success,
            trim($reason),
        );
    }
}
