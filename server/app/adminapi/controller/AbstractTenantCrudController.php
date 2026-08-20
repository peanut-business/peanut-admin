<?php
declare(strict_types=1);

namespace app\adminapi\controller;

use LogicException;
use PeanutAdmin\Kernel\Auth\TenantContext;
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
    protected const CRUD_LOGIC = '';

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

    abstract protected function resolveCrudContext(): TenantContext;

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

    /** @return array{lists:array,count:int,pageNo?:int,pageSize?:int,page_no?:int,page_size?:int,extend?:array}|false */
    protected function performLists(TenantContext $context, array $params): array|false
    {
        $logic = $this->crudLogicClass();
        return $logic::lists($context, $params);
    }

    protected function performDetail(TenantContext $context, array $params): array
    {
        $logic = $this->crudLogicClass();
        return $logic::detail($context, (int) $params['id']);
    }

    protected function performAdd(TenantContext $context, array $params): bool
    {
        $logic = $this->crudLogicClass();
        return $logic::add($context, $params);
    }

    protected function performEdit(TenantContext $context, array $params): bool
    {
        $logic = $this->crudLogicClass();
        return $logic::edit($context, $params);
    }

    protected function performDelete(TenantContext $context, array $params): bool
    {
        $logic = $this->crudLogicClass();
        return $logic::delete($context, (int) $params['id']);
    }

    protected function performStatusUpdate(TenantContext $context, array $params): bool
    {
        $logic = $this->crudLogicClass();
        return $logic::updateStatus(
            $context,
            (int) $params['id'],
            (int) $params[static::CRUD_STATUS_FIELD],
        );
    }

    protected function renderLists(array|false $result): Json
    {
        if ($result === false) {
            return $this->fail($this->crudError());
        }

        return $this->dataLists(
            $result['lists'],
            (int) $result['count'],
            (int) $result['pageNo'],
            (int) $result['pageSize'],
        );
    }

    protected function renderDetail(array $result): Json
    {
        return $result === []
            ? $this->fail(static::CRUD_NOT_FOUND_MESSAGE)
            : $this->data($result);
    }

    protected function renderMutation(bool $result, string $successMessage): Json
    {
        return $result
            ? $this->success($successMessage)
            : $this->fail($this->crudError());
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
    final protected function crudLogicClass(): string
    {
        $logic = static::CRUD_LOGIC;
        if ($logic === '' || !class_exists($logic)) {
            throw new LogicException(sprintf(
                '%s must configure a valid CRUD_LOGIC class.',
                static::class,
            ));
        }

        return $logic;
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

    final protected function crudError(): string
    {
        $logic = $this->crudLogicClass();
        if (!is_callable([$logic, 'getError'])) {
            throw new LogicException(sprintf(
                '%s must provide a static getError() method.',
                $logic,
            ));
        }

        return (string) $logic::getError();
    }
}
