<?php
declare(strict_types=1);

namespace app\adminapi\controller;

use app\common\http\PageResult;
use app\common\application\BusinessException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use app\common\execution\CurrentExecutionContext;
use think\App;
use think\response\Json;

/**
 * Template controller for tenant CRUD resources with an identical contract.
 *
 * Input, execution and rendering methods are the supported action hooks. Stop
 * using this base when a resource must replace more than one third of them or
 * when its transaction, actor, side-effect or response contract differs.
 */
abstract class AbstractTenantCrudController extends BaseAdminController
{
    /** @var class-string */
    protected const CRUD_VALIDATE = '';

    protected const CRUD_NOT_FOUND_MESSAGE = '数据不存在';
    protected const CRUD_ADD_SUCCESS_MESSAGE = '操作成功';
    protected const CRUD_EDIT_SUCCESS_MESSAGE = '操作成功';
    protected const CRUD_DELETE_SUCCESS_MESSAGE = '操作成功';
    protected const CRUD_STATUS_SUCCESS_MESSAGE = '操作成功';
    protected const CRUD_VALIDATE_LISTS = false;
    protected const CRUD_STATUS_FIELD = 'is_disable';
    protected const CRUD_STATUS_SCENE = 'status';

    public function __construct(
        App $app,
        CurrentExecutionContext $executionContext,
        private readonly object $crudServiceInstance,
    ) {
        parent::__construct($app, $executionContext);
    }

    final public function lists(): Json
    {
        $context = $this->resolveCrudContext();
        return $this->renderLists($this->performLists($context, $this->listsInput($context)));
    }

    final public function detail(): Json
    {
        $context = $this->resolveCrudContext();
        return $this->renderDetail($this->performDetail($context, $this->detailInput($context)));
    }

    final public function add(): Json
    {
        $context = $this->resolveCrudContext();
        return $this->renderMutation(
            $this->performAdd($context, $this->addInput($context)),
            static::CRUD_ADD_SUCCESS_MESSAGE,
        );
    }

    final public function edit(): Json
    {
        $context = $this->resolveCrudContext();
        return $this->renderMutation(
            $this->performEdit($context, $this->editInput($context)),
            static::CRUD_EDIT_SUCCESS_MESSAGE,
        );
    }

    final public function delete(): Json
    {
        $context = $this->resolveCrudContext();
        return $this->renderMutation(
            $this->performDelete($context, $this->deleteInput($context)),
            static::CRUD_DELETE_SUCCESS_MESSAGE,
        );
    }

    final public function updateStatus(): Json
    {
        $context = $this->resolveCrudContext();
        return $this->renderMutation(
            $this->performStatusUpdate($context, $this->statusInput($context)),
            static::CRUD_STATUS_SUCCESS_MESSAGE,
        );
    }

    protected function resolveCrudContext(): TenantContext
    {
        return $this->tenantAdminContext();
    }

    protected function listsInput(TenantContext $context): array
    {
        $params = $this->request->get();
        return static::CRUD_VALIDATE_LISTS
            ? $this->validatedInput($context, 'lists', $params)
            : $params;
    }

    protected function detailInput(TenantContext $context): array
    {
        return $this->validatedInput($context, 'detail', $this->request->get());
    }

    protected function addInput(TenantContext $context): array
    {
        return $this->validatedInput($context, 'add', $this->request->post());
    }

    protected function editInput(TenantContext $context): array
    {
        return $this->validatedInput($context, 'edit', $this->request->post());
    }

    protected function deleteInput(TenantContext $context): array
    {
        return $this->validatedInput($context, 'delete', $this->request->post());
    }

    protected function statusInput(TenantContext $context): array
    {
        return $this->validatedInput($context, static::CRUD_STATUS_SCENE, $this->request->post());
    }

    protected function performLists(TenantContext $context, array $params): PageResult|array
    {
        return $this->crudService()->lists($context, $params);
    }

    protected function performDetail(TenantContext $context, array $params): array
    {
        return $this->crudService()->detail($context, (int) $params['id']);
    }

    protected function performAdd(TenantContext $context, array $params): bool
    {
        return $this->crudService()->add($context, $params);
    }

    protected function performEdit(TenantContext $context, array $params): bool
    {
        return $this->crudService()->edit($context, $params);
    }

    protected function performDelete(TenantContext $context, array $params): bool
    {
        return $this->crudService()->delete($context, (int) $params['id']);
    }

    protected function performStatusUpdate(TenantContext $context, array $params): bool
    {
        return $this->crudService()->updateStatus(
            $context,
            (int) $params['id'],
            (int) $params[static::CRUD_STATUS_FIELD],
        );
    }

    protected function renderLists(PageResult|array $result): Json
    {
        return $this->data($result);
    }

    protected function renderDetail(array $result): Json
    {
        if ($result === []) {
            throw BusinessException::notFound('ADMIN_RESOURCE_NOT_FOUND', static::CRUD_NOT_FOUND_MESSAGE);
        }
        return $this->data($result);
    }

    protected function renderMutation(bool $result, string $successMessage): Json
    {
        return $this->success($successMessage);
    }

    protected function validatedInput(
        TenantContext $context,
        string $scene,
        array $params,
    ): array
    {
        $this->validate($params, $this->crudValidateClass() . '.' . $scene);
        return $params;
    }

    /** @return class-string */
    final protected function crudValidateClass(): string
    {
        $validate = static::CRUD_VALIDATE;
        if ($validate === '' || !class_exists($validate)) {
            throw new LogicException(sprintf(
                '%s must configure a valid CRUD_VALIDATE class.',
                static::class,
            ));
        }

        return $validate;
    }

    final protected function crudService(): object
    {
        return $this->crudServiceInstance;
    }
}
