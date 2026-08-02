<?php
declare(strict_types=1);

namespace app\adminapi\validate\auth;

use app\common\model\auth\AdminRole;
use app\common\model\auth\SystemMenu;
use app\common\model\auth\SystemRole;
use think\Validate;

class RoleValidate extends Validate
{
    protected $rule = [
        'id'      => 'require|integer|gt:0|checkRole',
        'name'    => 'require|length:1,50|checkUniqueName',
        'menu_id' => 'array|checkMenu',
    ];

    protected $message = [
        'id.require'      => '请选择角色',
        'id.integer'      => '角色参数错误',
        'id.gt'           => '角色参数错误',
        'name.require'    => '请输入角色名称',
        'name.length'     => '角色名称长度须在 1~50 个字符',
        'menu_id.array'   => '权限格式错误',
    ];

    protected $scene = [
        'add'    => ['name', 'menu_id'],
        'edit'   => ['id', 'name', 'menu_id'],
        'detail' => ['id'],
    ];

    public function sceneDelete(): self
    {
        return $this->only(['id'])->append('id', 'checkAdmin');
    }

    protected function checkRole(mixed $value): bool|string
    {
        return SystemRole::findOrEmpty((int)$value)->isEmpty()
            ? '角色不存在'
            : true;
    }

    protected function checkUniqueName(mixed $value, mixed $rule, array $data): bool|string
    {
        $query = SystemRole::where('name', trim((string)$value));
        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $query->where('id', '<>', $id);
        }
        return $query->count() > 0 ? '角色名称已存在' : true;
    }

    protected function checkMenu(mixed $value): bool|string
    {
        if (!is_array($value)) {
            return '权限格式错误';
        }

        $menuIds = array_values(array_unique(array_map('intval', $value)));
        if ($menuIds === []) {
            return true;
        }
        foreach ($menuIds as $menuId) {
            if ($menuId <= 0) {
                return '权限参数错误';
            }
        }

        return SystemMenu::whereIn('id', $menuIds)->count() === count($menuIds)
            ? true
            : '菜单权限不存在';
    }

    protected function checkAdmin(mixed $value): bool|string
    {
        return AdminRole::where('role_id', (int)$value)->count() > 0
            ? '有管理员在使用该角色，不允许删除'
            : true;
    }
}
