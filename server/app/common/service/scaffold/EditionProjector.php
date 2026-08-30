<?php
declare(strict_types=1);

namespace app\common\service\scaffold;

use RuntimeException;

final class EditionProjector
{
    private const PATHS = [
        'deploy/docker/nginx-select-admin.sh',
        'deploy/docker/production.Dockerfile',
        'server/.env.example',
        'server/database/init.sql',
        'server/route/app.php',
    ];

    /** @return list<string> */
    public function paths(EditionProfile $profile): array
    {
        $paths = array_values(array_unique([...self::PATHS, ...$profile->schemaSources()]));
        sort($paths, SORT_STRING);
        return $paths;
    }

    /** @return array{path:string,sha256:string,mode:int,classification:string,owner:string,source:string} */
    public function project(
        string $stage,
        array $entry,
        string $content,
        EditionProfile $profile,
    ): array {
        $path = (string)$entry['target'];
        $content = match ($path) {
            'server/.env.example' => $this->serverEnvironment($content, $profile->edition),
            'server/database/init.sql' => $this->schemaSource($content, $path, $profile),
            'server/route/app.php' => $this->routeComposition($content, $profile),
            'deploy/docker/production.Dockerfile' => $this->productionDockerfile($content, $profile),
            'deploy/docker/nginx-select-admin.sh' => $this->adminSelector($content, $profile),
            default => $profile->tableRulesForSource($path) === []
                ? $content
                : $this->schemaSource($content, $path, $profile),
        };
        $destination = $stage . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $this->writeFile($destination, $content, (int)$entry['mode']);

        return [
            'path' => $path,
            'sha256' => hash('sha256', $content),
            'mode' => $entry['mode'],
            'classification' => $entry['classification'],
            'owner' => $entry['owner'],
            'source' => $entry['path'],
        ];
    }

    private function serverEnvironment(string $content, string $edition): string
    {
        if (preg_match_all('/^DEPLOYMENT_MODE=(standalone|multi-tenant)$/m', $content) !== 1) {
            throw new RuntimeException('CREATE_APP_EDITION_ENV_SOURCE_INVALID');
        }
        return preg_replace(
            '/^DEPLOYMENT_MODE=(standalone|multi-tenant)$/m',
            'DEPLOYMENT_MODE=' . $edition,
            $content,
            1,
        ) ?? $content;
    }

    private function routeComposition(string $content, EditionProfile $profile): string
    {
        if ($profile->edition !== 'standalone') {
            return $content;
        }
        if (preg_match_all("/^require __DIR__ \. '\/platform\.php';$/m", $content) !== 1) {
            throw new RuntimeException('CREATE_APP_EDITION_ROUTE_SOURCE_INVALID');
        }
        return preg_replace(
            "/^require __DIR__ \. '\/platform\.php';\n/m",
            '',
            $content,
            1,
        ) ?? $content;
    }

    private function productionDockerfile(string $content, EditionProfile $profile): string
    {
        if ($profile->edition === 'standalone') {
            $content = $this->replaceOnce(
                $content,
                "    && VITE_DEPLOYMENT_MODE=standalone pnpm exec vite build --config ./config/vite.config.prod.ts --outDir dist/standalone \\\n"
                    . "    && VITE_DEPLOYMENT_MODE=multi-tenant pnpm exec vite build --config ./config/vite.config.prod.ts --outDir dist/multi-tenant",
                "    && VITE_DEPLOYMENT_MODE=standalone pnpm exec vite build --config ./config/vite.config.prod.ts --outDir dist/standalone",
                'CREATE_APP_EDITION_DOCKER_ADMIN_BUILD_SOURCE_INVALID',
            );
            $content = preg_replace(
                '/\nFROM node:20\.19\.4-bookworm-slim AS platform-builder\n.*?\nRUN npm run build\n/s',
                '',
                $content,
                1,
                $platformCount,
            ) ?? $content;
            if ($platformCount !== 1) {
                throw new RuntimeException('CREATE_APP_EDITION_DOCKER_PLATFORM_BUILD_SOURCE_INVALID');
            }
            $content = $this->removeLineOnce(
                $content,
                'COPY --from=platform-builder /build/platform/dist /var/www/peanut-admin/server/public/platform',
                'CREATE_APP_EDITION_DOCKER_PLATFORM_COPY_SOURCE_INVALID',
            );
            $content = $this->removeLineOnce(
                $content,
                '    && chmod +x server/database/seed-multi-tenant-demo.php \\',
                'CREATE_APP_EDITION_DOCKER_DEMO_SOURCE_INVALID',
            );
            $content = $this->removeLineOnce(
                $content,
                '    && ln -s /var/www/peanut-admin/server/database/seed-multi-tenant-demo.php /usr/local/bin/peanut-seed-multi-tenant-demo \\',
                'CREATE_APP_EDITION_DOCKER_DEMO_SOURCE_INVALID',
            );
        } else {
            $content = $this->replaceOnce(
                $content,
                "    && VITE_DEPLOYMENT_MODE=standalone pnpm exec vite build --config ./config/vite.config.prod.ts --outDir dist/standalone \\\n"
                    . "    && VITE_DEPLOYMENT_MODE=multi-tenant pnpm exec vite build --config ./config/vite.config.prod.ts --outDir dist/multi-tenant",
                "    && VITE_DEPLOYMENT_MODE=multi-tenant pnpm exec vite build --config ./config/vite.config.prod.ts --outDir dist/multi-tenant",
                'CREATE_APP_EDITION_DOCKER_ADMIN_BUILD_SOURCE_INVALID',
            );
        }

        return $content;
    }

