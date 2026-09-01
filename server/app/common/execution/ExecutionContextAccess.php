<?php
declare(strict_types=1);

namespace app\common\execution;

use app\platform\context\PlatformOperatorContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** Read-only access to the context established by the active boundary. */
final class ExecutionContextAccess
{
    public static function current(): ?ExecutionContext
    {
        return app(CurrentExecutionContext::class)->current();
    }

    public static function tenantAdmin(): TenantContext
    {
        return app(CurrentExecutionContext::class)->tenantAdmin();
    }

    public static function admin(): AdminExecutionContext
    {
        return app(CurrentExecutionContext::class)->admin();
    }

    public static function member(): AuthenticatedMemberContext
    {
        return app(CurrentExecutionContext::class)->member();
    }

    public static function consumer(): ConsumerExecutionContext
    {
        return app(CurrentExecutionContext::class)->consumer();
    }

    public static function system(): TenantSystemContext
    {
        return app(CurrentExecutionContext::class)->system();
    }

    public static function systemExecution(): SystemExecutionContext
    {
        return app(CurrentExecutionContext::class)->systemExecution();
    }

    public static function platform(): PlatformOperatorContext
    {
        return app(CurrentExecutionContext::class)->platform();
    }

    public static function instance(): InstanceExecutionScope
    {
        return app(CurrentExecutionContext::class)->instance();
    }

    /** @return array<string,mixed> */
    public static function principal(): array
    {
        return app(CurrentExecutionContext::class)->tenantAdminPrincipal();
    }

    public static function tenantEntryBound(): bool
    {
        return app(CurrentExecutionContext::class)->tenantEntryBound();
    }

    public static function tenantId(): int
    {
        return app(CurrentExecutionContext::class)->tenantId();
    }

    private function __construct()
    {
    }
}
