<?php
declare(strict_types=1);

namespace app\adminapi\controller\dept;

use app\adminapi\controller\AbstractTenantCrudController;
use app\common\service\org\OrgTenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\response\Json;

/** Tenant-aware CRUD template shared by the organization compatibility APIs. */
abstract class AbstractOrgCrudController extends AbstractTenantCrudController
{
    protected const CRUD_STATUS_FIELD = 'status';

    protected function resolveCrudContext(): TenantContext
    {
        return OrgTenantContext::member($this->request);
    }

    protected function validatedInput(
        TenantContext $context,
        string $scene,
        array $params,
    ): array {
        $logic = $this->crudLogicClass();
        if (is_callable([$logic, 'normalizeInput'])) {
            $params = $logic::normalizeInput($params);
        }
        if (!array_key_exists('status', $params) && array_key_exists('is_disable', $params)) {
            $params['status'] = (int) $params['is_disable'] === 0 ? 1 : 0;
        }

        $rules = $logic::validationRules($scene);
        if (in_array($scene, ['detail', 'delete'], true)) {
            $rules = ['id' => $rules['id'] ?? 'require|integer|gt:0'];
        } elseif ($scene === 'status') {
            $rules = [
                'id' => $rules['id'] ?? 'require|integer|gt:0',
                'status' => $rules['status'] ?? 'require|in:0,1',
            ];
        }
        $this->validate($params, $rules);
        return $params;
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
}
