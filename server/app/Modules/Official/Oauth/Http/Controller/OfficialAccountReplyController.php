<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Http\Controller;

use app\adminapi\controller\AbstractTenantCrudController;
use app\common\http\PageResult;
use app\Modules\Official\Oauth\Application\OfficialAccountReplyApplicationService;
use app\Modules\Official\Oauth\Validation\OfficialAccountReplyValidate;
use think\response\Json;
use app\common\execution\CurrentExecutionContext;
use think\App;

class OfficialAccountReplyController extends AbstractTenantCrudController
{
    protected const CRUD_VALIDATE = OfficialAccountReplyValidate::class;
    protected const CRUD_NOT_FOUND_MESSAGE = '自动回复不存在';
    protected const CRUD_DELETE_SUCCESS_MESSAGE = '删除成功';
    protected const CRUD_VALIDATE_LISTS = true;
    protected const CRUD_STATUS_FIELD = 'status';

    public function __construct(
        App $app,
        CurrentExecutionContext $executionContext,
        OfficialAccountReplyApplicationService $replies,
    ) {
        parent::__construct($app, $executionContext, $replies);
    }

    protected function renderLists(PageResult|array $result): Json
    {
        return $this->data($result);
    }
}
