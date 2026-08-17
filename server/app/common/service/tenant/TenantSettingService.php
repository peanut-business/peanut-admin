<?php
declare(strict_types=1);

namespace app\common\service\tenant;

use app\common\service\member\AuthenticatedMemberContext;
use app\common\service\member\MemberTenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use think\facade\Db;

final class TenantSettingService
{
    public static function document(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $namespace,
        array $default = []
    ): array {
        $tenantId = MemberTenantContext::tenantId($context);
        self::assertNamespace($namespace);
        $raw = Db::name('tenant_setting')
            ->where('tenant_id', $tenantId)
            ->where('namespace', $namespace)
            ->value('config_json');
        if ($raw === null || $raw === '') {
            return $default;
        }
        $decoded = is_array($raw) ? $raw : json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('租户设置数据无效');
        }
        return array_replace_recursive($default, $decoded);
    }

    public static function replace(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $namespace,
        array $document
    ): void {
        $tenantId = MemberTenantContext::tenantId($context);
        self::assertNamespace($namespace);
        $encoded = json_encode(
            $document,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        Db::transaction(static function () use ($tenantId, $namespace, $encoded): void {
            $row = Db::name('tenant_setting')
                ->where('tenant_id', $tenantId)
                ->where('namespace', $namespace)
                ->lock(true)
                ->find();
            $now = time();
            if (empty($row)) {
                Db::name('tenant_setting')->insert([
                    'tenant_id' => $tenantId,
                    'namespace' => $namespace,
                    'config_json' => $encoded,
                    'revision' => 1,
                    'create_time' => $now,
                    'update_time' => $now,
                ]);
                return;
            }
            Db::name('tenant_setting')
                ->where('id', (int)$row['id'])
                ->where('tenant_id', $tenantId)
                ->update([
                    'config_json' => $encoded,
                    'revision' => (int)$row['revision'] + 1,
                    'update_time' => $now,
                ]);
        });
    }

    private static function assertNamespace(string $namespace): void
    {
        if (preg_match('/^[a-z][a-z0-9.-]{0,63}$/D', $namespace) !== 1) {
            throw new \InvalidArgumentException('租户设置命名空间无效');
        }
    }
}
