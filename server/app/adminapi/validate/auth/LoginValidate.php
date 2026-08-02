<?php
declare(strict_types=1);

namespace app\adminapi\validate\auth;

use think\Validate;

class LoginValidate extends Validate
{
    protected $rule = [
        'account'  => 'require|max:50',
        'password' => 'require',
        'terminal' => 'require|integer|in:1,2',
    ];

    protected $message = [
        'account.require'  => '请输入账号',
        'account.max'      => '账号长度不能超过 50 个字符',
        'password.require' => '请输入密码',
        'terminal.require' => '请选择登录终端',
        'terminal.integer' => '登录终端参数错误',
        'terminal.in'      => '登录终端参数错误',
    ];
}
