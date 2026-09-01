<?php
declare(strict_types=1);

namespace app\adminapi\application\decoration;

use app\common\application\ApplicationService;
use app\common\service\decoration\DecorationReadService;
use app\common\service\decoration\DecorationSchemaService;
use app\common\service\decoration\DecorationTabbarTenantRepository;
use app\common\persistence\TransactionalExecution;
use PeanutAdmin\Kernel\Auth\TenantContext;

class DecorationTabbarApplicationService extends ApplicationService
{
    public function __construct(private readonly TransactionalExecution $transactions)
    {
    }

    public function detail(TenantContext $context): array
    {
        self::clearError();
        return DecorationReadService::tabbar($context, false);
    }

    public function save(TenantContext $context, array $style, array $items): bool
    {
        self::clearError();
        try {
            DecorationSchemaService::validateTabbar($context, $style, $items);
            $this->transactions->run(function () use ($context, $style, $items): void {
                DecorationTabbarTenantRepository::replace($style, array_map(
                    static function (array $item) use ($context): array {
                        return DecorationSchemaService::resourcesForStorage($item, $context);
                    },
                    $items
                ));
            });
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }
}
