<?php
declare(strict_types=1);

namespace app\adminapi\controller\dict;

use think\App;

use app\adminapi\controller\AbstractTenantCrudController;
use app\adminapi\application\dict\DictTypeApplicationService;
use app\adminapi\validate\dict\DictTypeValidate;
use app\common\service\dict\DictTenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\response\Json;

class DictTypeController extends AbstractTenantCrudController
{
    public function __construct(App $app, private readonly DictTypeApplicationService $dictionaryTypes)
    {
        parent::__construct($app);
    }

    protected const CRUD_SERVICE = DictTypeApplicationService::class;
    protected const CRUD_VALIDATE = DictTypeValidate::class;
    protected const CRUD_NOT_FOUND_MESSAGE = '字典类型不存在';

    public function all(): Json
    {
        return $this->data($this->dictionaryTypes->all($this->resolveCrudContext()));
    }

    protected function resolveCrudContext(): TenantContext
    {
        return DictTenantContext::member();
    }
}
