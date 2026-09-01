<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\api\application\IndexApplicationService;

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
        $result = $this->index->getIndexData($this->publicTenantContext('article.index'));
        return $this->data($result);
    }

    /** 全局配置 */
    public function config()
    {
        $result = $this->index->getConfigData(
            $this->publicTenantContext('decoration.config')
        );
        return $this->data($result);
    }

    /** 政策协议 */
    public function policy()
    {
        $type   = $this->request->get('type/s', 'service');
        $result = $this->index->getPolicyByType(
            $this->publicTenantContext('decoration.config'),
            $type,
        );
        return $this->data($result);
    }
}
