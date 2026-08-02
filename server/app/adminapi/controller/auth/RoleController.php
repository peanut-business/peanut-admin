<?php
declare(strict_types=1);

namespace app\adminapi\controller\auth;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\auth\RoleLogic;
use app\adminapi\validate\auth\RoleValidate;

class RoleController extends BaseAdminController
{
    public function lists()
    {
        $result = RoleLogic::lists($this->request->get());
        return $this->dataLists(
            $result['lists'],
            $result['count'],
            $result['pageNo'],
            $result['pageSize']
        );
    }

    public function all()
    {
        return $this->data(RoleLogic::getAll());
    }

    public function detail()
    {
        $params = $this->request->get();
        $this->validate($params, RoleValidate::class . '.detail');
        return $this->data(RoleLogic::detail((int)$params['id']));
    }

    public function add()
    {
        $params = $this->roleParams();
        $this->validate($params, RoleValidate::class . '.add');
        $result = RoleLogic::add($params);
        return $result
            ? $this->success('操作成功')
            : $this->fail(RoleLogic::getError());
    }

    public function edit()
    {
        $params = $this->roleParams();
        $this->validate($params, RoleValidate::class . '.edit');
        $result = RoleLogic::edit($params);
        return $result
            ? $this->success('操作成功')
            : $this->fail(RoleLogic::getError());
    }

    public function delete()
    {
        $params = $this->request->post();
        $this->validate($params, RoleValidate::class . '.delete');
        $result = RoleLogic::delete((int)$params['id']);
        return $result
            ? $this->success('操作成功')
            : $this->fail(RoleLogic::getError());
    }

    /**
     * menu_id 是正式契约；menu_ids 仅兼容现有 Peanut 前端。
     * 两者同时存在时始终以 menu_id 为准。
     */
    private function roleParams(): array
    {
        $params = $this->request->post();
        if (!array_key_exists('menu_id', $params) && array_key_exists('menu_ids', $params)) {
            $params['menu_id'] = $params['menu_ids'];
        }
        return $params;
    }
}
