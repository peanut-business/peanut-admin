<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\IndexLogic;
use app\common\service\article\ArticleTenantContext;
use app\common\service\decoration\DecorationTenantContext;

class IndexController extends BaseApiController
{
    public array $notNeedLogin = ['index', 'config', 'policy'];

    /** 首页数据 */
    public function index()
    {
        $result = IndexLogic::getIndexData(ArticleTenantContext::read($this->request, 'article.index'));
        return $this->data($result);
    }

    /** 全局配置 */
    public function config()
    {
        $result = IndexLogic::getConfigData(DecorationTenantContext::read(
            $this->request,
            DecorationTenantContext::CONFIG_OPERATION
        ));
        return $this->data($result);
    }

    /** 政策协议 */
    public function policy()
    {
        $type   = $this->request->get('type/s', 'service');
        $result = IndexLogic::getPolicyByType($type);
        return $this->data($result);
    }
}
