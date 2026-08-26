<?php
declare(strict_types=1);

namespace app\adminapi\service;

use app\common\service\audit\AuditContractHost;
use app\common\service\audit\RedactionPolicy;
use PeanutAdmin\Kernel\Auth\TenantContext;

/** 管理端操作日志的唯一写入与脱敏入口。 */
final class OperationLogService
{
    public static function record(
        TenantContext $context,
        int $adminId,
        string $username,
        string $ip,
        string $uri,
        string $method,
        mixed $params
    ): void {
        AuditContractHost::production()->recordOperationLog(
            $context,
            $adminId,
            $username,
            $ip,
            $uri,
            $method,
            $params,
        );
    }

    public static function serializeParams(mixed $params): string
    {
        return RedactionPolicy::encode($params);
    }

    public static function redactSensitive(mixed $value, string $key = ''): mixed
    {
        return RedactionPolicy::sanitize($value, $key);
    }
}
