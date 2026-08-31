<?php
declare(strict_types=1);

namespace app\common\execution;

use app\platform\context\PlatformOperatorContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** Immutable identity and operation established by a trusted execution boundary. */
final readonly class ExecutionContext
{
    public const TENANT_ADMIN = 'tenant-admin';
    public const MEMBER = 'member';
    public const SYSTEM = 'system';
    public const PLATFORM = 'platform';
    public const INSTANCE = 'instance';

    /**
     * @param array<string,int|string> $actor
     * @param array<string,mixed> $attributes Non-secret identity data established by the boundary.
     */
    private function __construct(
        public string $actorType,
        public string $operation,
        public string $requestId,
        public array $actor,
        public TenantContext|AuthenticatedMemberContext|TenantSystemContext|PlatformOperatorContext|InstanceExecutionScope $scope,
        public array $attributes = [],
    ) {
        if (trim($operation) === '' || trim($requestId) === '') {
            throw new \DomainException('EXECUTION_CONTEXT_UNTRUSTED');
        }
    }

    /** @param array<string,mixed> $principal */
    public static function tenantAdmin(
        TenantContext $context,
        string $operation,
        array $principal = [],
        bool $entryBound = false,
        array $attributes = [],
    ): self
    {
        if ($principal !== [] && (int)($principal['id'] ?? 0) !== $context->memberId) {
            throw new \DomainException('EXECUTION_ADMIN_PRINCIPAL_MISMATCH');
        }
        unset($principal['token']);
        return new self(
            self::TENANT_ADMIN,
            self::operation($operation),
            $context->requestId,
            [
                'tenant_id' => $context->tenantId,
                'account_id' => $context->accountId,
                'id' => $context->memberId,
            ],
            $context,
            $attributes + ['principal' => $principal, 'tenant_entry_bound' => $entryBound],
        );
    }

    public static function member(AuthenticatedMemberContext $context, string $operation): self
    {
        return new self(
            self::MEMBER,
            self::operation($operation),
            $context->requestId,
            [
                'tenant_id' => $context->tenantId,
                'id' => $context->memberId,
            ],
            $context,
        );
    }

    public static function system(TenantSystemContext $context, array $attributes = []): self
    {
        return new self(
            self::SYSTEM,
            self::operation($context->operation),
            $context->operationId,
            [
                'tenant_id' => $context->tenantId,
                'actor_key' => $context->actorKey,
            ],
            $context,
            $attributes,
        );
    }

    public static function platform(PlatformOperatorContext $context, string $operation): self
    {
        return new self(
            self::PLATFORM,
            self::operation($operation),
            $context->core->requestId,
            [
                'account_id' => $context->core->accountId,
                'id' => $context->core->operatorId,
            ],
            $context,
        );
    }

    public static function instance(string $operation, string $requestId): self
    {
        $host = gethostname();
        $host = is_string($host) && trim($host) !== '' ? $host : 'unknown-host';
        $scope = new InstanceExecutionScope('console', $host);
        return new self(
            self::INSTANCE,
            self::operation($operation),
            trim($requestId),
            ['actor_key' => $scope->actorKey],
            $scope,
        );
    }

    public function tenantId(): ?int
    {
        return match (true) {
            $this->scope instanceof TenantContext,
            $this->scope instanceof AuthenticatedMemberContext,
            $this->scope instanceof TenantSystemContext => $this->scope->tenantId,
            default => null,
        };
    }

    private static function operation(string $operation): string
    {
        $operation = trim($operation);
        if ($operation === '') {
            throw new \DomainException('EXECUTION_OPERATION_REQUIRED');
        }
        return $operation;
    }
}
