<?php
declare(strict_types=1);

namespace app\platform\service\module;

use app\common\service\audit\AuditContractHost;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\Kernel\Audit\AuditOutcome;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;
use PeanutAdmin\Kernel\Module\TenantModuleManager;
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
        private array $deploymentConfig,
        private AuditContractHost $audit,
    ) {
    }

    /** @return array{profile:string,tenant_count:int,module_count:int,binding_count:int} */
    public function apply(string $profile): array
    {
        $definition = self::PROFILES[$profile]
            ?? throw new ModuleException('PRODUCT_PROFILE_INVALID', 'Unknown product profile.');
        return $this->applyDefinition($profile, $definition);
    }

    /**
     * @param list<string> $moduleKeys
     * @return array{profile:string,tenant_count:int,module_count:int,binding_count:int}
     */
    public function applyInstallationSelection(array $moduleKeys): array
    {
        if (!array_is_list($moduleKeys)
            || array_filter($moduleKeys, static fn(mixed $key): bool => !is_string($key)) !== []) {
            throw new ModuleException('PRODUCT_PROFILE_INVALID', 'Installation Module selection is invalid.');
        }
        $moduleKeys = array_values(array_unique($moduleKeys));
        sort($moduleKeys, SORT_STRING);
        return $this->applyDefinition('installation', [
            'tenant_codes' => ['default'],
            'modules' => $moduleKeys,
        ]);
    }

    /**
     * @param array{tenant_codes:list<string>,modules:list<string>} $definition
     * @return array{profile:string,tenant_count:int,module_count:int,binding_count:int}
     */
    private function applyDefinition(string $profile, array $definition): array
    {
        $registry = $this->registry();
        $moduleKeys = $this->dependencyOrder($registry, $definition['modules']);
        $repository = new VerifiedTenantModuleRepository(
            new PdoModuleRuntimeRepository($this->pdo, true),
            $registry
        );
        $manager = new TenantModuleManager(
            $registry->compiled(),
            $repository,
            new OpisTenantModuleConfigValidator()
        );
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return (new PdoTransactionManager($this->pdo))->run(function () use (
            $profile,
            $definition,
            $repository,
            $manager,
            $now,
            $moduleKeys
        ): array {
            $tenants = $this->tenants($definition['tenant_codes']);
            foreach ($tenants as $tenant) {
                foreach ($moduleKeys as $moduleKey) {
                    $before = $repository->tenantModule((int)$tenant['id'], $moduleKey);
                    $manager->enable(
                        (int)$tenant['id'],
                        $moduleKey,
                        [],
                        $now,
                        'product_profile'
                    );
                    if ($before === null || !$before->isEffective($now)) {
                        $this->audit->recordTenantSystem(
                            (int)$tenant['id'],
                            'tenant-module.profile-enabled',
                            'tenant.module.apply-product-profile',
                            'product-profile:' . $profile . ':' . $tenant['code'] . ':' . $moduleKey,
                            ['profile' => $profile, 'module_key' => $moduleKey],
                            AuditOutcome::Success,
                            null,
                        );
                    }
                }
            }

            return [
                'profile' => $profile,
                'tenant_count' => count($tenants),
                'module_count' => count($moduleKeys),
                'binding_count' => count($tenants) * count($moduleKeys),
            ];
        });
    }

    /**
     * @param list<string> $moduleKeys
     * @return list<string>
     */
    private function dependencyOrder(DeployedTenantModuleRegistry $registry, array $moduleKeys): array
    {
        $manifests = [];
        foreach ($registry->compiled()->modules as $manifest) {
            $key = $manifest->data['key'] ?? null;
            if (is_string($key) && $key !== '') {
                $manifests[$key] = $manifest;
            }
        }
        $selected = array_fill_keys($moduleKeys, true);
        $visiting = [];
        $visited = [];
        $ordered = [];
        $visit = function (string $moduleKey) use (
            &$visit,
            &$visiting,
            &$visited,
            &$ordered,
            $manifests,
            $selected
        ): void {
            if (isset($visited[$moduleKey])) {
                return;
            }
            if (isset($visiting[$moduleKey])) {
                throw new ModuleException('PRODUCT_PROFILE_DEPENDENCY_CYCLE', 'Product profile dependency cycle detected.');
            }
            $manifest = $manifests[$moduleKey]
                ?? throw new ModuleException('PRODUCT_PROFILE_MODULE_INVALID', "Unknown product profile Module: {$moduleKey}");
            $visiting[$moduleKey] = true;
            $tenant = $manifest->data['tenant'] ?? [];
            $dependencies = is_array($tenant) ? ($tenant['requires'] ?? []) : [];
            if (!is_array($dependencies) || !array_is_list($dependencies)) {
                throw new ModuleException('PRODUCT_PROFILE_DEPENDENCY_INVALID', 'Product profile dependency metadata is invalid.');
            }
            foreach ($dependencies as $dependency) {
                if (!is_string($dependency) || !isset($selected[$dependency])) {
                    throw new ModuleException(
                        'PRODUCT_PROFILE_DEPENDENCY_MISSING',
                        "Product profile Module {$moduleKey} requires selected Module {$dependency}."
                    );
                }
                $visit($dependency);
            }
            unset($visiting[$moduleKey]);
            $visited[$moduleKey] = true;
            $ordered[] = $moduleKey;
        };
        foreach ($moduleKeys as $moduleKey) {
            $visit($moduleKey);
        }
        return $ordered;
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
