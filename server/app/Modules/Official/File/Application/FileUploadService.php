<?php
declare(strict_types=1);

namespace app\Modules\Official\File\Application;

use app\Modules\Official\File\Contracts\FileUploads;
use app\Modules\Official\File\Contracts\Dto\UploadFile;
use app\Modules\Official\File\Infrastructure\Persistence\FileTenantRepository;
use app\common\enum\FileEnum;
use app\common\execution\CurrentExecutionContext;
use PeanutAdmin\Kernel\Context\AuthenticatedMemberContext;
use app\common\service\storage\StorageService;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class FileUploadService implements FileUploads
{
    public function __construct(
        private readonly StorageService $storage,
        private readonly CurrentExecutionContext $executionContext,
    ) {}

    public function image(AuthenticatedMemberContext|TenantContext $context, UploadFile $uploaded, int $cid, int $sourceId = 0, int $source = FileEnum::SOURCE_ADMIN): array
    {
        return $this->save($context, $uploaded, FileEnum::IMAGE, $cid, $sourceId, $source);
    }

    public function video(AuthenticatedMemberContext|TenantContext $context, UploadFile $uploaded, int $cid, int $sourceId = 0, int $source = FileEnum::SOURCE_ADMIN): array
    {
        return $this->save($context, $uploaded, FileEnum::VIDEO, $cid, $sourceId, $source);
    }

    public function file(AuthenticatedMemberContext|TenantContext $context, UploadFile $uploaded, int $cid, int $sourceId = 0, int $source = FileEnum::SOURCE_ADMIN): array
    {
        return $this->save($context, $uploaded, FileEnum::FILE, $cid, $sourceId, $source);
    }

    private function save(AuthenticatedMemberContext|TenantContext $context, UploadFile $uploaded, int $type, int $cid, int $sourceId, int $source): array
    {
        if (!FileEnum::isValidType($type)) {
            throw new \InvalidArgumentException('文件类型无效');
        }
        if (!in_array($source, [FileEnum::SOURCE_ADMIN, FileEnum::SOURCE_USER], true)) {
            throw new \InvalidArgumentException('上传来源无效');
        }
        if ($cid < 0) {
            throw new \InvalidArgumentException('目标分类无效');
        }
        if ($cid > 0) {
            $category = FileTenantRepository::findCategory($cid);
            if (!$category) {
                throw new \InvalidArgumentException('目标分类不存在');
            }
            if ((int)$category->type !== $type) {
                throw new \InvalidArgumentException('上传类型与目标分类不一致');
            }
        }

        $ext = strtolower($uploaded->extension);
        if (!in_array($ext, FileEnum::EXT[$type], true)) {
            throw new \Exception('不允许上传 ' . ($ext ?: '未知') . ' 格式文件');
        }
        if ($uploaded->size > FileEnum::MAX_SIZE[$type]) {
            $mb = (int)(FileEnum::MAX_SIZE[$type] / 1024 / 1024);
            throw new \Exception('文件大小超过上限 ' . $mb . 'MB');
        }

        $originName = $uploaded->originalName;
        $name = mb_substr((string)pathinfo($originName, PATHINFO_FILENAME), 0, 120) . '.' . $ext;
        $purpose = match ($type) {
            FileEnum::IMAGE => 'material.image',
            FileEnum::VIDEO => 'material.video',
            FileEnum::FILE => 'material.file',
        };
        $tenantId = $this->executionContext->tenantId();
        if ($context->tenantId !== $tenantId) {
            throw new \DomainException('FILE_UPLOAD_TENANT_CONTEXT_MISMATCH');
        }
        $stored = $this->storage->storePath(
            $tenantId,
            (int)$context->memberId,
            $purpose,
            $uploaded->path,
            $name,
            $uploaded->mediaType !== '' ? $uploaded->mediaType : 'application/octet-stream',
        );

        try {
            $file = FileTenantRepository::createFile([
                'cid' => $cid,
                'source_id' => $sourceId,
                'source' => $source,
                'type' => $type,
                'name' => $name,
                'file_key' => $stored['file_key'],
            ]);
        } catch (\Throwable $exception) {
            $this->storage->delete($tenantId, $stored['file_key']);
            throw $exception;
        }

        return [
            'id' => $file->id,
            'cid' => $file->cid,
            'type' => $file->type,
            'name' => $file->name,
            'file_key' => $stored['file_key'],
            'uri' => $stored['file_key'],
            'url' => $stored['url'],
        ];
    }
}
