<?php
declare(strict_types=1);

namespace app\adminapi\validate\article;

use think\Validate;

class ArticleValidate extends Validate
{
    protected $rule = [
        'id'      => 'require|integer|gt:0',
        'cate_id' => 'require|integer|gt:0',
        'title'   => 'require|max:100',
    ];

    protected $message = [
        'id.require'      => 'id 不能为空',
        'cate_id.require' => '请选择文章分类',
        'title.require'   => '文章标题不能为空',
        'title.max'       => '文章标题最多 100 个字符',
    ];

    protected $scene = [
        'add'    => ['cate_id', 'title'],
        'edit'   => ['id', 'cate_id', 'title'],
        'delete' => ['id'],
        'detail' => ['id'],
    ];
}
