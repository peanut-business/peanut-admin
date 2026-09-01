<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\api\application\IndexApplicationService;
use app\common\service\article\ArticleTenantContext;
use app\common\service\decoration\DecorationTenantContext;

class IndexController extends BaseApiController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly IndexApplicationService $index)
    {
        parent::__construct($app, $executionContext);
    }

    public array $notNeedLogin = ['index', 'config', 'policy'];

    /** 首页数据 */
    public function index()
    {
        $result = $this->index->getIndexData(ArticleTenantContext::read('article.index'));
        return $this->data($result);
    }

    /** 全局配置 */
    public function config()
    {
        $result = $this->index->getConfigData(
            DecorationTenantContext::read(DecorationTenantContext::CONFIG_OPERATION)
        );
        return $this->data($result);
    }

    /** 政策协议 */
    public function policy()
    {
        $type   = $this->request->get('type/s', 'service');
        $result = $this->index->getPolicyByType(
            DecorationTenantContext::read(DecorationTenantContext::CONFIG_OPERATION),
            $type,
        );
        return $this->data($result);
    }
}
