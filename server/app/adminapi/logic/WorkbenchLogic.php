<?php
declare(strict_types=1);

namespace app\adminapi\logic;

use app\common\logic\BaseLogic;
use app\common\service\FileService;
use app\common\service\config\PaConfigWebsiteStore;
use app\common\service\config\WebsiteConfigService;

class WorkbenchLogic extends BaseLogic
{
    public static function index(): array
    {
        return [
            'version' => self::versionInfo(),
            'today'   => self::today(),
            'menu'    => self::menu(),
            'visitor' => self::visitor(),
            'support' => self::support(),
            'sale'    => self::sale(),
        ];
    }

    public static function versionInfo(): array
    {
        $website = self::websiteService()->get();
        return [
            'version' => (string) config('project.version', '1.1.5'),
            'website' => $website['official_url'],
            'name'    => $website['name'],
            'based'   => (string) config(
                'project.based',
                'Vue 3.x、Element Plus、ThinkPHP 8、MySQL'
            ),
            'channel' => [
                'website' => $website['official_url'],
                'github'  => $website['github_url'],
            ],
        ];
    }

    private static function websiteService(): WebsiteConfigService
    {
        return new WebsiteConfigService(
            new PaConfigWebsiteStore(),
            static fn(string $value): string => FileService::getFileUrl($value),
            static fn(string $value): string => FileService::setFileUrl($value),
        );
    }

    public static function today(): array
    {
        return [
            'time'           => date('Y-m-d H:i:s'),
            'today_sales'    => 100,
            'total_sales'    => 1000,
            'today_visitor'  => 10,
            'total_visitor'  => 100,
            'today_new_user' => 30,
            'total_new_user' => 3000,
            'order_num'      => 12,
            'order_sum'      => 255,
        ];
    }

    public static function menu(): array
    {
        $items = [
            ['name' => '管理员', 'image' => 'menu_admin', 'url' => '/system/admin'],
            ['name' => '角色管理', 'image' => 'menu_role', 'url' => '/system/role'],
            ['name' => '部门管理', 'image' => 'menu_dept', 'url' => '/system/dept'],
            ['name' => '字典管理', 'image' => 'menu_dict', 'url' => '/system/dict'],
            ['name' => '代码生成器', 'image' => 'menu_generator', 'url' => '/dev-tools/code'],
            ['name' => '素材中心', 'image' => 'menu_file', 'url' => '/system/file'],
            ['name' => '菜单权限', 'image' => 'menu_auth', 'url' => '/system/menu'],
            ['name' => '网站信息', 'image' => 'menu_web', 'url' => '/system/config'],
        ];

        return array_map(static function (array $item): array {
            $item['image'] = FileService::getFileUrl(
                (string) config('project.default_image.' . $item['image'], '')
            );
            return $item;
        }, $items);
    }

    public static function visitor(): array
    {
        $date = [];
        $data = [];
        for ($i = 0; $i < 15; $i++) {
            $timestamp = strtotime("- {$i} day");
            $date[] = date('m/d', $timestamp);
            $data[] = rand(0, 100);
        }

        return [
            'date' => $date,
            'list' => [['name' => '访客数', 'data' => $data]],
        ];
    }

    public static function sale(): array
    {
        $date = [];
        $data = [];
        for ($i = 0; $i < 7; $i++) {
            $timestamp = strtotime("- {$i} day");
            $date[] = date('m/d', $timestamp);
            $data[] = rand(30, 200);
        }

        return [
            'date' => $date,
            'list' => [['name' => '销售量', 'data' => $data]],
        ];
    }

    public static function support(): array
    {
        $website = self::websiteService()->get();
        return [
            [
                'image' => FileService::getFileUrl(
                    (string) config('project.default_image.project_docs', '')
                ),
                'title' => '项目文档',
                'desc'  => '查看 Peanut Admin 使用与开发文档',
                'url'   => (string) $website['official_url'],
            ],
            [
                'image' => FileService::getFileUrl(
                    (string) config('project.default_image.technical_support', '')
                ),
                'title' => '技术支持',
                'desc'  => '获取 Peanut Admin 技术支持',
                'url'   => (string) $website['github_url'],
            ],
        ];
    }
}
