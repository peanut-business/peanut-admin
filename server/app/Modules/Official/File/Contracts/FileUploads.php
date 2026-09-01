<?php
declare(strict_types=1);

namespace app\Modules\Official\File\Contracts;

use app\common\enum\FileEnum;
use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\file\UploadedFile;

interface FileUploads
{
    public function image(AuthenticatedMemberContext|TenantContext $context, UploadedFile $uploaded, int $cid, int $sourceId = 0, int $source = FileEnum::SOURCE_ADMIN): array;

    public function video(AuthenticatedMemberContext|TenantContext $context, UploadedFile $uploaded, int $cid, int $sourceId = 0, int $source = FileEnum::SOURCE_ADMIN): array;

    public function file(AuthenticatedMemberContext|TenantContext $context, UploadedFile $uploaded, int $cid, int $sourceId = 0, int $source = FileEnum::SOURCE_ADMIN): array;
}
