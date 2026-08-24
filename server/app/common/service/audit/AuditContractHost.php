<?php
declare(strict_types=1);

namespace app\common\service\audit;

use app\common\contract\audit\AuditActor;
use app\common\contract\audit\AuditEvent;
use app\common\contract\audit\AuditResource;
use PDO;
use PeanutAdmin\Kernel\Audit\AuditOutcome;
use PeanutAdmin\Kernel\Audit\AuditRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\facade\Db;

final class AuditContractHost implements AuditRepository
{
    private OperationLogProjection $operationLogs;

    public function __construct(private readonly PDO $pdo)
    {
        $this->operationLogs = new OperationLogProjection();
    }

    public static function fromPdo(PDO $pdo): self
    {
        return new self($pdo);
    }

    public static function production(): self
    {
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('AUDIT_DATABASE_CONNECTION_UNAVAILABLE');
        }
        return new self($pdo);
    }

    public function record(AuditEvent $event): void
    {
        if ($event->projection === AuditEvent::OPERATION_LOG) {
            $this->operationLogs->append($event);
            return;
        }
        if ($event->projection === AuditEvent::PLATFORM) {
            $this->appendPlatformEvent($event);
            return;
        }
        $this->appendTenantEvent($event);
    }

    public function recordOperationLog(
        TenantContext $context,
        int $adminId,
        string $username,
        string $ip,
        string $uri,
        string $method,
        mixed $params,
    ): void {
        $this->record(AuditEvent::operationLog($context, $adminId, $username, $ip, $uri, $method, $params));
    }

    public function recordPlatform(
        string $eventType,
        string $operation,
        string $requestId,
        ?int $operatorId,
        ?int $accountId,
        array $metadata = [],
        AuditOutcome $outcome = AuditOutcome::Success,
        ?string $reasonCode = null,
        ?AuditResource $resource = null,
    ): void {
        $this->record(AuditEvent::platform(
            $eventType,
            $operation,
            $requestId,
            $operatorId,
            $accountId,
            $metadata,
            $outcome,
            $reasonCode,
            $resource,
        ));
    }

    public function recordTenantSystem(
        int $tenantId,
        string $eventType,
        string $operation,
        string $requestId,
        array $metadata = [],
        AuditOutcome $outcome = AuditOutcome::Success,
        ?string $reasonCode = null,
        ?AuditResource $resource = null,
    ): void {
        $this->record(AuditEvent::tenantSystem(
            $tenantId,
            $eventType,
            $operation,
            $requestId,
            $metadata,
            $outcome,
            $reasonCode,
            $resource,
        ));
    }

    public function appendPlatform(
        string $eventType,
        string $action,
        string $requestId,
        ?int $operatorId,
        ?int $accountId,
        array $metadata = [],
    ): void {
        $reasonCode = isset($metadata['reason']) ? trim((string)$metadata['reason']) : null;
        unset($metadata['reason']);
        $this->recordPlatform($eventType, $action, $requestId, $operatorId, $accountId, $metadata, AuditOutcome::Success, $reasonCode);
    }

    public function appendTenantSystem(
        int $tenantId,
        string $eventType,
        string $action,
        string $requestId,
        array $metadata = [],
    ): void {
        $reasonCode = isset($metadata['reason']) ? trim((string)$metadata['reason']) : null;
        unset($metadata['reason']);
        $this->recordTenantSystem($tenantId, $eventType, $action, $requestId, $metadata, AuditOutcome::Success, $reasonCode);
    }

    public function appendTenantMember(
        TenantContext $context,
        string $eventType,
        string $action,
        ?string $targetResourceType = null,
        ?string $targetResourceId = null,
        ?string $boundaryTargetType = null,
        ?string $boundaryTargetId = null,
        int $targetCount = 0,
        ?string $targetSetDigest = null,
        array $metadata = [],
    ): void {
        $this->record(AuditEvent::tenantMember(
            $context,
            $eventType,
            $action,
            $targetResourceType === null || $targetResourceId === null
                ? null
                : new AuditResource($targetResourceType, $targetResourceId),
            $boundaryTargetType === null || $boundaryTargetId === null
                ? null
                : new AuditResource($boundaryTargetType, $boundaryTargetId),
            $targetCount,
            $targetSetDigest,
            $metadata,
        ));
    }

    public function appendTenantPlatformOperator(
        int $tenantId,
        int $operatorId,
        int $accountId,
        string $eventType,
        string $action,
        string $requestId,
        array $metadata = [],
    ): void {
        $this->record(AuditEvent::tenantPlatformOperator(
            $tenantId,
            $operatorId,
            $accountId,
            $eventType,
            $action,
            $requestId,
            $metadata,
        ));
    }

    private function appendPlatformEvent(AuditEvent $event): void
    {
        $actor = $event->actor;
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_platform_audit_event (
    event_type, action, outcome, reason_code, operator_id, account_id,
    target_type, target_id, request_id, operation_id, ip_address,
    user_agent_hash, before_json, after_json, metadata_json, occurred_at
) VALUES (
    :event_type, :action, :outcome, :reason_code, :operator_id, :account_id,
    :target_type, :target_id, :request_id, :operation_id, :ip_address,
    :user_agent_hash, :before_json, :after_json, :metadata_json, :occurred_at
)
SQL);
        $statement->execute([
            'event_type' => $event->eventType,
            'action' => $event->operation,
            'outcome' => $event->outcome->value,
            'reason_code' => $event->reasonCode,
            'operator_id' => $actor->platformOperatorId,
            'account_id' => $actor->accountId,
            'target_type' => $event->resource?->type,
            'target_id' => $event->resource?->id,
            'request_id' => $event->trace->requestId,
            'operation_id' => $event->trace->operationId,
            'ip_address' => $event->trace->ipAddress,
            'user_agent_hash' => $event->trace->userAgentHash,
            'before_json' => RedactionPolicy::nullableJson($event->before),
            'after_json' => RedactionPolicy::nullableJson($event->after),
            'metadata_json' => RedactionPolicy::nullableJson($event->metadata),
            'occurred_at' => $this->now(),
        ]);
    }

    private function appendTenantEvent(AuditEvent $event): void
    {
        if ($event->tenantId === null) {
            throw new \InvalidArgumentException('AUDIT_TENANT_REQUIRED');
        }
        $actor = $event->actor;
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_tenant_audit_event (
    tenant_id, event_type, action, outcome, reason_code,
    actor_tenant_id, actor_tenant_member_id, actor_account_id, actor_platform_operator_id, actor_type,
    target_resource_type, target_resource_id, boundary_target_type, boundary_target_id,
    target_count, target_set_digest, authorization_basis_json,
    request_id, operation_id, ip_address, user_agent_hash,
    before_json, after_json, metadata_json, occurred_at
) VALUES (
    :tenant_id, :event_type, :action, :outcome, :reason_code,
    :actor_tenant_id, :actor_tenant_member_id, :actor_account_id, :actor_platform_operator_id, :actor_type,
    :target_resource_type, :target_resource_id, :boundary_target_type, :boundary_target_id,
    :target_count, :target_set_digest, :authorization_basis_json,
    :request_id, :operation_id, :ip_address, :user_agent_hash,
    :before_json, :after_json, :metadata_json, :occurred_at
)
SQL);
        $statement->execute([
            'tenant_id' => $event->tenantId,
            'event_type' => $event->eventType,
            'action' => $event->operation,
            'outcome' => $event->outcome->value,
            'reason_code' => $event->reasonCode,
            'actor_tenant_id' => $actor->type === AuditActor::TENANT_MEMBER || $actor->type === AuditActor::TENANT_SYSTEM
                ? $actor->tenantId
                : null,
            'actor_tenant_member_id' => $actor->tenantMemberId,
            'actor_account_id' => $actor->accountId,
            'actor_platform_operator_id' => $actor->platformOperatorId,
            'actor_type' => $actor->type,
            'target_resource_type' => $event->resource?->type,
            'target_resource_id' => $event->resource?->id,
            'boundary_target_type' => $event->boundaryTarget?->type,
            'boundary_target_id' => $event->boundaryTarget?->id,
            'target_count' => $event->targetCount,
            'target_set_digest' => $event->targetSetDigest,
            'authorization_basis_json' => RedactionPolicy::nullableJson($event->authorizationBasis),
            'request_id' => $event->trace->requestId,
            'operation_id' => $event->trace->operationId,
            'ip_address' => $event->trace->ipAddress,
            'user_agent_hash' => $event->trace->userAgentHash,
            'before_json' => RedactionPolicy::nullableJson($event->before),
            'after_json' => RedactionPolicy::nullableJson($event->after),
            'metadata_json' => RedactionPolicy::nullableJson($event->metadata),
            'occurred_at' => $this->now(),
        ]);
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');
    }
}
