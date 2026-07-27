<?php
declare(strict_types=1);

namespace app\adminapi\validate\notice;

use think\Validate;

class NoticeTemplateValidate extends Validate
{
    protected $rule = [
        'id'      => 'require|integer|gt:0',
        'name'    => 'require|max:100',
        'code'    => 'require|max:50|regex:/^[A-Za-z0-9_]+$/',
        'channel' => 'require|integer|in:1,2,3',
        'content' => 'require',
    ];

    protected $message = [
        'id.require'      => 'id 不能为空',
        'name.require'    => '模板名称不能为空',
        'name.max'        => '模板名称最多 100 个字符',
        'code.require'    => '模板标识不能为空',
        'code.max'        => '模板标识最多 50 个字符',
        'code.regex'      => '模板标识只能包含字母、数字、下划线',
        'channel.require' => '渠道不能为空',
        'channel.in'      => '渠道值无效（1短信 2邮件 3推送）',
        'content.require' => '模板内容不能为空',
    ];

    protected $scene = [
        'add'  => ['name', 'code', 'channel', 'content'],
        'edit' => ['id', 'name', 'code', 'channel', 'content'],
    ];
}
