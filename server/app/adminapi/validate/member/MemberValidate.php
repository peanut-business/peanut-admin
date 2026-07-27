<?php
declare(strict_types=1);

namespace app\adminapi\validate\member;

use think\Validate;

class MemberValidate extends Validate
{
    protected $rule = [
        'id'       => 'require|integer|gt:0',
        'nickname' => 'require|max:50',
        'mobile'   => 'max:20',
        'email'    => 'email',
        'sex'      => 'in:0,1,2',
        'status'   => 'require|in:0,1',
        'amount'   => 'require|float',
    ];

    protected $message = [
        'id.require'       => 'id 不能为空',
        'nickname.require' => '昵称不能为空',
        'nickname.max'     => '昵称最多 50 个字符',
        'mobile.max'       => '手机号最多 20 个字符',
        'email.email'      => '邮箱格式不正确',
        'sex.in'           => '性别值无效',
        'status.require'   => '状态不能为空',
        'status.in'        => '状态值无效',
        'amount.require'   => '调整金额不能为空',
        'amount.float'     => '金额格式不正确',
    ];

    protected $scene = [
        'add'     => ['nickname', 'mobile', 'email', 'sex', 'status'],
        'edit'    => ['id', 'nickname', 'mobile', 'email', 'sex'],
        'balance' => ['id', 'amount'],
    ];
}
