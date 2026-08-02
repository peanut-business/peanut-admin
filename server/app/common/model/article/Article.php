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
        return $this->belongsTo(ArticleCate::class, 'cid', 'id');
    }

    /** 管理端与公共端统一浏览量口径。 */
    public function getClickAttr($value, array $data): int
    {
        return (int) ($data['click_actual'] ?? 0) + (int) ($data['click_virtual'] ?? 0);
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

    /** 富文本内相对图片、视频地址补全当前文件域名。 */
    public function getContentAttr($value): string
    {
        if (empty($value)) {
            return (string) $value;
        }
        return (string) preg_replace_callback(
            '/(<(?:img|video)\b[^>]*?\bsrc=["\'])(?!https?:\/\/)([^"\']+)(["\'][^>]*>)/is',
            static fn(array $matches): string => $matches[1] . FileService::getFileUrl($matches[2]) . $matches[3],
            (string) $value
        );
    }

    /** 富文本入库时去除当前存储引擎域名。 */
    public function setContentAttr($value): string
    {
        if (empty($value)) {
            return (string) $value;
        }
        return (string) preg_replace_callback(
            '/(<(?:img|video)\b[^>]*?\bsrc=["\'])(https?:\/\/[^"\']+)(["\'][^>]*>)/is',
            static fn(array $matches): string => $matches[1] . FileService::setFileUrl($matches[2]) . $matches[3],
            (string) $value
        );
    }

    /** 可见文章详情；读取即累计一次真实浏览量。 */
    public static function getArticleDetailArr(int $id): array
    {
        $article = self::where(['id' => $id, 'is_show' => 1])->findOrEmpty();
        if ($article->isEmpty()) {
            return [];
        }

        $article->click_actual = (int) $article->click_actual + 1;
        $article->save();
        $data = $article->toArray();
        $data['click'] = (int) $data['click_actual'] + (int) $data['click_virtual'];
        unset($data['click_actual'], $data['click_virtual']);
        return $data;
    }
}
