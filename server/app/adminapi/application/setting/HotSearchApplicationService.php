<?php
declare(strict_types=1);

namespace app\adminapi\application\setting;

use app\common\application\ApplicationService;
use app\common\persistence\TransactionalExecution;
use app\common\service\config\TenantApplicationSettingService;
use app\common\service\hot_search\HotSearchTenantContext;
use app\common\service\hot_search\HotSearchTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;

/**
 * 热门搜索设置 Logic
 *
 * - 开关：pa_tenant_setting namespace=hot-search（0关 1开）
 * - 词条：pa_hot_search（Tenant-owned name + sort），当前 Tenant「全删再全建」保存
 */
class HotSearchApplicationService extends ApplicationService
{
    protected const CONFIG_TYPE = 'hot_search';

    public function __construct(private readonly TransactionalExecution $transactions)
    {
    }

    /** 读取配置：开关 + 词条列表 */
    public function getConfig(TenantContext $context): array
    {
        self::clearError();
        return [
            'status' => (int)TenantApplicationSettingService::hotSearch($context)['status'],
            'data'   => HotSearchTenantRepository::terms()
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
    public function setConfig(TenantContext $context, array $params): bool
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

        try {
            return $this->transactions->run(function () use ($context, $params, $rows): bool {
                TenantApplicationSettingService::replaceHotSearch($context, [
                    'status' => (int)($params['status'] ?? 0),
                ]);
                HotSearchTenantRepository::replace($rows);
                return true;
            });
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }
}
