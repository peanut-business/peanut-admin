<?php
declare(strict_types=1);

namespace app\adminapi\controller\dict;

use app\adminapi\controller\AbstractTenantCrudController;
use app\adminapi\logic\dict\DictTypeLogic;
use app\adminapi\validate\dict\DictTypeValidate;
use app\common\service\dict\DictTenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\response\Json;

class DictTypeController extends AbstractTenantCrudController
{
    protected const CRUD_LOGIC = DictTypeLogic::class;
    protected const CRUD_VALIDATE = DictTypeValidate::class;
    protected const CRUD_NOT_FOUND_MESSAGE = '字典类型不存在';

    public function all(): Json
    {
        return $this->data(DictTypeLogic::all($this->resolveCrudContext()));
    }

    protected function resolveCrudContext(): TenantContext
    {
        return DictTenantContext::member($this->request);
    }
}
