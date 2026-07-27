<?php
declare(strict_types=1);

namespace app\adminapi\validate\article;

use think\Validate;

class ArticleCateValidate extends Validate
{
    protected $rule = [
        'id'   => 'require|integer|gt:0',
        'name' => 'require|max:50',
    ];

    protected $message = [
        'id.require'   => 'id 不能为空',
        'name.require' => '分类名称不能为空',
        'name.max'     => '分类名称最多 50 个字符',
    ];

    protected $scene = [
        'add'    => ['name'],
        'edit'   => ['id', 'name'],
        'delete' => ['id'],
        'detail' => ['id'],
    ];
}
