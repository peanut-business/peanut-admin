<?php
declare(strict_types=1);

namespace app\adminapi\validate\auth;

use think\Validate;

class MenuValidate extends Validate
{
    protected $rule = [
        'id'   => 'require|integer|gt:0',
        'name' => 'require|max:50',
        'type' => 'require|in:M,C,A',
        'pid'  => 'integer|egt:0',
    ];

    protected $message = [
        'id.require'   => 'id 不能为空',
        'name.require' => '菜单名称不能为空',
        'name.max'     => '菜单名称最多 50 个字符',
        'type.require' => '菜单类型不能为空',
        'type.in'      => '菜单类型只能是 M（目录）/C（菜单）/A（按钮）',
    ];

    protected $scene = [
        'add'  => ['name', 'type', 'pid'],
        'edit' => ['id', 'name', 'type', 'pid'],
    ];
}
