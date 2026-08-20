<?php
declare(strict_types=1);

namespace app\common\model\article;

use app\common\model\BaseModel;
use app\common\service\ProductAssetReferenceService;
use app\common\service\RichTextResourceService;
use app\common\service\article\ArticleTenantRepository;
use think\model\concern\SoftDelete;

class Article extends BaseModel
{
    use SoftDelete;
    protected $name       = 'article';
    protected $deleteTime = 'delete_time';

    /** 关联分类 */
    public function cate()
    {
        return $this->belongsTo(ArticleCate::class, 'cid', 'id')
            ->where('tenant_id', (int)$this->tenant_id);
    }

    /** 管理端与公共端统一浏览量口径。 */
    public function getClickAttr($value, array $data): int
    {
        return (int) ($data['click_actual'] ?? 0) + (int) ($data['click_virtual'] ?? 0);
    }

    /** 封面图访问 URL */
    public function getImageAttr($value): string
    {
        return $value ? ProductAssetReferenceService::forRead((string)$value) : '';
    }

    /** local 封面存相对 URI；云/CDN 封面保留绝对来源。 */
    public function setImageAttr($value): string
    {
        return $value ? ProductAssetReferenceService::forStorage((string)$value) : '';
    }

    /** Rich text is sanitized again when historical content is read. */
    public function getContentAttr($value): string
    {
        return RichTextResourceService::forRead((string)$value);
    }

    /** Rich text is sanitized before any content reaches persistence. */
    public function setContentAttr($value): string
    {
        return RichTextResourceService::forStorage((string)$value);
    }

    /** 可见文章详情；读取即累计一次真实浏览量。 */
    public static function getArticleDetailArr(object $context, int $id): array
    {
        $article = ArticleTenantRepository::articles($context)
            ->where(['id' => $id, 'is_show' => 1])->findOrEmpty();
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