    private function adminSelector(string $content, EditionProfile $profile): string
    {
        $content = $this->replaceOnce(
            $content,
            '    standalone|multi-tenant) ;;',
            '    ' . $profile->edition . ') ;;',
            'CREATE_APP_EDITION_NGINX_SELECTOR_SOURCE_INVALID',
        );
        return $this->replaceOnce(
            $content,
            'nginx-select-admin: DEPLOYMENT_MODE must be standalone or multi-tenant',
            'nginx-select-admin: this artifact requires DEPLOYMENT_MODE=' . $profile->edition,
            'CREATE_APP_EDITION_NGINX_SELECTOR_SOURCE_INVALID',
        );
    }

    private function schemaSource(string $content, string $source, EditionProfile $profile): string
    {
        if ($profile->edition !== 'standalone') {
            return $content;
        }

        $rules = $profile->tableRulesForSource($source);
        if ($rules === []) {
            throw new RuntimeException('CREATE_APP_EDITION_SCHEMA_SOURCE_UNREGISTERED: ' . $source);
        }

        if ($source === 'server/database/init.sql') {
            foreach ($rules as $table => $rule) {
                $content = $rule['action'] === 'exclude_table'
                    ? $this->excludeTable($content, $table)
                    : $this->standaloneTable($content, $table);
            }
            $content = $this->standaloneInsertProjection($content, $rules);
            $this->assertProjectedRules($content, $rules, $source);
            return $content;
        }

        foreach ($rules as $table => $rule) {
            if ($rule['action'] === 'exclude_table') {
                $content = $this->excludeTable($content, $table);
                continue;
            }
            if (preg_match($this->tablePattern($table), $content) === 1) {
                $content = $this->standaloneTable($content, $table);
            }
        }
        $content = $this->standaloneInsertProjection($content, $rules);
        $content = $this->standaloneMigrationProjection($content, $source);
        $this->assertProjectedRules($content, $rules, $source);
        if (str_contains($content, '`tenant_id`')) {
            throw new RuntimeException('CREATE_APP_STANDALONE_MIGRATION_TENANT_REFERENCE_REMAINS: ' . $source);
        }
        return $content;
    }

    private function excludeTable(string $content, string $table): string
    {
        $pattern = $this->tablePattern($table);
        if (preg_match_all($pattern, $content) !== 1) {
            throw new RuntimeException('CREATE_APP_STANDALONE_EXCLUDED_TABLE_INVALID: ' . $table);
        }
        $content = preg_replace($pattern, '', $content, 1) ?? $content;
        if (str_contains($content, '`' . $table . '`')) {
            throw new RuntimeException('CREATE_APP_STANDALONE_EXCLUDED_TABLE_REFERENCE_REMAINS: ' . $table);
        }
        return preg_replace("/\n{3,}/", "\n\n", $content) ?? $content;
    }

