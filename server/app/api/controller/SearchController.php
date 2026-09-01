<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\api\application\SearchApplicationService;
use app\common\service\hot_search\HotSearchTenantContext;

class SearchController extends BaseApiController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly SearchApplicationService $search)
    {
        parent::__construct($app, $executionContext);
    }

    public array $notNeedLogin = ['hotLists'];

    /** 热门搜索 */
    public function hotLists()
    {
        $result = $this->search->hotLists(
            HotSearchTenantContext::read()
        );
        return $this->data($result);
    }
}
