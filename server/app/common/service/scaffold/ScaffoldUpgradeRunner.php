<?php
declare(strict_types=1);

namespace app\common\service\scaffold;

use RuntimeException;

final class ScaffoldUpgradeRunner
{
    /** @return array<string,mixed> */
    public function preflight(string $projectRoot, string $fromManifestPath, string $toManifestPath): array
    {
        $root = ScaffoldPathGuard::projectRoot($projectRoot);
        $from = ScaffoldManifest::load($fromManifestPath);
        $to = ScaffoldManifest::load($toManifestPath);
        if (version_compare($from->version(), $to->version(), '>=')) {
            throw new RuntimeException('SCAFFOLD_VERSION_ORDER_INVALID');
        }

        $actions = $this->classify($root, $from, $to);
        $summary = $this->summary($actions);
        $identity = [
            'source_version' => $from->version(),
            'target_version' => $to->version(),
            'source_manifest' => $from->digest(),
            'target_manifest' => $to->digest(),
            'current_tree' => $this->currentTreeDigest($root, $actions),
        ];
        $candidate = 'scaffold-' . substr(hash('sha256', self::canonicalJson($identity)), 0, 20);
        $stateRoot = ScaffoldPathGuard::projectPath($root, '.peanut/upgrades');
        $backupRoot = $stateRoot . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $candidate;
        $recovery = $this->backup($root, $backupRoot, $candidate, $actions);

        $status = $summary['conflicts'] > 0 ? 'blocked' : 'ready';
        $ledgerEntry = [
            'schema_version' => 1,
            'candidate' => $candidate,
            'source_version' => $from->version(),
            'target_version' => $to->version(),
            'plan_summary' => $summary,
            'status' => $status,
            'mode' => 'dry-run',
        ];
        $ledgerEntry = (new ScaffoldUpgradeLedger($stateRoot . DIRECTORY_SEPARATOR . 'ledger.ndjson'))
            ->appendOnce($ledgerEntry);

        $plan = [
            'schema_version' => 1,
            'protocol' => 'peanut.scaffold-upgrade-plan.v1',
            'candidate' => $candidate,
            'mode' => 'dry-run',
            'status' => $status,
            'identity' => $identity,
            'summary' => $summary,
            'executors' => [
                'backend' => ['owner' => 'backend', 'actions' => $this->countOwner($actions, 'backend')],
                'frontend' => ['owner' => 'frontend', 'actions' => $this->countOwner($actions, 'frontend')],
                'host' => ['owner' => 'host', 'actions' => $this->countOwner($actions, 'host')],
            ],
            'actions' => $actions,
            'backup' => $recovery,
            'ledger' => [
                'path' => '.peanut/upgrades/ledger.ndjson',
                'status' => $ledgerEntry['status'],
            ],
        ];
        $planPath = $stateRoot . DIRECTORY_SEPARATOR . 'plans' . DIRECTORY_SEPARATOR . $candidate . '.json';
        $this->writeStableJson($planPath, $plan);
        $plan['plan_path'] = $this->relative($root, $planPath);
        return $plan;
    }

    /** @return array<int,array<string,mixed>> */
    private function classify(string $root, ScaffoldManifest $from, ScaffoldManifest $to): array
    {
        $oldFiles = $from->files();
        $newFiles = $to->files();
        $actions = [];
        $renamedFrom = [];
        $renamedTo = [];
        foreach ($to->renames() as $rename) {
            $fromPath = (string)$rename['from'];
            $toPath = (string)$rename['to'];
            $renamedFrom[$fromPath] = true;
            $renamedTo[$toPath] = true;
            $actions[] = $this->classifyRename($root, $fromPath, $toPath, $rename, $oldFiles, $newFiles, $to);
        }

        $paths = array_unique(array_merge(array_keys($oldFiles), array_keys($newFiles)));
        sort($paths, SORT_STRING);
        foreach ($paths as $path) {
            if (isset($renamedFrom[$path]) || isset($renamedTo[$path])) {
                continue;
            }
            $old = $oldFiles[$path] ?? null;
            $new = $newFiles[$path] ?? null;
            $actions[] = $this->classifyPath($root, $path, $old, $new, $to);
        }
        return $actions;
    }