    private function standaloneTable(string $content, string $table): string
    {
        $pattern = $this->tablePattern($table);
        if (preg_match($pattern, $content, $matches) !== 1) {
            throw new RuntimeException('CREATE_APP_STANDALONE_SCHEMA_TABLE_MISSING: ' . $table);
        }
        $statement = $matches[0];
        if (preg_match('/^(CREATE TABLE `[^`]+` \(\n)(.*)(\n\) ENGINE=.*)$/s', $statement, $parts) !== 1) {
            throw new RuntimeException('CREATE_APP_STANDALONE_SCHEMA_TABLE_INVALID: ' . $table);
        }
        $definitions = $this->splitSqlList($parts[2]);
        $projected = [];
        $columnCount = 0;
        foreach ($definitions as $definition) {
            $definition = trim($definition);
            if (preg_match('/^`tenant_id`\s/i', $definition) === 1) {
                $columnCount++;
                continue;
            }
            if (str_contains($definition, '`tenant_id`')) {
                $definition = $this->stripTenantKeyColumns($definition);
                if ($definition === null) {
                    continue;
                }
            }
            $projected[] = '  ' . $definition;
        }
        if ($columnCount !== 1) {
            throw new RuntimeException('CREATE_APP_STANDALONE_SCHEMA_TENANT_COLUMN_INVALID: ' . $table);
        }
        $statement = $parts[1] . implode(",\n", $projected) . $parts[3];
        return preg_replace($pattern, $statement, $content, 1) ?? $content;
    }

    private function stripTenantKeyColumns(string $definition): ?string
    {
        $emptyList = false;
        $definition = preg_replace_callback(
            '/\(([^()]*)\)/',
            static function (array $matches) use (&$emptyList): string {
                $columns = array_map('trim', explode(',', $matches[1]));
                if (!in_array('`tenant_id`', $columns, true)) {
                    return $matches[0];
                }
                $columns = array_values(array_filter(
                    $columns,
                    static fn(string $column): bool => $column !== '`tenant_id`',
                ));
                if ($columns === []) {
                    $emptyList = true;
                }
                return '(' . implode(', ', $columns) . ')';
            },
            $definition,
        ) ?? $definition;
        if ($emptyList || str_contains($definition, '`tenant_id`')) {
            return null;
        }
        if (preg_match('/^(?:UNIQUE )?KEY\s+`[^`]+`\s*\(\s*`id`\s*\)$/i', $definition) === 1) {
            return null;
        }
        return $definition;
    }

    /** @param array<string,array{action:string,sources:list<string>}> $rules */
    private function standaloneInsertProjection(string $content, array $rules): string
    {
        foreach ($rules as $table => $rule) {
            if ($rule['action'] !== 'strip_tenant_column') {
                continue;
            }
            $pattern = '/(INSERT(?:\s+IGNORE)?\s+INTO\s+`' . preg_quote($table, '/')
                . '`\s*\()(.*?)(\)\s*(?:VALUES|SELECT)\s*)(.*?;)/is';
            $content = preg_replace_callback(
                $pattern,
                function (array $matches) use ($table): string {
                    $columns = array_map('trim', $this->splitSqlList($matches[2]));
                    $index = array_search('`tenant_id`', $columns, true);
                    if ($index === false) {
                        return $matches[0];
                    }
                    array_splice($columns, $index, 1);
                    $values = $matches[4];
                    if (preg_match('/\bVALUES\s*$/i', $matches[3]) === 1) {
                        $values = $this->projectValuesList($values, $index, $table);
                    } else {
                        [$select, $tail] = $this->splitSelectTail($values, $table);
                        $expressions = $this->splitSqlList($select);
                        if (!array_key_exists($index, $expressions)) {
                            throw new RuntimeException('CREATE_APP_STANDALONE_INSERT_SELECT_INVALID: ' . $table);
                        }
                        array_splice($expressions, $index, 1);
                        $values = implode(',', $expressions) . $tail;
                    }
                    return $matches[1] . implode(',', $columns) . $matches[3] . $values;
                },
                $content,
            ) ?? $content;
        }
        return $content;
    }

