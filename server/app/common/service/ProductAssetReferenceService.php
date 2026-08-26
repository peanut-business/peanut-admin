<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** 内容与装修等产品记录的公开资源引用边界。 */
final class ProductAssetReferenceService
{
    /**
     * 同源 local storage URL 保存为相对 URI；云/CDN/外部 URL 保留绝对地址与原始域名。
     */
    public static function forStorage(
        string $value,
        ?string $applicationOrigin = null,
        AuthenticatedMemberContext|TenantContext|TenantSystemContext|null $context = null,
    ): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (!self::isHttpUrl($value)) {
            $uri = ltrim($value, '/');
            return $context === null ? $uri : FileService::setTenantFileUrl($context, $uri);
        }

        $origin = rtrim($applicationOrigin ?? (string)request()->domain(), '/');
        if (self::isSameOriginStorageUrl($value, $origin)) {
            $uri = ltrim((string)parse_url($value, PHP_URL_PATH), '/');
            return $context === null ? $uri : FileService::setTenantFileUrl($context, $uri);
        }
        if ($context !== null) {
            FileService::setTenantFileUrl($context, $value);
        }
        return $value;
    }

    public static function forRead(string $value): string
    {
        $value = trim($value);
        return self::isHttpUrl($value) ? $value : FileService::getFileUrl($value);
    }

    private static function isSameOriginStorageUrl(string $value, string $origin): bool
    {
        if (!self::isHttpUrl($origin)
            || parse_url($value, PHP_URL_USER) !== null
            || parse_url($value, PHP_URL_PASS) !== null
            || parse_url($value, PHP_URL_QUERY) !== null
            || parse_url($value, PHP_URL_FRAGMENT) !== null) {
            return false;
        }

        $valueScheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
        $originScheme = strtolower((string)parse_url($origin, PHP_URL_SCHEME));
        $valueHost = strtolower((string)parse_url($value, PHP_URL_HOST));
        $originHost = strtolower((string)parse_url($origin, PHP_URL_HOST));
        $valuePort = parse_url($value, PHP_URL_PORT) ?? ($valueScheme === 'https' ? 443 : 80);
        $originPort = parse_url($origin, PHP_URL_PORT) ?? ($originScheme === 'https' ? 443 : 80);
        $path = (string)parse_url($value, PHP_URL_PATH);

        return $valueScheme === $originScheme
            && $valueHost !== ''
            && $valueHost === $originHost
            && $valuePort === $originPort
            && str_starts_with($path, '/storage/');
    }

    private static function isHttpUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string)parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
