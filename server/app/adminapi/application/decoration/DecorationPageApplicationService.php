<?php
declare(strict_types=1);

namespace app\adminapi\application\decoration;

use app\common\application\ApplicationService;
use app\common\service\ProductAssetReferenceService;
use app\common\service\article\ArticleTenantRepository;
use app\common\service\decoration\DecorationReadService;
use app\common\service\decoration\DecorationSchemaService;
use app\common\service\decoration\DecorationTenantRepository;
use app\common\persistence\TransactionalExecution;
use PeanutAdmin\Kernel\Auth\TenantContext;

class DecorationPageApplicationService extends ApplicationService
{
    public function __construct(private readonly TransactionalExecution $transactions)
    {
    }

    public function lists(TenantContext $context, array $allowedTypes): array
    {
        self::clearError();
        return DecorationTenantRepository::pages()
            ->field(['id', 'type', 'name', 'update_time'])
            ->whereIn('type', $allowedTypes)->order('type', 'asc')->select()->toArray();
    }

    public function detail(TenantContext $context, int $id, array $allowedTypes): array|false
    {
        self::clearError();
        try {
            $page = DecorationTenantRepository::pages()->where('id', $id)->findOrEmpty();
            if ($page->isEmpty() || !in_array((int)$page->type, $allowedTypes, true)) {
                throw new \RuntimeException('装修页面不存在或无权访问');
            }
            return DecorationReadService::formatPage($page->toArray());
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public function detailByType(TenantContext $context, int $type): array|false
    {
        self::clearError();
        try {
            return DecorationReadService::pageByType($context, $type);
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public function save(TenantContext $context, array $params, array $allowedTypes): bool
    {
        self::clearError();
        try {
            $type = (int)$params['type'];
            if (!in_array($type, $allowedTypes, true)) {
                throw new \RuntimeException('无权保存该装修页面');
            }
            $data = $params['data'];
            $meta = $params['meta'] ?? [];
            DecorationSchemaService::validatePage($context, $type, $data, $meta);

            $this->transactions->run(function () use ($context, $params, $type, $data, $meta): void {
                $page = DecorationTenantRepository::pages()
                    ->where('id', (int)$params['id'])->lock(true)->findOrEmpty();
                if ($page->isEmpty()) {
                    throw new \RuntimeException('装修页面不存在');
                }
                if ((int)$page->type !== $type) {
                    throw new \RuntimeException('装修页面类型不可修改');
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
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public function articleOptions(TenantContext $context, int $limit): array
    {
        self::clearError();
        $rows = ArticleTenantRepository::articles()->field(['id', 'title', 'image', 'abstract'])
            ->where('is_show', 1)->order('id', 'desc')->limit($limit)
            ->select()->toArray();
        foreach ($rows as &$row) {
            $row['image'] = ProductAssetReferenceService::forRead((string)($row['image'] ?? ''));
        }
        unset($row);
        return $rows;
    }

}
