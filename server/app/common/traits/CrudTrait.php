<?php
declare(strict_types=1);

namespace app\common\traits;

use app\common\http\PageResult;
use app\common\application\BusinessException;
use app\common\validate\TenantContextValidate;
use LogicException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\response\Json;

/**
 * Standard, Ergonomic CRUD Trait for ThinkPHP 8 Controllers.
 * Decoupled from specific Context (Tenant / Platform / Global) using composition.
 */
trait CrudTrait
{
    use ApiResponseTrait;

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
            $this->crudAddSuccessMessage(),
        );
    }

    final public function edit(): Json
    {
        $context = $this->resolveCrudContext();
        return $this->renderMutation(
            $this->performEdit($context, $this->editInput($context)),
            $this->crudEditSuccessMessage(),
        );
    }

    final public function delete(): Json
    {
        $context = $this->resolveCrudContext();
        return $this->renderMutation(
            $this->performDelete($context, $this->deleteInput($context)),
            $this->crudDeleteSuccessMessage(),
        );
    }

    final public function updateStatus(): Json
    {
        $context = $this->resolveCrudContext();
        return $this->renderMutation(
            $this->performStatusUpdate($context, $this->statusInput($context)),
            $this->crudStatusSuccessMessage(),
        );
    }

    abstract protected function resolveCrudContext(): mixed;
    abstract protected function crudService(): object;

    protected function listsInput(mixed $context): array
    {
        $params = $this->request->get();
        return $this->crudValidateLists()
            ? $this->validatedInput($context, 'lists', $params)
            : $params;
    }

    protected function detailInput(mixed $context): array
    {
        return $this->validatedInput($context, 'detail', $this->request->get());
    }

    protected function addInput(mixed $context): array
    {
        $params = $this->validatedInput($context, 'add', $this->request->post());
        $this->beforeAdd($params, $context);
        return $params;
    }

    protected function editInput(mixed $context): array
    {
        $params = $this->validatedInput($context, 'edit', $this->request->post());
        $this->beforeEdit($params, $context);
        return $params;
    }

    protected function deleteInput(mixed $context): array
    {
        $params = $this->validatedInput($context, 'delete', $this->request->post());
        $this->beforeDelete($params, $context);
        return $params;
    }

    protected function statusInput(mixed $context): array
    {
        return $this->validatedInput($context, $this->crudStatusScene(), $this->request->post());
    }

    protected function beforeAdd(array &$params, mixed $context): void {}
    protected function beforeEdit(array &$params, mixed $context): void {}
    protected function beforeDelete(array &$params, mixed $context): void {}

    protected function performLists(mixed $context, array $params): PageResult|array
    {
        return $this->crudService()->lists($context, $params);
    }

    protected function performDetail(mixed $context, array $params): array
    {
        return $this->crudService()->detail($context, (int) $params['id']);
    }

    protected function performAdd(mixed $context, array $params): bool
    {
        return $this->crudService()->add($context, $params);
    }

    protected function performEdit(mixed $context, array $params): bool
    {
        return $this->crudService()->edit($context, $params);
    }

    protected function performDelete(mixed $context, array $params): bool
    {
        return $this->crudService()->delete($context, (int) $params['id']);
    }

    protected function performStatusUpdate(mixed $context, array $params): bool
    {
        return $this->crudService()->updateStatus(
            $context,
            (int) $params['id'],
            (int) $params[$this->crudStatusField()],
        );
    }

    protected function renderLists(PageResult|array $result): Json
    {
        return $this->data($result);
    }

    protected function renderDetail(array $result): Json
    {
        if ($result === []) {
            throw BusinessException::notFound('ADMIN_RESOURCE_NOT_FOUND', $this->crudNotFoundMessage());
        }
        return $this->data($result);
    }

    protected function renderMutation(bool $result, string $successMessage): Json
    {
        if (!$result) {
            throw new LogicException('CRUD_MUTATION_MUST_SUCCEED_OR_THROW');
        }
        return $this->success($successMessage);
    }

    protected function validatedInput(
        mixed $context,
        string $scene,
        array $params,
    ): array
    {
        $validatorClass = $this->crudValidateClass();
        if ($context instanceof TenantContext
            && is_subclass_of($validatorClass, TenantContextValidate::class)) {
            $validator = new $validatorClass();
            $validator->forTenant($context)
                ->scene($scene)
                ->failException(true)
                ->check($params);
            return $params;
        }

        $this->validate($params, $validatorClass . '.' . $scene);
        return $params;
    }

    protected function crudValidateClass(): string
    {
        $validate = defined(static::class . '::CRUD_VALIDATE') ? static::CRUD_VALIDATE : '';
        if ($validate === '' || !class_exists($validate)) {
            throw new LogicException(sprintf(
                '%s must configure a valid CRUD_VALIDATE class.',
                static::class,
            ));
        }
        return $validate;
    }

    protected function crudAddSuccessMessage(): string
    {
        return defined(static::class . '::CRUD_ADD_SUCCESS_MESSAGE') ? static::CRUD_ADD_SUCCESS_MESSAGE : '操作成功';
    }

    protected function crudEditSuccessMessage(): string
    {
        return defined(static::class . '::CRUD_EDIT_SUCCESS_MESSAGE') ? static::CRUD_EDIT_SUCCESS_MESSAGE : '操作成功';
    }

    protected function crudDeleteSuccessMessage(): string
    {
        return defined(static::class . '::CRUD_DELETE_SUCCESS_MESSAGE') ? static::CRUD_DELETE_SUCCESS_MESSAGE : '操作成功';
    }

    protected function crudStatusSuccessMessage(): string
    {
        return defined(static::class . '::CRUD_STATUS_SUCCESS_MESSAGE') ? static::CRUD_STATUS_SUCCESS_MESSAGE : '操作成功';
    }

    protected function crudNotFoundMessage(): string
    {
        return defined(static::class . '::CRUD_NOT_FOUND_MESSAGE') ? static::CRUD_NOT_FOUND_MESSAGE : '数据不存在';
    }

    protected function crudStatusField(): string
    {
        return defined(static::class . '::CRUD_STATUS_FIELD') ? static::CRUD_STATUS_FIELD : 'is_disable';
    }

    protected function crudStatusScene(): string
    {
        return defined(static::class . '::CRUD_STATUS_SCENE') ? static::CRUD_STATUS_SCENE : 'status';
    }

    protected function crudValidateLists(): bool
    {
        return defined(static::class . '::CRUD_VALIDATE_LISTS') ? (bool)static::CRUD_VALIDATE_LISTS : false;
    }
}
