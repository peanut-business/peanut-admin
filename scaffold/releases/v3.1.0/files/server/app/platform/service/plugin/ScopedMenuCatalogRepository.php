<?php
declare(strict_types=1);

namespace app\platform\service\plugin;

use PDO;
use PeanutAdmin\Kernel\Menu\MenuCatalogRepository;
use PeanutAdmin\Kernel\Menu\MenuDefinition;

/** Preserves active menus outside a targeted module:sync/apply scope. */
final readonly class ScopedMenuCatalogRepository implements MenuCatalogRepository
{
    /** @param non-empty-list<string> $moduleKeys */
    public function __construct(
        private PDO $pdo,
        private MenuCatalogRepository $inner,
        private array $moduleKeys,
    ) {
    }

    public function synchronize(MenuDefinition $definition, string $manifestDigest): void
    {
        $this->inner->synchronize($definition, $manifestDigest);
    }

    public function retireMissing(array $activeKeys): void
    {
        $placeholders = implode(',', array_fill(0, count($this->moduleKeys), '?'));
        $statement = $this->pdo->prepare("SELECT `key` FROM pa_menu_definition WHERE status='active' AND module_key NOT IN ({$placeholders}) ORDER BY `key`");
        $statement->execute($this->moduleKeys);
        $preserved = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
        $keys = array_values(array_unique([...$activeKeys, ...$preserved]));
        sort($keys, SORT_STRING);
        $this->inner->retireMissing($keys);
    }

    public function activeDefinitions(string $scope): array
    {
        return $this->inner->activeDefinitions($scope);
    }

    public function activeDeploymentModules(): array
    {
        return $this->inner->activeDeploymentModules();
    }

    public function activeTenantModules(int $tenantId): array
    {
        return $this->inner->activeTenantModules($tenantId);
    }
}
