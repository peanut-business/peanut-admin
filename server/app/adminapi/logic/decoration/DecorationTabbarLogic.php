<?php
declare(strict_types=1);

namespace app\adminapi\logic\decoration;

use app\common\logic\BaseLogic;
use app\common\service\decoration\DecorationReadService;
use app\common\service\decoration\DecorationSchemaService;
use app\common\service\decoration\DecorationTabbarTenantRepository;
use think\facade\Db;
use PeanutAdmin\Kernel\Auth\TenantContext;

class DecorationTabbarLogic extends BaseLogic
{
    public static function detail(TenantContext $context): array
    {
        return DecorationReadService::tabbar($context, false);
    }

    public static function save(TenantContext $context, array $style, array $items): bool
    {
        try {
            DecorationSchemaService::validateTabbar($context, $style, $items);
            Db::transaction(function () use ($context, $style, $items): void {
                DecorationTabbarTenantRepository::replace($context, $style, array_map(
                    static function (array $item): array {
                        return DecorationSchemaService::resourcesForStorage($item);
                    },
                    $items
                ));
            });
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }
}
