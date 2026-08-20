<?php
declare(strict_types=1);

namespace app\adminapi\logic\setting;

use app\common\logic\BaseLogic;
use app\common\service\ConfigService;
use app\common\service\hot_search\HotSearchTenantContext;
use app\common\service\hot_search\HotSearchTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\facade\Db;

/**
 * 热门搜索设置 Logic
 *
 * - 开关：ConfigService type=hot_search, name=status（0关 1开，实例级）
 * - 词条：pa_hot_search（Tenant-owned name + sort），当前 Tenant「全删再全建」保存
 */
class HotSearchLogic extends BaseLogic
{
    protected const CONFIG_TYPE = 'hot_search';

    /** 读取配置：开关 + 词条列表 */
    public static function getConfig(TenantContext $context): array
    {
        self::clearError();
        return [
            'status' => (int) ConfigService::get(self::CONFIG_TYPE, 'status', 0),
            'data'   => HotSearchTenantRepository::terms($context)
                ->field(['id', 'name', 'sort'])
                ->order(['sort' => 'desc', 'id' => 'desc'])
                ->select()
                ->toArray(),
        ];
    }

    /**
     * 保存配置：写开关 + 全量替换词条
     * @param array<string,mixed> $params
     */
    public static function setConfig(TenantContext $context, array $params): bool
    {
        self::clearError();
        HotSearchTenantContext::tenantId($context);
        $rows = [];
        foreach ((array) ($params['data'] ?? []) as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $rows[] = ['name' => $name, 'sort' => (int) ($item['sort'] ?? 0)];
        }

        Db::startTrans();
        try {
            ConfigService::set(self::CONFIG_TYPE, 'status', (int) ($params['status'] ?? 0));
            HotSearchTenantRepository::replace($context, $rows);
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            return self::fail($e);
        }
    }
}
