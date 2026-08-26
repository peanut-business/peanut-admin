<?php
declare(strict_types=1);

namespace app\Modules\Official\Article\Http\Controller;

use app\adminapi\controller\AbstractTenantCrudController;
use app\common\service\article\ArticleTenantContext;
use app\common\validate\TenantContextValidate;
use LogicException;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\response\Json;

/** Tenant-aware CRUD template shared only by the official Article domain. */
abstract class AbstractArticleCrudController extends AbstractTenantCrudController
{
    protected const CRUD_ADD_SUCCESS_MESSAGE = '添加成功';
    protected const CRUD_EDIT_SUCCESS_MESSAGE = '编辑成功';
    protected const CRUD_DELETE_SUCCESS_MESSAGE = '删除成功';
    protected const CRUD_STATUS_SUCCESS_MESSAGE = '修改成功';
    protected const CRUD_VALIDATE_LISTS = true;
    protected const CRUD_STATUS_FIELD = 'is_show';

    protected function resolveCrudContext(): TenantContext
    {
        $context = ArticleTenantContext::member($this->request);
        if (!$context instanceof TenantContext) {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }

        return $context;
    }

    protected function renderLists(array|false $result): Json
    {
        return $result === false
            ? $this->fail($this->crudError())
            : $this->data($result);
    }

    protected function renderDetail(array $result): Json
    {
        return $this->data($result);
    }

    protected function validatedInput(
        TenantContext $context,
        string $scene,
        array $params,
    ): array {
        $validatorClass = $this->crudValidateClass();
        $validator = new $validatorClass();
        if (!$validator instanceof TenantContextValidate) {
            throw new LogicException(sprintf(
                '%s must extend %s.',
                $validatorClass,
                TenantContextValidate::class,
            ));
        }

        $validator->forTenant($context)
            ->scene($scene)
            ->failException(true)
            ->check($params);

        return $params;
    }
}
