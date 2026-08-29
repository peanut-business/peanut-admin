<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Application;

use app\common\dto\authorization\AdminPrincipal;
use app\common\service\authorization\AdminAuthorizationService;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;

/** Tenant Admin application boundary for configuration package operations. */
final readonly class TenantConfigurationTransferService
{
    private AdminAuthorizationService $authorization;
    private ConfigurationTransferApplicationService $transfers;

    public function __construct(PDO $pdo)
    {
        $this->authorization = new AdminAuthorizationService($pdo);
        $this->transfers = new ConfigurationTransferApplicationService($pdo);
    }

    /** @return array<string,mixed> */
    public function export(TenantContext $context, AdminPrincipal $principal): array
    {
        $this->authorize($context, $principal, 'official.import-export.configuration.export');
        return $this->transfers->export($context, 'tenant');
    }

    /** @return array<string,mixed> */
    public function dryRun(
        TenantContext $context,
        AdminPrincipal $principal,
        array|string $package,
        array $secretBindings,
        string $conflictPolicy,
    ): array {
        $this->authorize($context, $principal, 'official.import-export.configuration.dry-run');
        return $this->transfers->dryRun(
            $context,
            'tenant',
            $package,
            $secretBindings,
            $conflictPolicy,
        );
    }

    /** @return array<string,mixed> */
    public function apply(
        TenantContext $context,
        AdminPrincipal $principal,
        array|string $package,
        array $secretBindings,
        string $conflictPolicy,
    ): array {
        $this->authorize($context, $principal, 'official.import-export.configuration.apply');
        return $this->transfers->apply(
            $context,
            'tenant',
            $package,
            $secretBindings,
            $conflictPolicy,
        );
    }

    private function authorize(TenantContext $context, AdminPrincipal $principal, string $permission): void
    {
        if (!$this->authorization->decide($context, $principal, $permission)->allowed) {
            throw new \RuntimeException('TRANSFER_PERMISSION_DENIED');
        }
    }
}
