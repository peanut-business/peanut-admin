<?php
declare(strict_types=1);

namespace app\common\service\scaffold;

use RuntimeException;

final readonly class EditionProfile
{
    private const EDITIONS = ['standalone', 'multi-tenant'];

    /** @param array<string,mixed> $definition */
    private function __construct(
        public string $edition,
        public string $protocol,
        public int $generatorVersion,
        public string $sourceSha256,
        public array $definition,
    ) {
    }

    public static function load(string $path, string $edition): self
    {
        if (!in_array($edition, self::EDITIONS, true)) {
            throw new RuntimeException('CREATE_APP_EDITION_INVALID');
        }
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('CREATE_APP_EDITION_PROFILE_MISSING');
        }
        $raw = file_get_contents($path);
        try {
            $document = is_string($raw)
                ? json_decode($raw, true, 128, JSON_THROW_ON_ERROR)
                : null;
        } catch (\JsonException $exception) {
            throw new RuntimeException('CREATE_APP_EDITION_PROFILE_INVALID_JSON', 0, $exception);
        }
        if (!is_array($document) || array_is_list($document)
            || array_keys($document) !== ['schema_version', 'protocol', 'generator_version', 'editions']
            || ($document['schema_version'] ?? null) !== 1
            || ($document['protocol'] ?? null) !== 'peanut.edition-profiles.v1'
            || ($document['generator_version'] ?? null) !== 1
            || !is_array($document['editions'] ?? null)
            || array_keys($document['editions']) !== self::EDITIONS
            || !is_string($raw)) {
            throw new RuntimeException('CREATE_APP_EDITION_PROFILE_SCHEMA_INVALID');
        }

        $definition = $document['editions'][$edition] ?? null;
        self::assertDefinition($edition, $definition);

        return new self(
            $edition,
            $document['protocol'],
            $document['generator_version'],
            hash('sha256', $raw),
            $definition,
        );
    }

    /** @return array<string,mixed> */
    public function identity(): array
    {
        return [
            'name' => $this->edition,
            'protocol' => $this->protocol,
            'generator_version' => $this->generatorVersion,
            'source_sha256' => $this->sourceSha256,
            'deployment_mode' => $this->definition['deployment_mode'],
            'data_scope_policy' => $this->definition['data_scope_policy'],
            'admin_bundle' => $this->definition['admin_bundle'],
            'platform_bundle' => $this->definition['platform_bundle'],
            'module_profile' => $this->definition['module_profile'],
            'schema' => $this->definition['schema'],
        ];
    }

    /** @return list<string> */
    public function schemaSources(): array
    {
        $sources = [];
        foreach ($this->definition['schema']['table_rules'] as $rule) {
            foreach ($rule['sources'] as $source) {
                $sources[$source] = true;
            }
        }
        $sources = array_keys($sources);
        sort($sources, SORT_STRING);
        return $sources;
    }

    /** @return array<string,array{action:string,sources:list<string>}> */
    public function tableRulesForSource(string $source): array
    {
        $rules = [];
        foreach ($this->definition['schema']['table_rules'] as $table => $rule) {
            if (in_array($source, $rule['sources'], true)) {
                $rules[$table] = $rule;
            }
        }
        return $rules;
    }

    /** @param mixed $definition */
    private static function assertDefinition(string $edition, mixed $definition): void
    {
        if (!is_array($definition) || array_is_list($definition)
            || array_keys($definition) !== [
                'deployment_mode', 'data_scope_policy', 'admin_bundle',
                'platform_bundle', 'module_profile', 'schema',
            ]
            || ($definition['deployment_mode'] ?? null) !== $edition
            || ($definition['admin_bundle'] ?? null) !== $edition
            || !is_bool($definition['platform_bundle'] ?? null)
            || ($definition['module_profile'] ?? null) !== 'official-default'
            || !is_array($definition['schema'] ?? null)
            || array_keys($definition['schema']) !== [
                'projection', 'retains_core_tenant_identity', 'table_rules',
            ]
            || ($definition['schema']['retains_core_tenant_identity'] ?? null) !== true
            || !is_array($definition['schema']['table_rules'] ?? null)
            || ($definition['schema']['table_rules'] !== []
                && array_is_list($definition['schema']['table_rules']))) {
            throw new RuntimeException('CREATE_APP_EDITION_PROFILE_SCHEMA_INVALID');
        }

        $expectedPolicy = $edition === 'standalone'
            ? 'app\\common\\tenancy\\StandaloneDataScopePolicy'
            : 'app\\common\\tenancy\\MultiTenantDataScopePolicy';
        $expectedProjection = $edition === 'standalone'
            ? 'single-organization-v1'
            : 'tenant-owned-v1';
        if (($definition['data_scope_policy'] ?? null) !== $expectedPolicy
            || ($definition['schema']['projection'] ?? null) !== $expectedProjection
            || ($edition === 'standalone' && $definition['platform_bundle'] !== false)
            || ($edition === 'multi-tenant' && $definition['platform_bundle'] !== true)) {
            throw new RuntimeException('CREATE_APP_EDITION_PROFILE_SCHEMA_INVALID');
        }

        $rules = $definition['schema']['table_rules'];
        $tables = array_keys($rules);
        $sortedTables = $tables;
        sort($sortedTables, SORT_STRING);
        if ($tables !== $sortedTables) {
            throw new RuntimeException('CREATE_APP_EDITION_PROFILE_TABLES_INVALID');
        }
        foreach ($rules as $table => $rule) {
            if (!is_string($table) || preg_match('/^pa_[a-z][a-z0-9_]*$/D', $table) !== 1
                || !is_array($rule) || array_is_list($rule)
                || array_keys($rule) !== ['action', 'sources']
                || !in_array($rule['action'] ?? null, ['strip_tenant_column', 'exclude_table'], true)
                || !is_array($rule['sources'] ?? null) || !array_is_list($rule['sources'])
                || $rule['sources'] === []
                || array_values(array_unique($rule['sources'], SORT_STRING)) !== $rule['sources']) {
                throw new RuntimeException('CREATE_APP_EDITION_PROFILE_TABLES_INVALID');
            }
            foreach ($rule['sources'] as $source) {
                if (!is_string($source)
                    || preg_match('#^server/(?:database/migrations/[^/]+|database/init|app/Modules/[^/]+/[^/]+/Database/Migrations/[^/]+)\.sql$#D', $source) !== 1) {
                    throw new RuntimeException('CREATE_APP_EDITION_PROFILE_TABLES_INVALID');
                }
            }
        }
        if (($edition === 'standalone' && $rules === [])
            || ($edition === 'multi-tenant' && $rules !== [])) {
            throw new RuntimeException('CREATE_APP_EDITION_PROFILE_TABLES_INVALID');
        }
    }
}
