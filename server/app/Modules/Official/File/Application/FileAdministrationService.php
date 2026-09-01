<?php
declare(strict_types=1);

namespace app\Modules\Official\File\Application;

use app\Modules\Official\File\Contracts\FileAdministration;
use app\common\enum\FileEnum;
use app\common\execution\ExecutionContextAccess;
use app\common\http\PageResult;
use app\common\service\FileService;
use app\Modules\Official\File\Infrastructure\Persistence\FileTenantRepository;
use app\common\service\storage\StorageService;
use app\common\support\PaginationInput;
use app\common\support\PositiveIds;
use PeanutAdmin\Kernel\Persistence\TransactionManager;

/** Application use cases for File media and categories. */
final class FileAdministrationService implements FileAdministration
{
    public function __construct(
        private readonly TransactionManager $transactions,
        private readonly StorageService $storage,
        private readonly ExecutionContextAccess $contexts,
        private readonly FileService $files,
    ) {}

    /** 分页列表：按 type / 分类子树 / source / name 组合过滤，追加 url。 */
    public function lists(array $params): PageResult
    {
        $type = $this->integerValue($params['type'] ?? null, '文件类型无效');
        if (!FileEnum::isValidType($type)) {
            throw new \InvalidArgumentException('文件类型无效');
        }

        $where = [['type', '=', $type]];
        $categoryIds = null;
        if (array_key_exists('cid', $params) && $params['cid'] !== '') {
            $cid = $this->integerValue($params['cid'], '文件分类无效');
            if ($cid < 0) {
                throw new \InvalidArgumentException('文件分类无效');
            }
            $categoryIds = $cid === 0 ? [0] : $this->subtreeIds($cid, $type);
        }
        if (array_key_exists('source', $params) && $params['source'] !== '') {
            $source = $this->integerValue($params['source'], '上传来源无效');
            if (!in_array($source, [FileEnum::SOURCE_ADMIN, FileEnum::SOURCE_USER], true)) {
                throw new \InvalidArgumentException('上传来源无效');
            }
            $where[] = ['source', '=', $source];
        }
        if (!empty($params['name'])) {
            $where[] = ['name', 'like', '%' . trim((string) $params['name']) . '%'];
        }

        $pagination = PaginationInput::from($params);
        $query = FileTenantRepository::files()->where($where);
        if ($categoryIds !== null) {
            $query->whereIn('cid', $categoryIds);
        }

        $pageResult = $pagination->result($query->order(['id' => 'desc']));
        $pageResult = FileTenantRepository::arrayPage($pageResult);
        $lists = $pageResult->items;
        foreach ($lists as &$item) {
            $item['url'] = $this->files->getFileUrl((string) ($item['file_key'] ?? ''));
        }
        unset($item);

        return new PageResult($lists, $pageResult->total, $pageResult->page, $pageResult->pageSize);
    }

    /** 批量移动到分类。 */
    public function move(array $ids, int $cid): void
    {
        $ids = $this->normalizeIds($ids);
        $rows = FileTenantRepository::files()->whereIn('id', $ids)->select()->toArray();
        if (count($rows) !== count($ids)) {
            throw new \InvalidArgumentException('包含不存在的素材');
        }

        if ($cid < 0) {
            throw new \InvalidArgumentException('目标分类无效');
        }
        if ($cid > 0) {
            $category = FileTenantRepository::findCategory($cid);
            if (!$category) {
                throw new \InvalidArgumentException('目标分类不存在');
            }
            $categoryType = (int) $category->type;
            foreach ($rows as $row) {
                if ((int) $row['type'] !== $categoryType) {
                    throw new \InvalidArgumentException('素材类型与目标分类不一致');
                }
            }
        }

        FileTenantRepository::files()->whereIn('id', $ids)->update(['cid' => $cid]);
    }

    /** 重命名。 */
    public function rename(int $id, string $name): void
    {
        $name = trim($name);
        if ($id <= 0 || $name === '') {
            throw new \InvalidArgumentException($id <= 0 ? '素材 ID 无效' : '名称不能为空');
        }
        if (mb_strlen($name) > 20) {
            throw new \InvalidArgumentException('名称最多 20 个字符');
        }
        $file = FileTenantRepository::findFile($id);
        if (!$file) {
            throw new \InvalidArgumentException('素材不存在');
        }
        $file->save(['name' => $name]);
    }

    /** 批量删除素材及对应存储对象。 */
    public function delete(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        $rows = FileTenantRepository::files()->whereIn('id', $ids)->order(['id' => 'asc'])->select();
        if ($rows->count() !== count($ids)) {
            throw new \InvalidArgumentException('包含不存在的素材');
        }

        $deleted = FileTenantRepository::files()
            ->whereIn('id', $ids)
            ->update(['delete_time' => time()]);
        if ($deleted !== count($ids)) {
            throw new \RuntimeException('素材记录删除失败');
        }

        $tenantId = $this->contexts->tenantId();
        $storageDeleted = 0;
        foreach ($rows as $row) {
            $fileId = (int) $row['id'];
            $fileKey = (string) $row['file_key'];
            try {
                $this->storage->delete($tenantId, $fileKey);
                $storageDeleted++;
            } catch (\Throwable $e) {
                throw new \RuntimeException('素材 ' . $fileId . ' 删除失败：' . $e->getMessage(), 0, $e);
            }
        }

        return [
            'files_deleted' => $deleted,
            'storage_deleted' => $storageDeleted,
        ];
    }

