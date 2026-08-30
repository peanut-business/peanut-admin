<?php
declare(strict_types=1);

namespace app\adminapi\controller\decoration;

use think\App;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\application\decoration\DecorationPageApplicationService;
use app\adminapi\validate\decoration\DecorationPageValidate;
use app\common\enum\decoration\DecorationEnum;
use app\common\service\decoration\DecorationTenantContext;
use app\common\service\module\ModuleExecutionBoundary;
use PeanutAdmin\Kernel\Module\ModuleException;

class DecorationPageController extends BaseAdminController
{
    public function __construct(App $app, private readonly DecorationPageApplicationService $decorationPages)
    {
        parent::__construct($app);
    }

    public function mobileLists()
    {
        return $this->data($this->decorationPages->lists(
            DecorationTenantContext::member(),
            DecorationEnum::MOBILE_TYPES
        ));
    }

    public function mobileDetail()
    {
        return $this->detail(DecorationEnum::MOBILE_TYPES);
    }

    public function mobileSave()
    {
        return $this->save(DecorationEnum::MOBILE_TYPES);
    }

    public function pcDetail()
    {
        return $this->detail([DecorationEnum::PC_HOME]);
    }

    public function pcLists()
    {
        return $this->data($this->decorationPages->lists(
            DecorationTenantContext::member(),
            [DecorationEnum::PC_HOME]
        ));
    }

    public function pcSave()
    {
        return $this->save([DecorationEnum::PC_HOME]);
    }

    public function article()
    {
        $params = $this->request->get();
        $this->validate($params, DecorationPageValidate::class . '.article');
        try {
            app(ModuleExecutionBoundary::class)->assertHttp('official.article', 'http.admin');
        } catch (ModuleException) {
            return $this->data([]);
        }
        return $this->data($this->decorationPages->articleOptions(
            DecorationTenantContext::member(),
            (int)($params['limit'] ?? 20)
        ));
    }

    private function detail(array $allowedTypes)
    {
        $params = $this->request->get();
        $this->validate($params, DecorationPageValidate::class . '.detail');
        $result = $this->decorationPages->detail(
            DecorationTenantContext::member(),
            (int)$params['id'],
            $allowedTypes
        );
        return $result === false ? $this->fail($this->decorationPages->getError()) : $this->data($result);
    }

    private function save(array $allowedTypes)
    {
        $params = $this->request->post();
        $this->validate($params, DecorationPageValidate::class . '.save');
        $result = $this->decorationPages->save(
            DecorationTenantContext::member(),
            $params,
            $allowedTypes
        );
        return $result ? $this->success('保存成功') : $this->fail($this->decorationPages->getError());
    }
}
