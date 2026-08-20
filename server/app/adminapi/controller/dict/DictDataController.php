<?php
declare(strict_types=1);

namespace app\adminapi\controller\dict;

use app\adminapi\controller\AbstractTenantCrudController;
use app\adminapi\logic\dict\DictDataLogic;
use app\adminapi\validate\dict\DictDataValidate;
use app\common\service\dict\DictTenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\response\Json;

class DictDataController extends AbstractTenantCrudController
{
    protected const CRUD_LOGIC = DictDataLogic::class;
    protected const CRUD_VALIDATE = DictDataValidate::class;
    protected const CRUD_NOT_FOUND_MESSAGE = '字典数据不存在';

    /** 按类型标识取启用数据项（业务前端用） */
    public function byType(): Json
    {
        return $this->data(DictDataLogic::byType(
            $this->resolveCrudContext(),
            (string) $this->request->get('type_value', ''),
        ));
    }

    protected function resolveCrudContext(): TenantContext
    {
        return DictTenantContext::member($this->request);
    }
}