    /** 某类型下的稳定分类树（同级按 id 升序）。 */
    public function categoryLists(int $type): array
    {
        if (!FileEnum::isValidType($type)) {
            throw new \InvalidArgumentException('文件类型无效');
        }

        $categories = FileTenantRepository::categories()
            ->where('type', $type)
            ->order(['id' => 'asc'])
            ->select()
            ->toArray();
        return linear_to_tree($categories);
    }

    public function addCategory(array $params): void
    {
        $name = trim((string) ($params['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('分类名称不能为空');
        }
        $type = (int) ($params['type'] ?? 0);
        if (!FileEnum::isValidType($type)) {
            throw new \InvalidArgumentException('文件类型无效');
        }
        $pid = (int) ($params['pid'] ?? 0);
        if ($pid < 0) {
            throw new \InvalidArgumentException('父分类无效');
        }
        if ($pid > 0) {
            $parent = FileTenantRepository::findCategory($pid);
            if (!$parent) {
                throw new \InvalidArgumentException('父分类不存在');
            }
            if ((int) $parent->type !== $type) {
                throw new \InvalidArgumentException('父分类类型不一致');
            }
        }

        FileTenantRepository::createCategory([
            'pid' => $pid,
            'type' => $type,
            'name' => $name,
        ]);
    }

    public function editCategory(array $params): void
    {
        $name = trim((string) ($params['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('分类名称不能为空');
        }
        $category = FileTenantRepository::findCategory((int) ($params['id'] ?? 0));
        if (!$category) {
            throw new \InvalidArgumentException('分类不存在');
        }

        $category->save(['name' => $name]);
    }

    /** 删除分类子树、其中素材及存储对象，并返回三者结果。 */
    public function deleteCategory(int $id): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('分类 ID 无效');
        }
        $root = FileTenantRepository::findCategory($id);
        if (!$root) {
            throw new \InvalidArgumentException('分类不存在');
        }
        $categoryIds = $this->subtreeIds($id, (int) $root->type);
        $fileIds = array_map(
            'intval',
            FileTenantRepository::files()->whereIn('cid', $categoryIds)->column('id'),
        );
        $fileResult = $fileIds === []
            ? ['files_deleted' => 0, 'storage_deleted' => 0]
            : $this->delete($fileIds);

        $this->transactions->run(function () use ($categoryIds): void {
            $query = FileTenantRepository::categories()->whereIn('id', $categoryIds);
            if ($query->count() !== count($categoryIds)) {
                throw new \RuntimeException('分类记录删除不完整');
            }
            if ($query->update(['delete_time' => time()]) !== count($categoryIds)) {
                throw new \RuntimeException('分类记录删除失败');
            }
        });

        return [
            'categories_deleted' => count($categoryIds),
            'files_deleted' => $fileResult['files_deleted'],
            'storage_deleted' => $fileResult['storage_deleted'],
        ];
    }

    /** 校验根分类类型并返回包含自身的稳定子树 ID。 */
    private function subtreeIds(int $id, int $type): array
    {
        if (!FileEnum::isValidType($type)) {
            throw new \InvalidArgumentException('文件类型无效');
        }

        $categories = FileTenantRepository::categories()
            ->order(['id' => 'asc'])
            ->field(['id', 'pid', 'type'])
            ->select()
            ->toArray();
        $children = [];
        $exists = false;
        foreach ($categories as $category) {
            $categoryId = (int) $category['id'];
            $parentId = (int) $category['pid'];
            $children[$parentId][] = $category;
            $exists = $exists || ($categoryId === $id && (int) $category['type'] === $type);
        }
        if (!$exists) {
            throw new \InvalidArgumentException('分类不存在或类型不一致');
        }

        $result = [];
        $queue = [$id];
        while ($queue) {
            $current = array_shift($queue);
            if (in_array($current, $result, true)) {
                throw new \RuntimeException('分类子树存在循环关系');
            }
            $result[] = $current;
            foreach ($children[$current] ?? [] as $child) {
                if ((int) $child['type'] !== $type) {
                    throw new \RuntimeException('分类子树存在跨类型关系');
                }
                $queue[] = (int) $child['id'];
            }
        }
        return $result;
    }

    private function normalizeIds(array $ids): array
    {
        return PositiveIds::normalize(
            $ids,
            [PositiveIds::REJECT_INVALID, PositiveIds::REQUIRE_NON_EMPTY],
            '素材 ID 无效',
            '素材 ID 集合不能为空',
        );
    }

    private function integerValue(mixed $value, string $message): int
    {
        if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/D', $value) === 1)) {
            throw new \InvalidArgumentException($message);
        }
        return (int) $value;
    }
}
