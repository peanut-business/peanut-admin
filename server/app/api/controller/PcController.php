<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\common\execution\CurrentExecutionContext;
use app\api\application\IndexApplicationService;
use app\api\application\PcApplicationService;
use app\Modules\Official\Article\Contracts\PublicArticleQueries;
use app\common\service\tenant\TenantEntryBindingResolver;

/**
 * PC 端聚合接口（部分端点返回更丰富的字段或不同格式）
 */
class PcController extends BaseApiController
{
    public function __construct(
        App $app, CurrentExecutionContext $executionContext,
        private readonly PublicArticleQueries $articles,
        private readonly IndexApplicationService $indexApplication,
        private readonly PcApplicationService $pcApplication,
        private readonly TenantEntryBindingResolver $entryBindings,
    ) {
        parent::__construct($app, $executionContext);
    }

    public array $notNeedLogin = ['config', 'index', 'infoCenter', 'articleDetail'];

    /** PC 配置 */
    public function config()
    {
        $entryTenantId = $this->entryBindings->boundTenantId(
            $this->request,
            TenantEntryBindingResolver::ADMIN_CLIENT,
        );
        $result = $this->indexApplication->getConfigData(
            $this->publicTenantContext('decoration.config'),
            (string)$this->request->domain(),
            (string)$this->request->host(),
            $entryTenantId,
        );
        return $this->data($result);
    }

    /** PC 首页 */
    public function index()
    {
        $result = $this->pcApplication->getIndexData($this->publicTenantContext('article.pc-index'));
        return $this->data($result);
    }

    /** PC 资讯中心（同 article/lists） */
    public function infoCenter()
    {
        $this->publicTenantContext('article.info-center');
        return $this->data($this->articles->infoCenter());
    }

    /** PC 文章详情 */
    public function articleDetail()
    {
        $id     = $this->request->get('id/d', 0);
        $source = $this->request->get('source/s', 'default');
        $this->publicTenantContext('article.pc-detail');
        return $this->data($this->articles->pcDetail($this->memberId, $id, $source));
    }
}
