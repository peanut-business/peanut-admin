<?php
declare(strict_types=1);

namespace app\common\model\article;

use app\common\model\BaseModel;
use app\common\service\FileService;
use think\model\concern\SoftDelete;

class Article extends BaseModel
{
    use SoftDelete;
    protected $name       = 'article';
    protected $deleteTime = 'delete_time';

    /** 关联分类 */
    public function cate()
    {
        return $this->belongsTo(ArticleCate::class, 'cate_id', 'id');
    }

    /** 封面图访问 URL */
    public function getImageAttr($value): string
    {
        return $value ? FileService::getFileUrl($value) : '';
    }

    /** 封面图存相对 uri */
    public function setImageAttr($value): string
    {
        return $value ? FileService::setFileUrl($value) : '';
    }
}
