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
 * 判定规则：以 storage/ 开头 → 本地；否则 → 当前默认云引擎的 domain。
 * 说明：与 likeadmin 一致，历史文件的 URL 依赖「当前默认引擎」，
 * 若切换引擎，旧云文件需保持该引擎 domain 配置不变方能访问。
 */
class FileService
{
    /** 相对 uri → 绝对 URL（已是 http(s) 则原样返回） */
    public static function getFileUrl(string $uri = ''): string
    {
        if ($uri === '') {
            return '';
        }
        if (str_starts_with($uri, 'http://') || str_starts_with($uri, 'https://')) {
            return $uri;
        }

        // 本地文件：走当前站点域名 + /storage 映射
        if (str_starts_with($uri, 'storage/')) {
            return request()->domain() . '/' . ltrim($uri, '/');
        }

        // 云文件：拼当前默认云引擎配置的 domain
        $domain = self::cloudDomain();
        if ($domain === '') {
            return request()->domain() . '/' . ltrim($uri, '/');
        }
        return self::format($domain, $uri);
    }

    /** 绝对 URL → 相对 uri（去掉本站域名前缀） */
    public static function setFileUrl(string $url = ''): string
    {
        if ($url === '') {
            return '';
        }
        $domain = request()->domain();
        return ltrim(str_replace($domain . '/', '', $url), '/');
    }

    /** 当前默认引擎的云端访问域名；local 或未配置时返回 '' */
    private static function cloudDomain(): string
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $default = (string) ConfigService::get('storage', 'default', 'local');
        if ($default === 'local' || $default === '') {
            return $cache = '';
        }
        $config = Driver::engineConfig($default);
        return $cache = (string) ($config['domain'] ?? '');
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