    private function projectValuesList(string $values, int $index, string $table): string
    {
        $suffix = '';
        if (str_ends_with($values, ';')) {
            $values = substr($values, 0, -1);
            $suffix = ';';
        }
        $rows = $this->splitSqlList($values);
        foreach ($rows as &$row) {
            $trimmed = trim($row);
            if (!str_starts_with($trimmed, '(') || !str_ends_with($trimmed, ')')) {
                throw new RuntimeException('CREATE_APP_STANDALONE_INSERT_VALUES_INVALID: ' . $table);
            }
            $items = $this->splitSqlList(substr($trimmed, 1, -1));
            if (!array_key_exists($index, $items)) {
                throw new RuntimeException('CREATE_APP_STANDALONE_INSERT_VALUES_INVALID: ' . $table);
            }
            array_splice($items, $index, 1);
            $row = '(' . implode(',', $items) . ')';
        }
        unset($row);
        return implode(",\n", $rows) . $suffix;
    }

    /** @return array{string,string} */
    private function splitSelectTail(string $select, string $table): array
    {
        $length = strlen($select);
        $depth = 0;
        $quote = null;
        for ($index = 0; $index < $length; $index++) {
            $character = $select[$index];
            if ($quote !== null) {
                if ($character === $quote && ($index === 0 || $select[$index - 1] !== '\\')) {
                    if ($index + 1 < $length && $select[$index + 1] === $quote) {
                        $index++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($character === "'" || $character === '"' || $character === '`') {
                $quote = $character;
                continue;
            }
            if ($character === '(') {
                $depth++;
                continue;
            }
            if ($character === ')') {
                $depth--;
                continue;
            }
            if ($depth === 0 && preg_match('/\G\s+(?:FROM|WHERE)\b/iA', $select, $match, 0, $index) === 1) {
                return [substr($select, 0, $index), substr($select, $index)];
            }
        }
        if ($quote !== null || $depth !== 0) {
            throw new RuntimeException('CREATE_APP_STANDALONE_INSERT_SELECT_INVALID: ' . $table);
        }
        return [$select, ''];
    }

    private function standaloneMigrationProjection(string $content, string $source): string
    {
        $content = preg_replace('/\s+AFTER\s+`tenant_id`/i', '', $content) ?? $content;
        $content = $this->stripTenantKeyClauses($content);
        return match ($source) {
            'server/database/migrations/20260823-unify-storage-service.sql' => $this->storageMigration($content),
            'server/database/migrations/20260824-payment-channel-grants.sql' => $this->paymentGrantMigration($content),
            'server/database/migrations/20260828-provider-qualification-evidence.sql' => $content,
            default => throw new RuntimeException('CREATE_APP_STANDALONE_MIGRATION_PROJECTOR_MISSING: ' . $source),
        };
    }

    private function storageMigration(string $content): string
    {
        $content = $this->replaceOnce(
            $content,
            "CONCAT('legacy-material:',f.`tenant_id`,':',f.`id`)",
            "CONCAT('legacy-material:',f.`id`)",
            'CREATE_APP_STANDALONE_STORAGE_MIGRATION_SOURCE_INVALID',
        );
        return $this->replaceOnce(
            $content,
            "CONCAT('legacy-material:',`tenant_id`,':',`id`)",
            "CONCAT('legacy-material:',`id`)",
            'CREATE_APP_STANDALONE_STORAGE_MIGRATION_SOURCE_INVALID',
        );
    }

    private function paymentGrantMigration(string $content): string
    {
        $content = $this->replaceOnce(
            $content,
            "JOIN `pa_external_channel_binding` b\n  ON b.`tenant_id` = ro.`tenant_id`\n AND b.`provider` =",
            "JOIN `pa_external_channel_binding` b\n  ON b.`provider` =",
            'CREATE_APP_STANDALONE_PAYMENT_MIGRATION_SOURCE_INVALID',
        );
        return $this->replaceOnce(
            $content,
            "JOIN `pa_payment_tenant_channel_grant` g\n  ON g.`tenant_id` = ro.`tenant_id`\n AND g.`provider` =",
            "JOIN `pa_payment_tenant_channel_grant` g\n  ON g.`provider` =",
            'CREATE_APP_STANDALONE_PAYMENT_MIGRATION_SOURCE_INVALID',
        );
    }

    private function stripTenantKeyClauses(string $content): string
    {
        $content = preg_replace_callback(
            '/((?:UNIQUE\s+)?KEY\s+`[^`]+`\s*)\(([^()]*)\)/i',
            fn(array $matches): string => $matches[1] . $this->tenantlessColumnList($matches[2]),
            $content,
        ) ?? $content;
        return preg_replace_callback(
            '/(FOREIGN\s+KEY\s*)\(([^()]*)\)(\s+REFERENCES\s+`[^`]+`\s*)\(([^()]*)\)/i',
            fn(array $matches): string => $matches[1] . $this->tenantlessColumnList($matches[2])
                . $matches[3] . $this->tenantlessColumnList($matches[4]),
            $content,
        ) ?? $content;
    }

    private function tenantlessColumnList(string $list): string
    {
        $columns = array_values(array_filter(
            array_map('trim', explode(',', $list)),
            static fn(string $column): bool => $column !== '`tenant_id`',
        ));
        if ($columns === []) {
            throw new RuntimeException('CREATE_APP_STANDALONE_MIGRATION_KEY_INVALID');
        }
        return '(' . implode(', ', $columns) . ')';
    }

    /** @param array<string,array{action:string,sources:list<string>}> $rules */
    private function assertProjectedRules(string $content, array $rules, string $source): void
    {
        foreach ($rules as $table => $rule) {
            if ($rule['action'] === 'exclude_table') {
                if (str_contains($content, '`' . $table . '`')) {
                    throw new RuntimeException('CREATE_APP_STANDALONE_EXCLUDED_TABLE_REFERENCE_REMAINS: ' . $table);
                }
                continue;
            }
            if (preg_match($this->tablePattern($table), $content, $matches) === 1
                && str_contains($matches[0], '`tenant_id`')) {
                throw new RuntimeException('CREATE_APP_STANDALONE_SCHEMA_TENANT_COLUMN_REMAINS: ' . $table . '@' . $source);
            }
            if (preg_match('/INSERT(?:\s+IGNORE)?\s+INTO\s+`' . preg_quote($table, '/')
                . '`\s*\([^)]*`tenant_id`/is', $content) === 1) {
                throw new RuntimeException('CREATE_APP_STANDALONE_INSERT_TENANT_COLUMN_REMAINS: ' . $table . '@' . $source);
            }
        }
    }

    private function tablePattern(string $table): string
    {
        return '/CREATE TABLE `' . preg_quote($table, '/') . '` \(.*?\n\) ENGINE=.*?;(?=\n)/s';
    }

    /** @return list<string> */
    private function splitSqlList(string $input): array
    {
        $items = [];
        $start = 0;
        $depth = 0;
        $quote = null;
        $length = strlen($input);
        for ($index = 0; $index < $length; $index++) {
            $character = $input[$index];
            if ($quote !== null) {
                if ($character === $quote && ($index === 0 || $input[$index - 1] !== '\\')) {
                    if ($index + 1 < $length && $input[$index + 1] === $quote) {
                        $index++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($character === "'" || $character === '"' || $character === '`') {
                $quote = $character;
            } elseif ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
            } elseif ($character === ',' && $depth === 0) {
                $items[] = substr($input, $start, $index - $start);
                $start = $index + 1;
            }
            if ($depth < 0) {
                throw new RuntimeException('CREATE_APP_EDITION_SQL_LIST_INVALID');
            }
        }
        if ($quote !== null || $depth !== 0) {
            throw new RuntimeException('CREATE_APP_EDITION_SQL_LIST_INVALID');
        }
        $items[] = substr($input, $start);
        return $items;
    }

    private function replaceOnce(
        string $content,
        string $search,
        string $replacement,
        string $error,
    ): string {
        if (substr_count($content, $search) !== 1) {
            throw new RuntimeException($error);
        }
        return str_replace($search, $replacement, $content);
    }

    private function removeLineOnce(string $content, string $line, string $error): string
    {
        return $this->replaceOnce($content, $line . "\n", '', $error);
    }

    private function writeFile(string $path, string $content, int $mode): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
            throw new RuntimeException('CREATE_APP_DIRECTORY_FAILED: ' . $directory);
        }
        if (file_put_contents($path, $content) === false || !chmod($path, $mode)) {
            throw new RuntimeException('CREATE_APP_WRITE_FAILED: ' . $path);
        }
    }
}
