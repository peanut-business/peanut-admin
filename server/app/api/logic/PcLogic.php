<?php
declare(strict_types=1);

namespace app\api\logic;

use app\common\logic\BaseLogic;
use app\common\enum\decoration\DecorationEnum;
use app\common\service\decoration\DecorationReadService;

/** PC 端业务聚合。 */
class PcLogic extends BaseLogic
{
    /** PC 首页文章分组与即时生效的 PC 装修。 */
    public static function getIndexData(): array
    {
        return [
            'all' => ArticleLogic::limitArticles('all', 5),
            'new' => ArticleLogic::limitArticles('new', 7),
            'hot' => ArticleLogic::limitArticles('hot', 8),
            'decorate' => DecorationReadService::pageByType(DecorationEnum::PC_HOME),
        ];
    }
}
