<?php
declare(strict_types=1);

namespace app\common\model\article;

use app\common\model\BaseModel;
use think\model\concern\SoftDelete;

class ArticleCate extends BaseModel
{
    use SoftDelete;
    protected $name       = 'article_cate';
    protected $deleteTime = 'delete_time';
}
