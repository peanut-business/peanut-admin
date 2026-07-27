<?php
declare(strict_types=1);

namespace app\adminapi\logic\system;

use app\common\logic\BaseLogic;
use think\facade\Cache;

/**
 * 系统维护逻辑
 * Class SystemLogic
 * @package app\adminapi\logic\system
 */
class SystemLogic extends BaseLogic
{
    /**
     * 系统环境信息
     */
    public static function getInfo(): array
    {
        $server = [
            ['param' => '服务器操作系统', 'value' => PHP_OS],
            ['param' => 'web服务器环境', 'value' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'],
            ['param' => 'PHP版本', 'value' => PHP_VERSION],
            ['param' => '上传附件限制', 'value' => ini_get('upload_max_filesize')],
        ];

        $env = [
            [
                'option'  => 'PHP版本',
                'require' => '8.0版本以上',
                'status'  => (int) compare_php('8.0.0'),
                'remark'  => '',
            ],
        ];

        $auth = [
            [
                'dir'     => '/runtime',
                'require' => 'runtime目录可写',
                'status'  => (int) check_dir_write('runtime'),
                'remark'  => '',
            ],
            [
                'dir'     => '/public/storage',
                'require' => 'storage目录可写',
                'status'  => (int) check_dir_write('public/storage'),
                'remark'  => '',
            ],
        ];

        return [
            'server' => $server,
            'env'    => $env,
            'auth'   => $auth,
        ];
    }

    /**
     * 清除系统缓存
     */
    public static function clearCache(): bool
    {
        Cache::clear();
        del_target_dir(root_path() . 'runtime/file', true);
        return true;
    }
}
