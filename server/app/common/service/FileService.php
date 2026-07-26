<?php
declare(strict_types=1);

namespace app\common\service;

/**
 * 文件服务：相对路径 ↔ 可访问 URL 转换。
 * 当前仅实现 local（本地磁盘）引擎——likeadmin 默认引擎。
 * OSS/COS/Qiniu 等云引擎为 Step-2 扩展点：在此按 storage 配置分支即可，
 * 对外接口（getFileUrl / setFileUrl）保持不变。
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
        $domain = request()->domain();
        return $domain . '/' . ltrim($uri, '/');
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
}
