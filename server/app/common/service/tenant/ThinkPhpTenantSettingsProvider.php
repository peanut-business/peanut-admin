<?php
declare(strict_types=1);

namespace app\common\service\tenant;

use app\common\contract\tenant\TenantSettingSnapshot;
use app\common\contract\tenant\TenantSettingsProvider;
use think\facade\Db;

final class ThinkPhpTenantSettingsProvider implements TenantSettingsProvider
{
    public function find(int $tenantId, string $namespace): ?TenantSettingSnapshot
    {
        $row = Db::name('tenant_setting')
            ->where('tenant_id', $tenantId)
            ->where('namespace', $namespace)
            ->find();
        return is_array($row) ? $this->snapshot($row) : null;
    }

    public function replace(int $tenantId, string $namespace, array $document): TenantSettingSnapshot
    {
        return Db::transaction(
            fn(): TenantSettingSnapshot => $this->replaceLocked($tenantId, $namespace, $document),
        );
    }

    private function replaceLocked(int $tenantId, string $namespace, array $document): TenantSettingSnapshot
    {
        $encoded = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $row = Db::name('tenant_setting')->where('tenant_id', $tenantId)->where('namespace', $namespace)->lock(true)->find();
        $now = time();
        if (!is_array($row)) {
            Db::name('tenant_setting')->insert([
                'tenant_id' => $tenantId, 'namespace' => $namespace, 'config_json' => $encoded,
                'revision' => 1, 'create_time' => $now, 'update_time' => $now,
            ]);
            return new TenantSettingSnapshot($tenantId, $namespace, $document, 1, $now, $now);
        }
        $revision = (int)$row['revision'] + 1;
        Db::name('tenant_setting')->where('id', (int)$row['id'])->where('tenant_id', $tenantId)->where('namespace', $namespace)->update([
            'config_json' => $encoded, 'revision' => $revision, 'update_time' => $now,
        ]);
        return new TenantSettingSnapshot($tenantId, $namespace, $document, $revision, (int)$row['create_time'], $now);
    }

    /** @param array<string, mixed> $row */
    private function snapshot(array $row): TenantSettingSnapshot
    {
        $document = json_decode((string)($row['config_json'] ?? ''), true);
        if (!is_array($document)) {
            throw new \RuntimeException('租户设置数据无效');
        }
        return new TenantSettingSnapshot((int)$row['tenant_id'], (string)$row['namespace'], $document, (int)$row['revision'], (int)$row['create_time'], (int)$row['update_time']);
    }
}
