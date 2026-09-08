<?php
declare(strict_types=1);

namespace app\platform\service\module;

use app\common\service\audit\AuditContractHost;
use app\common\service\instance\DeploymentMode;
use app\platform\service\plugin\PluginLockResolver;
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
                'official.rich-text',
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
        private PdoTransactionManager $transactions,
        private PdoModuleRuntimeRepository $moduleRuntime,
        private PdoModuleGovernanceProvider $moduleGovernance,
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

    /** Standalone owner selection adds locked private Modules; existing openings/configuration and RBAC remain intact. */
    public function applyAdditionalInstallationSelection(array $moduleKeys, DeploymentMode $mode, PluginLockResolver $lock): array
    {
        if ($mode !== DeploymentMode::Standalone) {
            throw new ModuleException('PRIVATE_TENANT_MODULE_STANDALONE_REQUIRED', 'Private Module selection requires Standalone.');
        }
        if ($moduleKeys === [] || !array_is_list($moduleKeys)
            || array_filter($moduleKeys, static fn(mixed $key): bool => !is_string($key) || $key === '') !== []) {
            throw new ModuleException('PRODUCT_PROFILE_INVALID', 'Select at least one private Module.');
        }
        $private = [];
        foreach ($lock->all() as $packageKey => $descriptor) {
            if (str_starts_with($packageKey, 'official.')) continue;
            foreach (array_keys($descriptor->moduleRoots) as $moduleKey) {
                if (!str_starts_with($moduleKey, 'official.')) $private[$moduleKey] = true;
            }
        }
        foreach ($moduleKeys as $moduleKey) {
            if (!isset($private[$moduleKey])) throw new ModuleException('PRIVATE_TENANT_MODULE_NOT_LOCKED', 'Selected Module is not owned by a locked private package.');
        }
        return $this->applyDefinition('private-additive', ['tenant_codes' => ['default'], 'modules' => array_values(array_unique($moduleKeys))], true);
    }

    /**
     * @param array{tenant_codes:list<string>,modules:list<string>} $definition
     * @return array{profile:string,tenant_count:int,module_count:int,binding_count:int}
     */
    private function applyDefinition(string $profile, array $definition, bool $additive = false): array
    {
        $registry = $this->registry();
        $repository = new VerifiedTenantModuleRepository(
            $this->moduleRuntime,
            $registry
        );
        $manager = new TenantModuleManager(
            $registry->compiled(),
            $repository,
            new OpisTenantModuleConfigValidator()
        );
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $this->transactions->run(function () use (
            $profile,
            $definition,
            $repository,
            $manager,
            $now,
            $registry,
            $additive
        ): array {
            $tenants = $this->tenants($definition['tenant_codes']);
            $selected = $definition['modules'];
            if ($additive) {
                // The locked default Tenant row serializes profile changes; count only still-effective dependencies.
                foreach ($registry->compiled()->modules as $manifest) {
                    $key = $manifest->data['key'];
                    if ($repository->tenantModule($tenants[0]['id'], $key)?->isEffective($now)) $selected[] = $key;
                }
            }
            $moduleKeys = $this->dependencyOrder($registry, array_values(array_unique($selected)));
            $bindings = 0;
            foreach ($tenants as $tenant) {
                foreach ($moduleKeys as $moduleKey) {
                    if ($additive && !in_array($moduleKey, $definition['modules'], true)) continue;
                    $before = $repository->tenantModule((int)$tenant['id'], $moduleKey);
                    if ($additive && $before?->isEffective($now)) continue;
                    $manager->enable(
                        (int)$tenant['id'],
                        $moduleKey,
                        [],
                        $now,
                        'product_profile'
                    );
                    $bindings++;
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
                'module_count' => count($definition['modules']),
                'binding_count' => $bindings,
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
        return $this->moduleGovernance->registry();
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
