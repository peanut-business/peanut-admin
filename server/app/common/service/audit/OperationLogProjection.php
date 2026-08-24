<?php
declare(strict_types=1);

namespace app\common\service\audit;

use app\common\contract\audit\AuditEvent;

final class OperationLogProjection
{
    public function append(AuditEvent $event): void
    {
        if ($event->projection !== AuditEvent::OPERATION_LOG || $event->tenantId === null) {
            throw new \InvalidArgumentException('AUDIT_OPERATION_LOG_EVENT_INVALID');
        }
        $metadata = RedactionPolicy::sanitize($event->metadata);
        $params = $metadata['params'] ?? [];
        OperationLogTenantRepository::createForTenant($event->tenantId, $event->trace->requestId, [
            'admin_id' => (int)($metadata['admin_id'] ?? 0),
            'username' => (string)($metadata['username'] ?? ''),
            'ip' => (string)($metadata['ip'] ?? $event->trace->ipAddress ?? ''),
            'uri' => strtolower(trim((string)($metadata['uri'] ?? ''), '/')),
            'method' => strtoupper((string)($metadata['method'] ?? '')),
            'params' => RedactionPolicy::encode($params),
        ]);
    }
}
