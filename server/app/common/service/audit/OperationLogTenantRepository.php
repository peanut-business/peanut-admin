<?php
declare(strict_types=1);

namespace app\common\service\audit;

use app\common\model\log\OperationLog;

final class OperationLogTenantRepository
{
    public static function query()
    {
        return OperationLog::where([]);
    }

    public static function createForTenant(int $tenantId, string $requestId, array $data): void
    {
        if ($tenantId < 1 || trim($requestId) === '') {
            throw new \InvalidArgumentException('AUDIT_OPERATION_LOG_OWNER_INVALID');
        }
        OperationLog::create([
            'tenant_id' => $tenantId,
            'admin_id' => (int)($data['admin_id'] ?? 0),
            'username' => (string)($data['username'] ?? ''),
            'ip' => (string)($data['ip'] ?? ''),
            'uri' => (string)($data['uri'] ?? ''),
            'method' => (string)($data['method'] ?? ''),
            'request_id' => trim($requestId),
            'params' => (string)($data['params'] ?? ''),
            'create_time' => time(),
        ]);
    }

    public static function detail(int $id): array
    {
        $row = self::query()->where('id', $id)->find();
        if ($row === null) {
            throw new \InvalidArgumentException('操作日志不存在');
        }
        return $row->toArray();
    }
}