    /** @param array<string,mixed>|null $old @param array<string,mixed>|null $new */
    private function classifyPath(string $root, string $path, ?array $old, ?array $new, ScaffoldManifest $to): array
    {
        $projectPath = ScaffoldPathGuard::projectPath($root, $path);
        $exists = is_file($projectPath);
        if (file_exists($projectPath) && !$exists) {
            return $this->action($path, $new ?? $old ?? [], 'conflict', 'path_type_collision', true);
        }
        $current = $exists ? hash_file('sha256', $projectPath) : null;
        if ($old === null && $new !== null) {
            $this->verifyArtifact($to, $new);
            return $exists
                ? $this->action($path, $new, 'conflict', 'new_path_already_exists', true, $current)
                : $this->action($path, $new, $new['policy'] === 'manual' ? 'manual' : 'create', 'new_managed_file', false);
        }
        if ($old !== null && $new === null) {
            return $this->action($path, $old, $exists && $current !== $old['content_sha256'] ? 'conflict' : 'deprecated',
                $exists && $current !== $old['content_sha256'] ? 'project_modified_while_upstream_removed' : 'removed_from_target_manifest',
                $exists && $current !== $old['content_sha256'], $current);
        }
        if ($old === null || $new === null) {
            throw new RuntimeException('SCAFFOLD_CLASSIFICATION_STATE_INVALID');
        }
        if (($new['policy'] ?? null) === 'deprecated') {
            $modified = !$exists || $current !== $old['content_sha256'];
            return $this->action($path, $new, $modified ? 'conflict' : 'deprecated',
                $modified ? 'project_modified_while_upstream_deprecated' : 'deprecated_migration_required', $modified, $current);
        }
        $this->verifyArtifact($to, $new);
        if (!$exists) {
            return $this->action($path, $new, 'conflict', 'managed_file_missing', true);
        }
        $projectChanged = $current !== $old['content_sha256'];
        $upstreamChanged = $new['content_sha256'] !== $old['content_sha256'];
        if ($projectChanged && $upstreamChanged) {
            return $this->action($path, $new, 'conflict', 'both_project_and_upstream_modified', true, $current);
        }
        if ($projectChanged) {
            $action = match ($new['policy']) {
                'merge' => 'merge',
                'manual' => 'manual',
                default => 'preserve',
            };
            return $this->action($path, $new, $action, 'project_modified_only', false, $current);
        }
        if (!$upstreamChanged) {
            return $this->action($path, $new, 'unchanged', 'no_changes', false, $current);
        }
        $action = match ($new['policy']) {
            'preserve' => 'preserve',
            'manual' => 'manual',
            'generated' => 'regenerate',
            default => 'replace',
        };
        return $this->action($path, $new, $action, 'upstream_modified_only', false, $current);
    }

    /** @param array<string,mixed> $rename @param array<string,array<string,mixed>> $oldFiles @param array<string,array<string,mixed>> $newFiles */
    private function classifyRename(string $root, string $fromPath, string $toPath, array $rename, array $oldFiles, array $newFiles, ScaffoldManifest $to): array
    {
        $old = $oldFiles[$fromPath] ?? null;
        $new = $newFiles[$toPath] ?? null;
        if (!is_array($old) || !is_array($new)) {
            throw new RuntimeException("SCAFFOLD_RENAME_TARGET_INVALID: {$fromPath} -> {$toPath}");
        }
        $this->verifyArtifact($to, $new);
        $source = ScaffoldPathGuard::projectPath($root, $fromPath);
        $target = ScaffoldPathGuard::projectPath($root, $toPath);
        $sourceDigest = is_file($source) ? hash_file('sha256', $source) : null;
        $targetExists = file_exists($target);
        $projectChanged = $sourceDigest !== $old['content_sha256'];
        $upstreamChanged = $new['content_sha256'] !== $old['content_sha256'];
        $conflict = !is_file($source) || $targetExists || ($projectChanged && $upstreamChanged);
        $reason = !is_file($source) ? 'rename_source_missing'
            : ($targetExists ? 'rename_target_exists'
                : (($projectChanged && $upstreamChanged) ? 'both_project_and_upstream_modified' : 'explicit_rename'));
        $file = $new + ['owner' => $rename['owner'], 'renamed_from' => $fromPath];
        return $this->action($toPath, $file, $conflict ? 'conflict' : ($projectChanged ? 'preserve_rename' : 'rename'), $reason, $conflict, $sourceDigest);
    }

    /** @param array<string,mixed> $file @return array<string,mixed> */
    private function action(string $path, array $file, string $action, string $reason, bool $conflict, ?string $current = null): array
    {
        $result = [
            'path' => $path,
            'owner' => (string)($file['owner'] ?? 'host'),
            'policy' => (string)($file['policy'] ?? 'manual'),
            'action' => $action,
            'reason' => $reason,
            'conflict' => $conflict,
        ];
        foreach (['renamed_from', 'renamed_to', 'migration_hint'] as $optional) {
            if (isset($file[$optional])) {
                $result[$optional] = $file[$optional];
            }
        }
        if ($current !== null) {
            $result['current_sha256'] = $current;
        }
        if (isset($file['content_sha256'])) {
            $result['target_sha256'] = $file['content_sha256'];
        }
        return $result;
    }

