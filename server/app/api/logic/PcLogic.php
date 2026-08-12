<?php
declare(strict_types=1);

namespace app\api\logic;

use app\common\logic\BaseLogic;
use app\common\enum\decoration\DecorationEnum;
use app\common\service\decoration\DecorationReadService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** PC 端业务聚合。 */
class PcLogic extends BaseLogic
{
    /** PC 首页文章分组与即时生效的 PC 装修。 */
    public static function getIndexData(TenantContext|TenantSystemContext $context): array
    {
        return [
            'all' => ArticleLogic::limitArticles($context, 'all', 5),
            'new' => ArticleLogic::limitArticles($context, 'new', 7),
            'hot' => ArticleLogic::limitArticles($context, 'hot', 8),
            'decorate' => DecorationReadService::pageByType(DecorationEnum::PC_HOME),
        ];
    }
}
