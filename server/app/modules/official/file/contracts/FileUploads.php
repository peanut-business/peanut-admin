<?php
declare(strict_types=1);

namespace app\modules\official\file\contracts;

use app\common\enum\fileEnum;
use PeanutAdmin\Kernel\Context\AuthenticatedMemberContext;
use app\modules\official\file\contracts\dto\UploadFile;
use PeanutAdmin\Kernel\Auth\TenantContext;

interface FileUploads
{
    public function image(AuthenticatedMemberContext|TenantContext $context, UploadFile $uploaded, int $cid, int $sourceId = 0, int $source = FileEnum::SOURCE_ADMIN): array;

    public function video(AuthenticatedMemberContext|TenantContext $context, UploadFile $uploaded, int $cid, int $sourceId = 0, int $source = FileEnum::SOURCE_ADMIN): array;

    public function file(AuthenticatedMemberContext|TenantContext $context, UploadFile $uploaded, int $cid, int $sourceId = 0, int $source = FileEnum::SOURCE_ADMIN): array;
}
