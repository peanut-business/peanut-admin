<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** 富文本内资源地址的相对 URI 存储与绝对 URL 读取转换。 */
class RichTextResourceService
{
    public static function forStorage(
        string $html,
        AuthenticatedMemberContext|TenantContext|TenantSystemContext|null $context = null
    ): string
    {
        return self::transform(HtmlSanitizerService::sanitize($html), false, $context);
    }

    public static function forRead(string $html): string
    {
        return self::transform(HtmlSanitizerService::sanitize($html), true);
    }

    private static function transform(
        string $html,
        bool $forRead,
        AuthenticatedMemberContext|TenantContext|TenantSystemContext|null $context = null
    ): string
    {
        if ($html === '') {
            return '';
        }

        $convert = static function (string $url) use ($forRead): string {
            $url = trim($url);
            if ($url === '' || preg_match('/^(?:mailto:|tel:|#)/i', $url)) {
                return $url;
            }
            if ($forRead) {
                return FileService::getFileUrl($url);
            }
            return $context === null
                ? FileService::setFileUrl($url)
                : FileService::setTenantFileUrl($context, $url);
        };

        $html = preg_replace_callback(
            '~(\b(?:src|href|poster)\s*=\s*)(["\'])(.*?)\2~is',
            static fn(array $match): string => $match[1] . $match[2] . $convert($match[3]) . $match[2],
            $html
        ) ?? $html;
        return $html;
    }
}
