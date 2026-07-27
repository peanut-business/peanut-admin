<?php
declare(strict_types=1);

namespace app\adminapi\validate\dept;

use think\Validate;

class DeptValidate extends Validate
{
    protected $rule = [
        'id'     => 'require|integer|gt:0',
        'name'   => 'require|max:50',
        'pid'    => 'integer|egt:0',
        'leader' => 'max:50',
        'mobile' => 'max:20',
        'sort'   => 'integer|egt:0',
    ];

    protected $message = [
        'id.require'   => 'id 不能为空',
        'name.require' => '部门名称不能为空',
        'name.max'     => '部门名称最多 50 个字符',
        'leader.max'   => '负责人最多 50 个字符',
        'mobile.max'   => '联系电话最多 20 个字符',
    ];

    protected $scene = [
        'add'  => ['name', 'pid', 'leader', 'mobile', 'sort'],
        'edit' => ['id', 'name', 'pid', 'leader', 'mobile', 'sort'],
    ];
}