    /** @param array<string,mixed> $file */
    private function verifyArtifact(ScaffoldManifest $manifest, array $file): void
    {
        if (($file['policy'] ?? null) === 'deprecated') {
            return;
        }
        $actual = hash_file('sha256', $manifest->artifactPath($file));
        if (!hash_equals((string)$file['content_sha256'], $actual)) {
            throw new RuntimeException('SCAFFOLD_ARTIFACT_DIGEST_MISMATCH: ' . $file['path']);
        }
    }

    /** @param array<int,array<string,mixed>> $actions @return array<string,int> */
    private function summary(array $actions): array
    {
        $summary = ['total' => count($actions), 'automatic' => 0, 'preserved' => 0, 'manual' => 0, 'deprecated' => 0, 'conflicts' => 0];
        foreach ($actions as $action) {
            if ($action['conflict']) {
                $summary['conflicts']++;
                continue;
            }
            match ($action['action']) {
                'create', 'replace', 'regenerate', 'rename' => $summary['automatic']++,
                'preserve', 'preserve_rename', 'unchanged' => $summary['preserved']++,
                'deprecated' => $summary['deprecated']++,
                default => $summary['manual']++,
            };
        }
        return $summary;
    }

    /** @param array<int,array<string,mixed>> $actions */
    private function currentTreeDigest(string $root, array $actions): string
    {
        $state = [];
        foreach ($actions as $action) {
            $paths = isset($action['renamed_from']) ? [$action['renamed_from'], $action['path']] : [$action['path']];
            foreach ($paths as $relative) {
                $path = ScaffoldPathGuard::projectPath($root, (string)$relative);
                $state[(string)$relative] = is_file($path) ? hash_file('sha256', $path) : null;
            }
        }
        ksort($state, SORT_STRING);
        return 'sha256:' . hash('sha256', self::canonicalJson($state));
    }

    /** @param array<int,array<string,mixed>> $actions @return array<string,mixed> */
    private function backup(string $root, string $backupRoot, string $candidate, array $actions): array
    {
        $files = [];
        foreach ($actions as $action) {
            $relatives = isset($action['renamed_from']) ? [$action['renamed_from']] : [$action['path']];
            foreach ($relatives as $relative) {
                $source = ScaffoldPathGuard::projectPath($root, (string)$relative);
                if (!is_file($source)) {
                    continue;
                }
                $destination = $backupRoot . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string)$relative);
                ScaffoldPathGuard::ensureDirectory(dirname($destination));
                if (is_file($destination)) {
                    if (!hash_equals(hash_file('sha256', $source), hash_file('sha256', $destination))) {
                        throw new RuntimeException("SCAFFOLD_BACKUP_COLLISION: {$relative}");
                    }
                } elseif (!copy($source, $destination)) {
                    throw new RuntimeException("SCAFFOLD_BACKUP_FAILED: {$relative}");
                }
                $files[(string)$relative] = [
                    'backup' => 'files/' . $relative,
                    'sha256' => hash_file('sha256', $source),
                    'restore' => 'copy backup over project path',
                ];
            }
        }
        ksort($files, SORT_STRING);
        $recovery = [
            'schema_version' => 1,
            'candidate' => $candidate,
            'project_root' => '.',
            'files' => $files,
            'note' => 'Dry-run did not change project files; this inventory is ready for a later apply executor.',
        ];
        $path = $backupRoot . DIRECTORY_SEPARATOR . 'recovery.json';
        $this->writeStableJson($path, $recovery);
        return ['path' => $this->relative($root, $path), 'files' => count($files)];
    }

    /** @param array<int,array<string,mixed>> $actions */
    private function countOwner(array $actions, string $owner): int
    {
        return count(array_filter($actions, static fn(array $action): bool => $action['owner'] === $owner));
    }

    /** @param array<string,mixed> $data */
    private function writeStableJson(string $path, array $data): void
    {
        ScaffoldPathGuard::ensureDirectory(dirname($path));
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (is_file($path) && file_get_contents($path) === $json) {
            return;
        }
        $temporary = $path . '.tmp-' . getmypid();
        if (file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException("SCAFFOLD_STATE_WRITE_FAILED: {$path}");
        }
    }

    private function relative(string $root, string $path): string
    {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root) + 1));
    }

    /** @param array<string,mixed> $value */
    private static function canonicalJson(array $value): string
    {
        self::sortRecursive($value);
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @param array<string,mixed> $value */
    private static function sortRecursive(array &$value): void
    {
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as &$item) {
            if (is_array($item)) {
                self::sortRecursive($item);
            }
        }
    }
}
