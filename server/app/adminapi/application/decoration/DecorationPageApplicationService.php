<?php
declare(strict_types=1);

namespace app\adminapi\application\decoration;

use app\common\application\BusinessException;
use app\common\service\ProductAssetReferenceService;
use app\Modules\Official\Article\Contracts\ArticleQueries;
use app\common\service\decoration\DecorationReadService;
use app\common\service\decoration\DecorationSchemaService;
use app\common\service\decoration\DecorationTenantRepository;
use app\common\persistence\TransactionalExecution;
use PeanutAdmin\Kernel\Auth\TenantContext;

class DecorationPageApplicationService
{
    public function __construct(
        private readonly TransactionalExecution $transactions,
        private readonly ArticleQueries $articles,
    ) {}

    public function lists(TenantContext $context, array $allowedTypes): array
    {
        return DecorationTenantRepository::pages()
            ->field(['id', 'type', 'name', 'update_time'])
            ->whereIn('type', $allowedTypes)->order('type', 'asc')->select()->toArray();
    }

    public function detail(TenantContext $context, int $id, array $allowedTypes): array
    {
        $page = DecorationTenantRepository::pages()->where('id', $id)->findOrEmpty();
        if ($page->isEmpty() || !in_array((int)$page->type, $allowedTypes, true)) {
            throw BusinessException::notFound('DECORATION_PAGE_NOT_FOUND', '装修页面不存在或无权访问');
        }
        return DecorationReadService::formatPage($page->toArray());
    }

    public function detailByType(TenantContext $context, int $type): array
    {
        return DecorationReadService::pageByType($context, $type);
    }

    public function save(TenantContext $context, array $params, array $allowedTypes): bool
    {
        $type = (int)$params['type'];
        if (!in_array($type, $allowedTypes, true)) {
            throw BusinessException::forbidden('DECORATION_PAGE_WRITE_FORBIDDEN', '无权保存该装修页面');
        }
        try {
            $data = $params['data'];
            $meta = $params['meta'] ?? [];
            DecorationSchemaService::validatePage($context, $type, $data, $meta, $this->articles);
        } catch (\RuntimeException $exception) {
            throw BusinessException::invalid('DECORATION_PAGE_INVALID', $exception->getMessage());
        }
        $this->transactions->run(function () use ($context, $params, $type, $data, $meta): void {
                $page = DecorationTenantRepository::pages()
                    ->where('id', (int)$params['id'])->lock(true)->findOrEmpty();
                if ($page->isEmpty()) {
                    throw BusinessException::notFound('DECORATION_PAGE_NOT_FOUND', '装修页面不存在');
                }
                if ((int)$page->type !== $type) {
                    throw BusinessException::conflict('DECORATION_PAGE_TYPE_IMMUTABLE', '装修页面类型不可修改');
                }
                $page->data = json_encode(
                    DecorationSchemaService::resourcesForStorage($data, $context),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                );
                $page->meta = json_encode(
                    DecorationSchemaService::resourcesForStorage($meta, $context),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                );
                $page->save();
        });
        return true;
    }

    public function articleOptions(TenantContext $context, int $limit): array
    {
        $rows = $this->articles->options($context, $limit);
        foreach ($rows as &$row) {
            $row['image'] = ProductAssetReferenceService::forRead((string)($row['image'] ?? ''));
        }
        unset($row);
        return $rows;
    }

}
