<?php
declare(strict_types=1);

namespace app\adminapi\validate\auth;

use think\Validate;

class RoleValidate extends Validate
{
    protected $rule = [
        'id'   => 'require|integer|gt:0',
        'name' => 'require|max:50',
    ];

    protected $message = [
        'id.require'   => 'id 不能为空',
        'name.require' => '角色名称不能为空',
        'name.max'     => '角色名称最多 50 个字符',
    ];

    protected $scene = [
        'add'  => ['name'],
        'edit' => ['id', 'name'],
    ];
}
