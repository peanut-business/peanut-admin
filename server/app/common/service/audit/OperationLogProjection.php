<?php
declare(strict_types=1);

namespace app\common\service\audit;

use app\common\contract\audit\AuditEvent;
use app\common\execution\CurrentExecutionContext;
use PDO;

final class OperationLogProjection
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function append(AuditEvent $event): void
    {
        if ($event->projection !== AuditEvent::OPERATION_LOG || $event->tenantId === null) {
            throw new \InvalidArgumentException('AUDIT_OPERATION_LOG_EVENT_INVALID');
        }
        $current = app(CurrentExecutionContext::class);
        if ($current->tenantId() !== $event->tenantId
            || !hash_equals($current->requestId(), $event->trace->requestId)) {
            throw new \DomainException('AUDIT_OPERATION_LOG_CONTEXT_MISMATCH');
        }
        $metadata = RedactionPolicy::sanitize($event->metadata);
        $params = $metadata['params'] ?? [];
        OperationLogTenantRepository::createForTenant(
            $this->pdo,
            $event->tenantId,
            $event->trace->requestId,
            [
                'admin_id' => (int)($metadata['admin_id'] ?? 0),
                'username' => (string)($metadata['username'] ?? ''),
                'ip' => (string)($metadata['ip'] ?? $event->trace->ipAddress ?? ''),
                'uri' => strtolower(trim((string)($metadata['uri'] ?? ''), '/')),
                'method' => strtoupper((string)($metadata['method'] ?? '')),
                'params' => RedactionPolicy::encode($params),
            ],
        );
    }
}
