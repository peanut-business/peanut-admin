<?php
declare(strict_types=1);

namespace app\adminapi\application\decoration;

use app\common\application\BusinessException;
use app\common\service\decoration\DecorationReadService;
use app\common\service\decoration\DecorationSchemaService;
use app\common\service\decoration\DecorationTabbarTenantRepository;
use app\common\persistence\TransactionalExecution;
use app\Modules\Official\Article\Contracts\ArticleQueries;
use PeanutAdmin\Kernel\Auth\TenantContext;

class DecorationTabbarApplicationService
{
    public function __construct(
        private readonly TransactionalExecution $transactions,
        private readonly ArticleQueries $articles,
    ) {}

    public function detail(TenantContext $context): array
    {
        return DecorationReadService::tabbar($context, false);
    }

    public function save(TenantContext $context, array $style, array $items): bool
    {
        try {
            DecorationSchemaService::validateTabbar($context, $style, $items, $this->articles);
        } catch (\RuntimeException $exception) {
            throw BusinessException::invalid('DECORATION_TABBAR_INVALID', $exception->getMessage());
        }
        $this->transactions->run(function () use ($context, $style, $items): void {
                DecorationTabbarTenantRepository::replace($style, array_map(
                    static function (array $item) use ($context): array {
                        return DecorationSchemaService::resourcesForStorage($item, $context);
                    },
                    $items
                ));
        });
        return true;
    }
}
