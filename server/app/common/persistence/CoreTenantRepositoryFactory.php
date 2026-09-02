<?php
declare(strict_types=1);

namespace app\common\persistence;

use app\common\service\instance\DeploymentMode;
use PDO;
use PeanutAdmin\ImportExport\Persistence\PdoImportExportRepository;
use PeanutAdmin\Kernel\Idempotency\PdoIdempotencyRepository;
use PeanutAdmin\Kernel\Persistence\Tenancy\TenantPersistenceMode;
use PeanutAdmin\Kernel\Tenancy\DefaultTenantContextResolver;
use PeanutAdmin\Settings\Persistence\PdoSettingRepository;
use PeanutAdmin\TaskJob\Persistence\PdoTaskJobRepository;

/** The application composition root for Core repositories with Edition-shaped tenant storage. */
final readonly class CoreTenantRepositoryFactory
{
    private TenantPersistenceMode $mode;
    private ?int $instanceTenantId;

    public function __construct(private PDO $pdo)
    {
        $deploymentMode = DeploymentMode::fromConfiguredValue(getenv('DEPLOYMENT_MODE'));
        if (!$deploymentMode instanceof DeploymentMode) {
            throw new \RuntimeException('TENANT_PERSISTENCE_DEPLOYMENT_MODE_INVALID');
        }
        $this->mode = $deploymentMode === DeploymentMode::Standalone
            ? TenantPersistenceMode::InstanceScoped
            : TenantPersistenceMode::TenantScoped;
        $this->instanceTenantId = $deploymentMode === DeploymentMode::Standalone
            ? $this->resolveInstanceTenantId()
            : null;
    }

    public function settings(): PdoSettingRepository
    {
        return new PdoSettingRepository($this->pdo, $this->mode, $this->instanceTenantId);
    }

    public function taskJobs(): PdoTaskJobRepository
    {
        return new PdoTaskJobRepository($this->pdo, $this->mode, $this->instanceTenantId);
    }

    public function importExport(): PdoImportExportRepository
    {
        return new PdoImportExportRepository($this->pdo, $this->mode, $this->instanceTenantId);
    }

    public function idempotency(): PdoIdempotencyRepository
    {
        return new PdoIdempotencyRepository($this->pdo, $this->mode, $this->instanceTenantId);
    }

    private function resolveInstanceTenantId(): int
    {
        try {
            return (new DefaultTenantContextResolver($this->pdo))
                ->system('peanut-admin', 'resolve-instance-tenant', 'core-tenant-persistence')
                ->tenantId;
        } catch (\Throwable $exception) {
            throw new \RuntimeException('TENANT_PERSISTENCE_INSTANCE_TENANT_UNAVAILABLE', 0, $exception);
        }
    }
}
