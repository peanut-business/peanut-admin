<?php
declare(strict_types=1);

namespace app\common\execution;

use app\platform\context\PlatformOperatorContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** Read-only access to the context established by the active boundary. */
final readonly class ExecutionContextAccess
{
    public function __construct(private CurrentExecutionContext $current)
    {
    }

    public function current(): ?ExecutionContext
    {
        return $this->current->current();
    }

    public function tenantAdmin(): TenantContext
    {
        return $this->current->tenantAdmin();
    }

    public function admin(): AdminExecutionContext
    {
        return $this->current->admin();
    }

    public function member(): AuthenticatedMemberContext
    {
        return $this->current->member();
    }

    public function consumer(): ConsumerExecutionContext
    {
        return $this->current->consumer();
    }

    public function system(): TenantSystemContext
    {
        return $this->current->system();
    }

    public function systemExecution(): SystemExecutionContext
    {
        return $this->current->systemExecution();
    }

    public function platform(): PlatformOperatorContext
    {
        return $this->current->platform();
    }

    public function instance(): InstanceExecutionScope
    {
        return $this->current->instance();
    }

    /** @return array<string,mixed> */
    public function principal(): array
    {
        return $this->current->tenantAdminPrincipal();
    }

    public function tenantEntryBound(): bool
    {
        return $this->current->tenantEntryBound();
    }

    public function tenantId(): int
    {
        return $this->current->tenantId();
    }
}
