<?php
declare(strict_types=1);

namespace app\common\execution;

use app\platform\context\PlatformOperatorContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** Typed, fail-closed access to the context established by the current boundary. */
final readonly class CurrentExecutionContext
{
    public function __construct(private ExecutionContextStore $store)
    {
    }

    public function get(): ExecutionContext
    {
        return $this->store->require();
    }

    public function current(): ?ExecutionContext
    {
        return $this->store->current();
    }

    public function tenantId(): int
    {
        return $this->get()->tenantId()
            ?? throw new \DomainException('EXECUTION_TENANT_CONTEXT_REQUIRED');
    }

    public function tenantAdmin(): TenantContext
    {
        $context = $this->get();
        if ($context->actorType !== ExecutionContext::TENANT_ADMIN
            || !$context->scope instanceof TenantContext) {
            throw new \DomainException('EXECUTION_TENANT_ADMIN_CONTEXT_REQUIRED');
        }
        return $context->scope;
    }

    public function member(): AuthenticatedMemberContext
    {
        $context = $this->get();
        if ($context->actorType !== ExecutionContext::MEMBER
            || !$context->scope instanceof AuthenticatedMemberContext) {
            throw new \DomainException('EXECUTION_MEMBER_CONTEXT_REQUIRED');
        }
        return $context->scope;
    }

    public function system(): TenantSystemContext
    {
        $context = $this->get();
        if ($context->actorType !== ExecutionContext::SYSTEM
            || !$context->scope instanceof TenantSystemContext) {
            throw new \DomainException('EXECUTION_SYSTEM_CONTEXT_REQUIRED');
        }
        return $context->scope;
    }

    public function platform(): PlatformOperatorContext
    {
        $context = $this->get();
        if ($context->actorType !== ExecutionContext::PLATFORM
            || !$context->scope instanceof PlatformOperatorContext) {
            throw new \DomainException('EXECUTION_PLATFORM_CONTEXT_REQUIRED');
        }
        return $context->scope;
    }

    public function instance(): InstanceExecutionScope
    {
        $context = $this->get();
        if ($context->actorType !== ExecutionContext::INSTANCE
            || !$context->scope instanceof InstanceExecutionScope) {
            throw new \DomainException('EXECUTION_INSTANCE_CONTEXT_REQUIRED');
        }
        return $context->scope;
    }

    /** @return array<string,int|string> */
    public function actor(): array
    {
        return $this->get()->actor;
    }

    /** @return array<string,mixed> */
    public function tenantAdminPrincipal(): array
    {
        $context = $this->get();
        if ($context->actorType !== ExecutionContext::TENANT_ADMIN) {
            throw new \DomainException('EXECUTION_TENANT_ADMIN_CONTEXT_REQUIRED');
        }
        $principal = $context->attributes['principal'] ?? null;
        if (!is_array($principal) || (int)($principal['id'] ?? 0) !== $context->scope->memberId) {
            throw new \DomainException('EXECUTION_ADMIN_PRINCIPAL_REQUIRED');
        }
        return $principal;
    }

    public function tenantEntryBound(): bool
    {
        $this->tenantAdmin();
        return ($this->get()->attributes['tenant_entry_bound'] ?? null) === true;
    }

    public function memberId(): int
    {
        $context = $this->get();
        if ($context->actorType !== ExecutionContext::MEMBER
            || !$context->scope instanceof AuthenticatedMemberContext) {
            throw new \DomainException('EXECUTION_MEMBER_CONTEXT_REQUIRED');
        }
        return $context->scope->memberId;
    }

    public function operation(): string
    {
        return $this->get()->operation;
    }

    public function requestId(): string
    {
        return $this->get()->requestId;
    }
}
