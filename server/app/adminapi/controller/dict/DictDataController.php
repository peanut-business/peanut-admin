<?php
declare(strict_types=1);

namespace app\adminapi\controller\dict;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\adminapi\controller\AbstractTenantCrudController;
use app\adminapi\application\dict\DictDataApplicationService;
use app\adminapi\validate\dict\DictDataValidate;
use think\response\Json;

class DictDataController extends AbstractTenantCrudController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly DictDataApplicationService $dictionaryData)
    {
        parent::__construct($app, $executionContext, $dictionaryData);
    }
    protected const CRUD_VALIDATE = DictDataValidate::class;
    protected const CRUD_NOT_FOUND_MESSAGE = '字典数据不存在';

    /** 按类型标识取启用数据项（业务前端用） */
    public function byType(): Json
    {
        return $this->data($this->dictionaryData->byType(
            $this->resolveCrudContext(),
            (string) $this->request->get('type_value', ''),
        ));
    }
}
