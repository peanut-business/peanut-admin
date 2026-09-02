<?php
declare(strict_types=1);

namespace app\Modules\Official\File\Infrastructure\Persistence;

use app\Modules\Official\File\Model\File;
use app\Modules\Official\File\Model\FileCate;
use app\common\execution\CurrentExecutionContext;
use app\common\persistence\ConvertsModelPage;

final class FileTenantRepository
{
    use ConvertsModelPage;

    public static function files()
    {
        return File::where([]);
    }

    public static function categories()
    {
        return FileCate::where([]);
    }

    public static function findFile(int $id): ?File
    {
        return self::files()->where('id', $id)->find();
    }

    public static function findCategory(int $id): ?FileCate
    {
        return self::categories()->where('id', $id)->find();
    }

    public static function createFile(array $data): File
    {
        unset($data['tenant_id']);
        if (preg_match('/^file_[0-9a-f]{32}$/D', (string)($data['file_key'] ?? '')) !== 1) {
            throw new \RuntimeException('文件对象身份无效');
        }
        return File::create($data);
    }

    public static function tenantId(CurrentExecutionContext $executionContext): int
    {
        return $executionContext->tenantId();
    }

    public static function createCategory(array $data): FileCate
    {
        unset($data['tenant_id']);
        return FileCate::create($data);
    }
}
