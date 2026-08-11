<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\service\storage\Driver;

/**
 * 文件服务：相对 uri ↔ 可访问 URL 转换，支持多存储引擎。
 *
 * uri 约定：
 *  - 本地引擎：以 storage/ 开头（如 storage/uploads/images/xxx.png），拼当前站点域名。
 *  - 云引擎：对象 key（如 uploads/images/xxx.png），拼配置的云端 domain。
 *
 * 判定规则：以 storage/ 开头或显式 local → 本地；显式云引擎使用该引擎 domain；
 * 没有 storage provenance 的旧 URI 才沿用当前默认云引擎。
 */
class FileService
{
    /** 相对 uri → 绝对 URL；素材可传入记录自己的 storage，避免默认引擎切换后误拼域名。 */
    public static function getFileUrl(string $uri = '', ?string $storage = null): string
    {
        if ($uri === '') {
            return '';
        }
        if (str_starts_with($uri, 'http://') || str_starts_with($uri, 'https://')) {
            return $uri;
        }

        // 本地文件：走当前站点域名 + /storage 映射
        if (str_starts_with($uri, 'storage/') || $storage === 'local') {
            return request()->domain() . '/' . ltrim($uri, '/');
        }

        // 素材记录优先使用自己的原始引擎；无 provenance 的旧 URI 沿用当前默认引擎。
        $explicitStorage = $storage !== null && trim($storage) !== '';
        $domain = self::cloudDomain($storage);
        if ($domain === '') {
            if ($explicitStorage) {
                return '';
            }
            return request()->domain() . '/' . ltrim($uri, '/');
        }
        return self::format($domain, $uri);
    }

    /** 绝对 URL → 相对 uri（去掉当前存储引擎域名前缀） */
    public static function setFileUrl(string $url = ''): string
    {
        if ($url === '') {
            return '';
        }
        $cloudDomain = self::cloudDomain();
        $domain = $cloudDomain === ''
            ? rtrim(request()->domain(), '/')
            : rtrim(self::format($cloudDomain, ''), '/');
        return ltrim(str_replace($domain . '/', '', $url), '/');
    }

    /** 当前默认引擎的云端访问域名；local 或未配置时返回 '' */
    private static function cloudDomain(?string $storage = null): string
    {
        $engine = trim((string)$storage);
        if ($engine === '') {
            $engine = (string) ConfigService::get('storage', 'default', 'local');
        }
        if ($engine === 'local' || $engine === '') {
            return '';
        }
        $config = Driver::engineConfig($engine);
        return (string) ($config['domain'] ?? '');
    }

    /** 拼接 domain 与 uri，规范化斜杠并补 http 协议 */
    private static function format(string $domain, string $uri): string
    {
        $domain = rtrim($domain, '/');
        if (!str_starts_with($domain, 'http://') && !str_starts_with($domain, 'https://')) {
            $domain = 'https://' . $domain;
        }
        return $domain . '/' . ltrim($uri, '/');
    }
}
