<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Http\Controller;

use app\adminapi\controller\AbstractTenantCrudController;
use app\Modules\Official\Oauth\Service\OfficialAccountReplyLogic;
use app\Modules\Official\Oauth\Validation\OfficialAccountReplyValidate;
use app\common\service\member\MemberTenantContext;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\response\Json;

class OfficialAccountReplyController extends AbstractTenantCrudController
{
    protected const CRUD_LOGIC = OfficialAccountReplyLogic::class;
    protected const CRUD_VALIDATE = OfficialAccountReplyValidate::class;
    protected const CRUD_NOT_FOUND_MESSAGE = '自动回复不存在';
    protected const CRUD_DELETE_SUCCESS_MESSAGE = '删除成功';
    protected const CRUD_VALIDATE_LISTS = true;
    protected const CRUD_STATUS_FIELD = 'status';

    protected function resolveCrudContext(): TenantContext
    {
        $context = MemberTenantContext::member($this->request);
        if (!$context instanceof TenantContext) {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }

        return $context;
    }

    protected function renderLists(array|false $result): Json
    {
        return $this->data($result);
    }
}
