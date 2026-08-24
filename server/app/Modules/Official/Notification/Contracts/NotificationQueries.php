<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Contracts;

use PeanutAdmin\Kernel\Auth\TenantContext;

interface NotificationQueries
{
    public function channelDetail(TenantContext $context): array;

    public function scenes(TenantContext $context): array;

    public function sceneDetail(TenantContext $context, int $id): array;

    public function sceneExists(TenantContext $context, int $id): bool;

    /** @param array<string,mixed> $params */
    public function logs(TenantContext $context, array $params): array;

    public function logDetail(TenantContext $context, int $id): array;
}
