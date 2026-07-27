<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\SearchLogic;

class SearchController extends BaseApiController
{
    public array $notNeedLogin = ['hotLists'];

    /** 热门搜索 */
    public function hotLists()
    {
        $result = SearchLogic::hotLists();
        return $this->data($result);
    }
}
