<?php
declare(strict_types=1);

namespace app\common\service;

/** 富文本内资源地址的相对 URI 存储与绝对 URL 读取转换。 */
class RichTextResourceService
{
    public static function forStorage(string $html): string
    {
        return self::transform(HtmlSanitizerService::sanitize($html), false);
    }

    public static function forRead(string $html): string
    {
        return self::transform(HtmlSanitizerService::sanitize($html), true);
    }

    private static function transform(string $html, bool $forRead): string
    {
        if ($html === '') {
            return '';
        }

        $convert = static function (string $url) use ($forRead): string {
            $url = trim($url);
            if ($url === '' || preg_match('/^(?:mailto:|tel:|#)/i', $url)) {
                return $url;
            }
            return $forRead ? FileService::getFileUrl($url) : FileService::setFileUrl($url);
        };

        $html = preg_replace_callback(
            '~(\b(?:src|href|poster)\s*=\s*)(["\'])(.*?)\2~is',
            static fn(array $match): string => $match[1] . $match[2] . $convert($match[3]) . $match[2],
            $html
        ) ?? $html;
        return $html;
    }
}
