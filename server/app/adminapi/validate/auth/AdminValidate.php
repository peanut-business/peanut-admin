<?php
declare(strict_types=1);

namespace app\adminapi\validate\auth;

use app\common\model\auth\Admin;
use think\Validate;

class AdminValidate extends Validate
{
    protected $rule = [
        'id'       => 'require|integer|gt:0',
        'username' => 'require|alphaDash|length:3,50|unique:' . Admin::class,
        'password' => 'require|length:6,32',
        'nickname' => 'max:50',
    ];

    protected $message = [
        'id.require'         => 'id 不能为空',
        'username.require'   => '用户名不能为空',
        'username.alphaDash' => '用户名只能是字母、数字、下划线或短横线',
        'username.length'    => '用户名长度为 3~50 个字符',
        'username.unique'    => '用户名已存在',
        'password.require'   => '密码不能为空',
        'password.length'    => '密码长度为 6~32 个字符',
        'nickname.max'       => '昵称最多 50 个字符',
    ];

    protected $scene = [
        // 新增：用户名唯一 + 密码必填
        'add'  => ['username', 'password', 'nickname'],
        // 编辑：不改用户名；密码可选（留空则不改）——在 logic 层处理，这里只校验 id/昵称
        'edit' => ['id', 'nickname'],
    ];
}
