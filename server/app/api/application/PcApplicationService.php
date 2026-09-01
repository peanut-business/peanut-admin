<?php
declare(strict_types=1);

namespace app\api\application;

use app\common\enum\decoration\DecorationEnum;
use app\common\service\decoration\DecorationReadService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** PC 端业务聚合。 */
class PcApplicationService
{
    public function __construct(
        private readonly ArticleApplicationService $articles,
        private readonly DecorationReadService $decoration,
    )
    {
    }

    /** PC 首页文章分组与即时生效的 PC 装修。 */
    public function getIndexData(TenantContext|TenantSystemContext $context): array
    {
        return [
            'all' => $this->articles->limitArticles('all', 5),
            'new' => $this->articles->limitArticles('new', 7),
            'hot' => $this->articles->limitArticles('hot', 8),
            'decorate' => $this->decoration->pageByType(
                $context,
                DecorationEnum::PC_HOME,
                'article.pc-index'
            ),
        ];
    }
}
