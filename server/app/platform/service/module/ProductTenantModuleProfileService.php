<?php
declare(strict_types=1);

namespace app\platform\service\module;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;
use PeanutAdmin\Kernel\Module\TenantModuleManager;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;

/** Applies explicit application product profiles through the canonical TenantModule runtime. */
final readonly class ProductTenantModuleProfileService
{
    private const PROFILES = [
        'standalone' => [
            'tenant_codes' => ['default'],
            'modules' => [
                'official.file',
                'official.task',
                'official.notification',
                'official.member',
                'official.article',
                'official.payment',
                'official.oauth',
                'official.import-export',
            ],
        ],
        'demo' => [
            'tenant_codes' => ['default', 'tenant-a', 'tenant-b'],
            'modules' => ['official.file', 'official.article', 'official.member'],
        ],
    ];

    /** @param array<string,mixed> $deploymentConfig */
    public function __construct(
        private PDO $pdo,
        private string $serverRoot,
        private array $deploymentConfig
    ) {
    }

    /** @return array{profile:string,tenant_count:int,module_count:int,binding_count:int} */
    public function apply(string $profile): array
    {
        $definition = self::PROFILES[$profile]
            ?? throw new ModuleException('PRODUCT_PROFILE_INVALID', 'Unknown product profile.');
        $registry = $this->registry();
        $repository = new VerifiedTenantModuleRepository(
            new PdoModuleRuntimeRepository($this->pdo, true),
            $registry
        );
        $manager = new TenantModuleManager(
            $registry->compiled(),
            $repository,
            new OpisTenantModuleConfigValidator()
        );
        $audit = new PdoAuditRepository($this->pdo);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return (new PdoTransactionManager($this->pdo))->run(function () use (
            $profile,
            $definition,
            $repository,
            $manager,
            $audit,
            $now
        ): array {
            $tenants = $this->tenants($definition['tenant_codes']);
            foreach ($tenants as $tenant) {
                foreach ($definition['modules'] as $moduleKey) {
                    $before = $repository->tenantModule((int)$tenant['id'], $moduleKey);
                    $manager->enable(
                        (int)$tenant['id'],
                        $moduleKey,
                        [],
                        $now,
                        'product_profile'
                    );
                    if ($before === null || !$before->isEffective($now)) {
                        $audit->appendTenantSystem(
                            (int)$tenant['id'],
                            'tenant-module.profile-enabled',
                            'tenant.module.apply-product-profile',
                            'product-profile:' . $profile . ':' . $tenant['code'] . ':' . $moduleKey,
                            ['profile' => $profile, 'module_key' => $moduleKey]
                        );
                    }
                }
            }

            return [
                'profile' => $profile,
                'tenant_count' => count($tenants),
                'module_count' => count($definition['modules']),
                'binding_count' => count($tenants) * count($definition['modules']),
            ];
        });
    }

    private function registry(): DeployedTenantModuleRegistry
    {
        return (new PdoModuleGovernanceProvider(
            $this->pdo,
            $this->serverRoot,
            $this->deploymentConfig
        ))->registry();
    }

    /** @param list<string> $tenantCodes @return list<array{id:int,code:string}> */
    private function tenants(array $tenantCodes): array
    {
        $placeholders = implode(',', array_fill(0, count($tenantCodes), '?'));
        $statement = $this->pdo->prepare(
            "SELECT id,code FROM pa_tenant WHERE code IN ({$placeholders}) AND status='active' ORDER BY code FOR UPDATE"
        );
        $statement->execute($tenantCodes);
        $tenants = $statement->fetchAll(PDO::FETCH_ASSOC);
        $actualCodes = array_column($tenants, 'code');
        $expectedCodes = $tenantCodes;
        sort($expectedCodes, SORT_STRING);
        if ($actualCodes !== $expectedCodes) {
            throw new ModuleException('PRODUCT_PROFILE_TENANT_SET_INVALID', 'Product profile Tenant set is unavailable.');
        }
        return array_map(
            static fn(array $tenant): array => ['id' => (int)$tenant['id'], 'code' => (string)$tenant['code']],
            $tenants
        );
    }
}
