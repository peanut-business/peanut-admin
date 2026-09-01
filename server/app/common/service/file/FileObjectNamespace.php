<?php
declare(strict_types=1);

namespace app\common\service\file;

use app\common\enum\FileEnum;
use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\FileMedia\Storage\TenantObjectNamespace;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class FileObjectNamespace
{
    public static function directory(AuthenticatedMemberContext|TenantContext $context, int $type): string
    {
        if (!FileEnum::isValidType($type)) {
            throw new \InvalidArgumentException('文件类型无效');
        }
        return TenantObjectNamespace::directory(
            $context->tenantId,
            FileEnum::SAVE_DIR[$type],
        );
    }

    public static function ownsUri(AuthenticatedMemberContext|TenantContext $context, string $uri): bool
    {
        return TenantObjectNamespace::ownsUri($context->tenantId, $uri);
    }

    public static function ownsTenantUri(int $tenantId, string $uri): bool
    {
        return TenantObjectNamespace::ownsUri($tenantId, $uri);
    }

    public static function assertOwnedUri(AuthenticatedMemberContext|TenantContext $context, string $uri): void
    {
        if ($uri === '' || !self::ownsUri($context, $uri)) {
            throw new \RuntimeException('素材对象不属于当前租户');
        }
    }
}
