<?php
declare(strict_types=1);

namespace app\common\persistence;

use PDO;
use PeanutAdmin\ImportExport\Persistence\PdoImportExportRepository;
use PeanutAdmin\Kernel\Idempotency\PdoIdempotencyRepository;
use PeanutAdmin\Kernel\Persistence\Tenancy\TenantPersistenceMode;
use PeanutAdmin\Settings\Persistence\PdoSettingRepository;
use PeanutAdmin\TaskJob\Persistence\PdoTaskJobRepository;

/** The application composition root for Core repositories with Edition-shaped tenant storage. */
final readonly class CoreTenantRepositoryFactory
{
    private TenantPersistenceMode $mode;
    private ?int $instanceTenantId;

    public function __construct(private PDO $pdo)
    {
        // The canonical fresh Schema always stores tenant_id, including standalone deployments.
        $this->mode = TenantPersistenceMode::TenantScoped;
        $this->instanceTenantId = null;
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
}
