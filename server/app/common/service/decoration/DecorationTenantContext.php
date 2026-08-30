<?php
declare(strict_types=1);

namespace app\common\service\decoration;

use PeanutAdmin\Kernel\Auth\AuthException;
use app\common\execution\ExecutionContext;
use app\common\execution\ExecutionContextAccess;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class DecorationTenantContext
{
    public const PUBLIC_ACTOR = 'peanut.decoration.public-read';
    public const MOBILE_PAGE_OPERATION = 'decoration.mobile-page';
    public const PC_PAGE_OPERATION = 'decoration.pc-page';
    public const CONFIG_OPERATION = 'decoration.config';
    public const ARTICLE_INDEX_OPERATION = 'article.index';
    public const ARTICLE_PC_INDEX_OPERATION = 'article.pc-index';

    private const SYSTEM_ACTORS = [
        self::MOBILE_PAGE_OPERATION => self::PUBLIC_ACTOR,
        self::PC_PAGE_OPERATION => self::PUBLIC_ACTOR,
        self::CONFIG_OPERATION => self::PUBLIC_ACTOR,
        self::ARTICLE_INDEX_OPERATION => 'peanut.article.public-read',
        self::ARTICLE_PC_INDEX_OPERATION => 'peanut.article.public-read',
    ];

    public static function member(): TenantContext
    {
        $current = ExecutionContextAccess::current();
        $context = $current?->scope;
        if (!$context instanceof TenantContext || !self::trustedMember($context)) {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        return $context;
    }

    public static function read(string $operation): TenantContext|TenantSystemContext
    {
        $current = ExecutionContextAccess::current();
        $context = $current?->scope;
        self::tenantId($context, $operation);
        return $context;
    }

    public static function tenantId(mixed $context, string $operation = ''): int
    {
        if ($context instanceof TenantContext && self::trustedMember($context)) {
            return $context->tenantId;
        }
        if ($context instanceof TenantSystemContext
            && $context->tenantId > 0
            && isset(self::SYSTEM_ACTORS[$operation])
            && $context->actorKey === self::SYSTEM_ACTORS[$operation]
            && $context->operation === $operation
            && $context->operationId !== '') {
            return $context->tenantId;
        }
        throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
    }

    private static function trustedMember(TenantContext $context): bool
    {
        return $context->tenantId > 0
            && $context->accountId > 0
            && $context->memberId > 0
            && $context->authorizationRevision > 0
            && $context->sessionKey !== ''
            && $context->clientKey !== ''
            && $context->requestId !== '';
    }

    private function __construct()
    {
    }
}
