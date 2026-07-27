<?php
declare(strict_types=1);

namespace app\adminapi\validate\dept;

use think\Validate;

class JobsValidate extends Validate
{
    protected $rule = [
        'id'   => 'require|integer|gt:0',
        'name' => 'require|max:50',
        'code' => 'require|max:64',
        'sort' => 'integer|egt:0',
    ];

    protected $message = [
        'id.require'   => 'id 不能为空',
        'name.require' => '岗位名称不能为空',
        'name.max'     => '岗位名称最多 50 个字符',
        'code.require' => '岗位编码不能为空',
        'code.max'     => '岗位编码最多 64 个字符',
    ];

    protected $scene = [
        'add'  => ['name', 'code', 'sort'],
        'edit' => ['id', 'name', 'code', 'sort'],
    ];
}
