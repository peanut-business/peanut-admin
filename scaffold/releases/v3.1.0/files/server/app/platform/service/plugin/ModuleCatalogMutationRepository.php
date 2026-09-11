<?php
declare(strict_types=1);

namespace app\platform\service\plugin;

use PDO;
use PeanutAdmin\Kernel\Module\ManifestDocument;

/** Computes and applies the catalog/RBAC part of retire and purge from Module-owned catalog rows. */
final readonly class ModuleCatalogMutationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string,ManifestDocument> $manifests */
    public function retireMissing(array $manifests): void
    {
        $moduleKeys = array_keys($manifests);
        if ($moduleKeys === []) return;
        $declared = [
            'pa_permission' => [],
            'pa_protected_resource' => [],
            'pa_target_type' => [],
            'pa_data_condition_definition' => [],
        ];
        $operations = [];
        foreach ($manifests as $moduleKey => $manifest) {
            $catalog = is_array($manifest->data['catalog'] ?? null) ? $manifest->data['catalog'] : [];
            foreach ([
                'permissions' => 'pa_permission',
                'protected_resources' => 'pa_protected_resource',
                'target_types' => 'pa_target_type',
                'data_conditions' => 'pa_data_condition_definition',
            ] as $manifestKey => $table) {
                foreach ((array)($catalog[$manifestKey] ?? []) as $entry) {
                    if (is_array($entry) && is_string($entry['key'] ?? null)) {
                        $declared[$table][$moduleKey][] = $entry['key'];
                    }
                }
            }
            foreach ((array)($catalog['protected_resources'] ?? []) as $resource) {
                if (!is_array($resource) || !is_string($resource['key'] ?? null)) continue;
                foreach ((array)($resource['operations'] ?? []) as $operation) {
                    if (is_array($operation) && is_string($operation['key'] ?? null)) {
                        $operations[$resource['key'] . "\0" . $operation['key']] = true;
                    }
                }
            }
        }

        $now = gmdate('Y-m-d H:i:s.v');
        foreach ($declared as $table => $byModule) {
            foreach ($moduleKeys as $moduleKey) {
                $keys = array_values(array_unique($byModule[$moduleKey] ?? []));
                $this->retireMissingKeys($table, $moduleKey, $keys, $now);
            }
        }

        $statement = $this->pdo->prepare(
            'SELECT o.id,r.`key` resource_key,o.operation FROM pa_resource_operation o'
            . ' JOIN pa_protected_resource r ON r.id=o.protected_resource_id'
            . ' WHERE r.module_key IN (' . $this->placeholders($moduleKeys) . ") AND o.status='active' ORDER BY o.id"
        );
        $statement->execute($moduleKeys);
        $missingOperationIds = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!isset($operations[(string)$row['resource_key'] . "\0" . (string)$row['operation']])) {
                $missingOperationIds[] = (string)$row['id'];
            }
        }
        if ($missingOperationIds !== []) {
            $this->deleteByForeignIds('pa_resource_operation_permission', 'resource_operation_id', $missingOperationIds);
            foreach (['pa_resource_operation_target_type', 'pa_resource_operation_condition'] as $table) {
                $this->updateByIds($table, $this->operationRelationIds($table, $missingOperationIds), "status='retired'", []);
            }
            $this->updateByIds('pa_resource_operation', $missingOperationIds, "status='retired',updated_at=?", [$now]);
        }
    }

    /** @return list<string> */
    public function activeModuleKeys(): array
    {
        $keys = [];
        foreach (['pa_permission', 'pa_protected_resource', 'pa_target_type', 'pa_data_condition_definition', 'pa_menu_definition', 'pa_setting_definition'] as $table) {
            $rows = $this->pdo->query("SELECT DISTINCT module_key FROM `{$table}` WHERE status='active' ORDER BY module_key")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($rows as $key) {
                $key = (string)$key;
                if (!in_array($key, ['core', 'platform'], true)) $keys[$key] = true;
            }
        }
        $result = array_keys($keys);
        sort($result, SORT_STRING);
        return $result;
    }

    /** @param list<string> $moduleKeys @return array{removed:list<array<string,mixed>>,preserved:list<array<string,mixed>>,blockers:list<array<string,mixed>>} */
    public function plan(array $moduleKeys, bool $purge): array
    {
        $permissions = $this->ids('pa_permission', $moduleKeys);
        $resources = $this->ids('pa_protected_resource', $moduleKeys);
        $targets = $this->ids('pa_target_type', $moduleKeys);
        $conditions = $this->ids('pa_data_condition_definition', $moduleKeys);
        $operations = $this->operationIds($resources);
        $menus = $this->ids('pa_menu_definition', $moduleKeys);
        $settings = $this->ids('pa_setting_definition', $moduleKeys);

        $removed = [];
        $preserved = [];
        $blockers = $this->crossModuleBlockers(
            $moduleKeys,
            $permissions,
            $resources,
            $targets,
            $conditions,
            $operations,
        );

        if ($purge) {
            foreach ([
                ['pa_setting_target_value', $this->foreignIds('pa_setting_target_value', 'definition_id', $settings)],
                ['pa_setting_tenant_value', $this->foreignIds('pa_setting_tenant_value', 'definition_id', $settings)],
                ['pa_setting_deployment_value', $this->foreignIds('pa_setting_deployment_value', 'definition_id', $settings)],
                ['pa_setting_definition', $settings],
                ['pa_role_permission', $this->roleBindingIds($permissions, false)],
                ['pa_platform_role_permission', $this->roleBindingIds($permissions, true)],
                ['pa_menu_definition', $menus],
                ['pa_resource_operation_permission', $this->operationRelationIds('pa_resource_operation_permission', $operations)],
                ['pa_resource_operation_target_type', $this->operationRelationIds('pa_resource_operation_target_type', $operations)],
                ['pa_resource_operation_condition', $this->operationRelationIds('pa_resource_operation_condition', $operations)],
                ['pa_resource_operation', $operations],
                ['pa_protected_resource', $resources],
                ['pa_target_type', $targets],
                ['pa_data_condition_definition', $conditions],
                ['pa_permission', $permissions],
            ] as [$table, $identifiers]) {
                $this->append($removed, 'catalog', $table, 'delete', $identifiers);
            }
        } else {
            foreach ([
                ['pa_menu_definition', $this->activeIds('pa_menu_definition', $moduleKeys)],
                ['pa_setting_definition', $this->activeIds('pa_setting_definition', $moduleKeys)],
                ['pa_resource_operation_target_type', $this->activeOperationRelationIds('pa_resource_operation_target_type', $operations)],
                ['pa_resource_operation_condition', $this->activeOperationRelationIds('pa_resource_operation_condition', $operations)],
                ['pa_resource_operation', $this->activeOperationIds($resources)],
                ['pa_protected_resource', $this->activeIds('pa_protected_resource', $moduleKeys)],
                ['pa_target_type', $this->activeIds('pa_target_type', $moduleKeys)],
                ['pa_data_condition_definition', $this->activeIds('pa_data_condition_definition', $moduleKeys)],
                ['pa_permission', $this->activeIds('pa_permission', $moduleKeys)],
            ] as [$table, $identifiers]) {
                $this->append($removed, 'catalog', $table, 'soft_retire', $identifiers);
            }
            foreach ([
                ['pa_setting_target_value', $this->foreignIds('pa_setting_target_value', 'definition_id', $settings)],
                ['pa_setting_tenant_value', $this->foreignIds('pa_setting_tenant_value', 'definition_id', $settings)],
                ['pa_setting_deployment_value', $this->foreignIds('pa_setting_deployment_value', 'definition_id', $settings)],
                ['pa_role_permission', $this->roleBindingIds($permissions, false)],
                ['pa_platform_role_permission', $this->roleBindingIds($permissions, true)],
            ] as [$table, $identifiers]) {
                $this->append($preserved, 'catalog', $table, 'preserve', $identifiers, true);
            }
        }

        $this->sortEntries($removed);
        $this->sortEntries($preserved);
        usort($blockers, static fn(array $a, array $b): int => strcmp((string)$a['code'], (string)$b['code']));
        return ['removed' => $removed, 'preserved' => $preserved, 'blockers' => $blockers];
    }

    /** @param list<string> $moduleKeys */
    public function retire(array $moduleKeys): void
    {
        $now = gmdate('Y-m-d H:i:s.v');
        $permissions = $this->ids('pa_permission', $moduleKeys);
        $resources = $this->ids('pa_protected_resource', $moduleKeys);
        $operations = $this->operationIds($resources);
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            $this->updateByIds('pa_permission', $permissions, "status='retired',retired_at=?,updated_at=?", [$now, $now]);
            $this->updateByIds('pa_protected_resource', $resources, "status='retired',retired_at=?,updated_at=?", [$now, $now]);
            foreach (['pa_target_type', 'pa_data_condition_definition', 'pa_menu_definition'] as $table) {
                $this->updateByIds($table, $this->ids($table, $moduleKeys), "status='retired',updated_at=?", [$now]);
            }
            $this->updateByIds('pa_setting_definition', $this->ids('pa_setting_definition', $moduleKeys), "status='retired',revision=revision+1,updated_at=?", [$now]);
            $this->updateByIds('pa_resource_operation', $operations, "status='retired',updated_at=?", [$now]);
            foreach (['pa_resource_operation_target_type', 'pa_resource_operation_condition'] as $table) {
                $this->updateByIds($table, $this->operationRelationIds($table, $operations), "status='retired'", []);
            }
            if ($ownsTransaction) $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param list<string> $moduleKeys */
    public function purge(array $moduleKeys): void
    {
        $permissions = $this->ids('pa_permission', $moduleKeys);
        $resources = $this->ids('pa_protected_resource', $moduleKeys);
        $targets = $this->ids('pa_target_type', $moduleKeys);
        $conditions = $this->ids('pa_data_condition_definition', $moduleKeys);
        $operations = $this->operationIds($resources);
        $settings = $this->ids('pa_setting_definition', $moduleKeys);
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            foreach (['pa_setting_target_value', 'pa_setting_tenant_value', 'pa_setting_deployment_value'] as $table) {
                $this->deleteByForeignIds($table, 'definition_id', $settings);
            }
            $this->deleteByForeignIds('pa_role_permission', 'permission_id', $permissions);
            $this->deleteByForeignIds('pa_platform_role_permission', 'permission_id', $permissions);
            $this->deleteByIds('pa_menu_definition', $this->ids('pa_menu_definition', $moduleKeys));
            foreach (['pa_resource_operation_permission', 'pa_resource_operation_target_type', 'pa_resource_operation_condition'] as $table) {
                $this->deleteByForeignIds($table, 'resource_operation_id', $operations);
            }
            $this->deleteByIds('pa_resource_operation', $operations);
            $this->deleteByIds('pa_protected_resource', $resources);
            $this->deleteByIds('pa_target_type', $targets);
            $this->deleteByIds('pa_data_condition_definition', $conditions);
            $this->deleteByIds('pa_setting_definition', $settings);
            $this->deleteByIds('pa_permission', $permissions);
            if ($ownsTransaction) $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param list<string> $moduleKeys @return list<string> */
    private function ids(string $table, array $moduleKeys): array
    {
        if ($moduleKeys === []) return [];
        $statement = $this->pdo->prepare("SELECT id FROM `{$table}` WHERE module_key IN (" . $this->placeholders($moduleKeys) . ') ORDER BY id');
        $statement->execute($moduleKeys);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<string> $moduleKeys @return list<string> */
    private function activeIds(string $table, array $moduleKeys): array
    {
        if ($moduleKeys === []) return [];
        $statement = $this->pdo->prepare("SELECT id FROM `{$table}` WHERE module_key IN (" . $this->placeholders($moduleKeys) . ") AND status='active' ORDER BY id");
        $statement->execute($moduleKeys);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<string> $resourceIds @return list<string> */
    private function operationIds(array $resourceIds): array
    {
        return $this->foreignIds('pa_resource_operation', 'protected_resource_id', $resourceIds);
    }

    /** @param list<string> $resourceIds @return list<string> */
    private function activeOperationIds(array $resourceIds): array
    {
        if ($resourceIds === []) return [];
        $statement = $this->pdo->prepare('SELECT id FROM pa_resource_operation WHERE protected_resource_id IN (' . $this->placeholders($resourceIds) . ") AND status='active' ORDER BY id");
        $statement->execute($resourceIds);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<string> $operationIds @return list<string> */
    private function operationRelationIds(string $table, array $operationIds): array
    {
        return $this->foreignIds($table, 'resource_operation_id', $operationIds);
    }

    /** @param list<string> $operationIds @return list<string> */
    private function activeOperationRelationIds(string $table, array $operationIds): array
    {
        if ($operationIds === []) return [];
        $statement = $this->pdo->prepare("SELECT id FROM `{$table}` WHERE resource_operation_id IN (" . $this->placeholders($operationIds) . ") AND status='active' ORDER BY id");
        $statement->execute($operationIds);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<string> $foreignIds @return list<string> */
    private function foreignIds(string $table, string $column, array $foreignIds): array
    {
        if ($foreignIds === []) return [];
        $statement = $this->pdo->prepare("SELECT id FROM `{$table}` WHERE `{$column}` IN (" . $this->placeholders($foreignIds) . ') ORDER BY id');
        $statement->execute($foreignIds);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<string> $permissionIds @return list<string> */
    private function roleBindingIds(array $permissionIds, bool $platform): array
    {
        if ($permissionIds === []) return [];
        if ($platform) {
            $sql = 'SELECT CONCAT("platform_role_id=",platform_role_id,";permission_id=",permission_id) FROM pa_platform_role_permission WHERE permission_id IN (' . $this->placeholders($permissionIds) . ') ORDER BY platform_role_id,permission_id';
        } else {
            $sql = 'SELECT CONCAT("tenant_id=",tenant_id,";role_id=",role_id,";permission_id=",permission_id) FROM pa_role_permission WHERE permission_id IN (' . $this->placeholders($permissionIds) . ') ORDER BY tenant_id,role_id,permission_id';
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($permissionIds);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<string> $moduleKeys @param list<string> $permissionIds @param list<string> $resourceIds @param list<string> $targetIds @param list<string> $conditionIds @param list<string> $operationIds @return list<array<string,mixed>> */
    private function crossModuleBlockers(array $moduleKeys, array $permissionIds, array $resourceIds, array $targetIds, array $conditionIds, array $operationIds): array
    {
        $blockers = [];
        $checks = [
            ['MODULE_CATALOG_EXTERNAL_MENU_REFERENCE', 'SELECT m.id FROM pa_menu_definition m WHERE m.module_key NOT IN (' . $this->placeholders($moduleKeys) . ') AND m.required_permission_id IN (' . $this->placeholders($permissionIds) . ')', [...$moduleKeys, ...$permissionIds]],
            ['MODULE_CATALOG_EXTERNAL_PERMISSION_REFERENCE', 'SELECT r.id FROM pa_resource_operation_permission r WHERE r.permission_id IN (' . $this->placeholders($permissionIds) . ') AND r.resource_operation_id NOT IN (' . $this->placeholders($operationIds) . ')', [...$permissionIds, ...$operationIds]],
            ['MODULE_CATALOG_EXTERNAL_TARGET_REFERENCE', 'SELECT r.id FROM pa_resource_operation_target_type r WHERE r.target_type_id IN (' . $this->placeholders($targetIds) . ') AND r.resource_operation_id NOT IN (' . $this->placeholders($operationIds) . ')', [...$targetIds, ...$operationIds]],
            ['MODULE_CATALOG_EXTERNAL_CONDITION_REFERENCE', 'SELECT r.id FROM pa_resource_operation_condition r WHERE r.condition_definition_id IN (' . $this->placeholders($conditionIds) . ') AND r.resource_operation_id NOT IN (' . $this->placeholders($operationIds) . ')', [...$conditionIds, ...$operationIds]],
        ];
        foreach ($checks as [$code, $sql, $parameters]) {
            if (str_contains($sql, 'IN ()')) continue;
            $statement = $this->pdo->prepare($sql . ' ORDER BY 1');
            $statement->execute($parameters);
            $ids = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
            if ($ids !== []) $blockers[] = ['code' => $code, 'identifiers' => $ids];
        }
        return $blockers;
    }

    /** @param list<array<string,mixed>> $entries @param list<string> $identifiers */
    private function append(array &$entries, string $scope, string $table, string $action, array $identifiers, bool $includeEmpty = false): void
    {
        sort($identifiers, SORT_STRING);
        if ($identifiers === [] && !$includeEmpty) return;
        $entries[] = ['scope' => $scope, 'table' => $table, 'action' => $action, 'count' => count($identifiers), 'identifiers' => $identifiers];
    }

    /** @param list<array<string,mixed>> $entries */
    private function sortEntries(array &$entries): void
    {
        usort($entries, static fn(array $a, array $b): int => strcmp($a['scope'] . "\0" . $a['table'] . "\0" . $a['action'], $b['scope'] . "\0" . $b['table'] . "\0" . $b['action']));
    }

    /** @param list<string> $ids @param list<string> $parameters */
    private function updateByIds(string $table, array $ids, string $set, array $parameters): void
    {
        if ($ids === []) return;
        $statement = $this->pdo->prepare("UPDATE `{$table}` SET {$set} WHERE id IN (" . $this->placeholders($ids) . ')');
        $statement->execute([...$parameters, ...$ids]);
    }

    /** @param list<string> $ids */
    private function deleteByIds(string $table, array $ids): void
    {
        if ($ids === []) return;
        $statement = $this->pdo->prepare("DELETE FROM `{$table}` WHERE id IN (" . $this->placeholders($ids) . ')');
        $statement->execute($ids);
    }

    /** @param list<string> $ids */
    private function deleteByForeignIds(string $table, string $column, array $ids): void
    {
        if ($ids === []) return;
        $statement = $this->pdo->prepare("DELETE FROM `{$table}` WHERE `{$column}` IN (" . $this->placeholders($ids) . ')');
        $statement->execute($ids);
    }

    /** @param list<string> $activeKeys */
    private function retireMissingKeys(string $table, string $moduleKey, array $activeKeys, string $now): void
    {
        $sql = "UPDATE `{$table}` SET status='retired',updated_at=?";
        $parameters = [$now];
        if (in_array($table, ['pa_permission', 'pa_protected_resource'], true)) {
            $sql = "UPDATE `{$table}` SET status='retired',retired_at=?,updated_at=?";
            $parameters = [$now, $now];
        } elseif ($table === 'pa_setting_definition') {
            $sql = "UPDATE `{$table}` SET status='retired',revision=revision+1,updated_at=?";
        }
        $sql .= " WHERE module_key=? AND status='active'";
        $parameters[] = $moduleKey;
        if ($activeKeys !== []) {
            $sql .= ' AND `key` NOT IN (' . $this->placeholders($activeKeys) . ')';
            $parameters = [...$parameters, ...$activeKeys];
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
    }

    /** @param list<mixed> $values */
    private function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}
