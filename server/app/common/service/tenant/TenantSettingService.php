<?php
declare(strict_types=1);

namespace app\common\service\tenant;

use app\common\contract\tenant\TenantSettingSnapshot;
use app\common\contract\tenant\TenantSettingsCommands;
use app\common\contract\tenant\TenantSettingsProvider;
use app\common\contract\tenant\TenantSettingsQuery;
use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use think\facade\Db;

final readonly class TenantSettingService implements TenantSettingsQuery, TenantSettingsCommands
{
    public function __construct(private TenantSettingsProvider $provider)
    {
    }

    public function get(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $namespace,
        array $default = []
    ): TenantSettingSnapshot {
        $tenantId = $context->tenantId;
        TenantSettingsNamespace::assertValid($namespace);
        $snapshot = $this->provider->find($tenantId, $namespace);
        if ($snapshot === null) {
            return new TenantSettingSnapshot($tenantId, $namespace, $default, 0, 0, 0);
        }
        return new TenantSettingSnapshot($snapshot->tenantId, $snapshot->namespace, array_replace_recursive($default, $snapshot->document), $snapshot->revision, $snapshot->createTime, $snapshot->updateTime);
    }

    public function replace(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $namespace,
        array $document
    ): TenantSettingSnapshot {
        $tenantId = $context->tenantId;
        TenantSettingsNamespace::assertValid($namespace);
        return Db::transaction(
            fn(): TenantSettingSnapshot => $this->provider->replace($tenantId, $namespace, $document),
        );
    }
}
