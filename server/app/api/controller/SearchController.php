<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\SearchLogic;
use app\common\service\hot_search\HotSearchTenantContext;

class SearchController extends BaseApiController
{
    public array $notNeedLogin = ['hotLists'];

    /** 热门搜索 */
    public function hotLists()
    {
        $result = SearchLogic::hotLists(
            HotSearchTenantContext::read($this->request)
        );
        return $this->data($result);
    }
}
