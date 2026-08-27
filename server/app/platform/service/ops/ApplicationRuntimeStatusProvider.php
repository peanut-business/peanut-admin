<?php
declare(strict_types=1);

namespace app\platform\service\ops;

use PDO;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\OpsConsole\Status\OpsStatusSnapshot;
use PeanutAdmin\OpsConsole\Status\RuntimeStatusProvider;
use app\platform\service\module\PdoModuleGovernanceProvider;
use think\facade\Cache;
use Throwable;

/** Application-owned runtime evidence provider for the Core Ops status contract. */
final readonly class ApplicationRuntimeStatusProvider implements RuntimeStatusProvider
{
    public function __construct(
        private PDO $pdo,
        private string $projectRoot,
    ) {
    }

    public function snapshot(PlatformContext $context): OpsStatusSnapshot
    {
        $runtime = $this->runtimeEvidence();
        $readiness = (new PlatformUpgradeReadinessService($this->pdo, $this->projectRoot))
            ->snapshot($context, $runtime);
        $backup = is_array($readiness['backup']['latest_verified'] ?? null)
            ? $readiness['backup']['latest_verified']
            : null;

        return new OpsStatusSnapshot(
            $runtime['health'],
            $runtime['checks'],
            $runtime['identity']['commit'],
            $runtime['identity']['tree'],
            $runtime['identity']['release_key'],
            $runtime['identity']['built_at'],
            $runtime['migrations']['applied'],
            $runtime['migrations']['target'],
            $runtime['migrations']['pending'],
            $runtime['migrations']['digest'],
            $runtime['migrations']['drift'],
            $readiness['state'],
            $readiness['code'],
            $runtime['identity']['commit'],
            is_array($readiness['target'] ?? null) ? (string)$readiness['target']['commit'] : null,
            $runtime['identity']['repository_clean'],
            $backup !== null,
            ($backup['source_matches_runtime'] ?? false) === true,
        );
    }

    /** @return array<string,mixed> */
    public function upgradeReadiness(PlatformContext $context): array
    {
        return (new PlatformUpgradeReadinessService($this->pdo, $this->projectRoot))
            ->snapshot($context, $this->runtimeEvidence());
    }

    public function runtimeCommit(): string
    {
        return $this->runtimeIdentity()['commit'];
    }

    /**
     * @return array{
     *   health:string,
     *   checks:list<array{key:string,status:string,critical:bool,latency_ms:float}>,
     *   identity:array{commit:string,tree:string,release_key:?string,built_at:string,repository_clean:bool},
     *   migrations:array{applied:int,target:int,pending:int,digest:string,drift:bool,files:array<string,string>}
     * }
     */
    private function runtimeEvidence(): array
    {
        $identity = $this->runtimeIdentity();
        $checks = [];

        [$databaseStatus, $databaseLatency] = $this->probe(function (): void {
            $statement = $this->pdo->query('SELECT 1');
            if ($statement === false || (int)$statement->fetchColumn() !== 1) {
                throw new \RuntimeException('database probe failed');
            }
        });
        $checks[] = $this->check('database.connection', $databaseStatus, true, $databaseLatency);

        $migrationStarted = hrtime(true);
        try {
            $migrations = $this->migrationState();
            $migrationStatus = !$migrations['drift'] && $migrations['pending'] === 0 ? 'up' : 'down';
        } catch (Throwable) {
            $migrations = $this->unavailableMigrationState();
            $migrationStatus = 'down';
        }
        $checks[] = $this->check(
            'database.migrations',
            $migrationStatus,
            true,
            $this->elapsedMilliseconds($migrationStarted)
        );

        [$moduleStatus, $moduleLatency] = $this->probe(function (): void {
            PdoModuleGovernanceProvider::forApplication($this->pdo)
                ->qualification()
                ->installedModules();
        });
        $checks[] = $this->check('module.catalog', $moduleStatus, true, $moduleLatency);

        [$cacheStatus, $cacheLatency] = $this->probe(static function (): void {
            Cache::get('peanut.ops.readonly-health');
        });
        $checks[] = $this->check('cache.read', $cacheStatus, false, $cacheLatency);

        [$storageStatus, $storageLatency] = $this->probe(function (): void {
            foreach (['server/runtime', 'server/public/storage', 'server/private/storage'] as $relative) {
                $path = $this->projectRoot . '/' . $relative;
                if (!is_dir($path) || !is_readable($path) || !is_writable($path)) {
                    throw new \RuntimeException('runtime storage unavailable');
                }
            }
        });
        $checks[] = $this->check('storage.runtime', $storageStatus, true, $storageLatency);

        $criticalDown = false;
        $anyDown = false;
        foreach ($checks as $check) {
            $isDown = $check['status'] === 'down';
            $anyDown = $anyDown || $isDown;
            $criticalDown = $criticalDown || ($isDown && $check['critical']);
        }
        $health = $criticalDown ? 'unhealthy' : ($anyDown ? 'degraded' : 'healthy');

        return [
            'health' => $health,
            'checks' => $checks,
            'identity' => $identity,
            'migrations' => $migrations,
        ];
    }

    /** @return array{commit:string,tree:string,release_key:?string,built_at:string,repository_clean:bool} */
    private function runtimeIdentity(): array
    {
        $metadata = $this->releaseMetadata();
        if (file_exists($this->projectRoot . '/.git')) {
            $commit = $this->git(['rev-parse', 'HEAD']);
            $tree = $this->git(['rev-parse', 'HEAD^{tree}']);
            // The fixed deployment-staged target is operational evidence, not
            // application source. Every other tracked/untracked change remains
            // part of the repository-clean upgrade gate.
            $clean = $this->git([
                'status', '--porcelain', '--untracked-files=all', '--', '.',
                ':(exclude).peanut/upgrade-target',
                ':(exclude).peanut/upgrade-target/**',
            ]) === '';
            $qualified = is_array($metadata['technical_qualification'] ?? null)
                ? $metadata['technical_qualification']
                : [];
            $releaseKey = ($qualified['final_candidate_commit'] ?? null) === $commit
                ? $this->releaseKey($metadata)
                : null;

            return [
                'commit' => $this->commit($commit),
                'tree' => $this->commit($tree),
                'release_key' => $releaseKey,
                'built_at' => $this->builtAt($this->projectRoot . '/server/composer.lock'),
                'repository_clean' => $clean,
            ];
        }

        $qualified = is_array($metadata['technical_qualification'] ?? null)
            ? $metadata['technical_qualification']
            : [];
        return [
            'commit' => $this->commit((string)($qualified['final_candidate_commit'] ?? '')),
            'tree' => $this->commit((string)($qualified['final_candidate_tree'] ?? '')),
            'release_key' => $this->releaseKey($metadata),
            'built_at' => $this->builtAt($this->metadataPath()),
            'repository_clean' => true,
        ];
    }

    /** @return array<string,mixed> */
    private function releaseMetadata(): array
    {
        $raw = file_get_contents($this->metadataPath());
        $decoded = is_string($raw) ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : null;
        if (!is_array($decoded)) {
            throw new \RuntimeException('OPS_RELEASE_IDENTITY_UNAVAILABLE');
        }
        return $decoded;
    }

    private function metadataPath(): string
    {
        foreach ([$this->projectRoot . '/RELEASE_METADATA.json', $this->projectRoot . '/legal/RELEASE_METADATA.json'] as $path) {
            if (is_file($path) && !is_link($path)) {
                return $path;
            }
        }
        throw new \RuntimeException('OPS_RELEASE_IDENTITY_UNAVAILABLE');
    }

    /** @param array<string,mixed> $metadata */
    private function releaseKey(array $metadata): ?string
    {
        $version = (string)($metadata['version'] ?? '');
        return preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $version) === 1
            ? 'v' . $version
            : null;
    }

    /** @param list<string> $arguments */
    private function git(array $arguments): string
    {
        $pipes = [];
        $process = proc_open(
            ['git', '-C', $this->projectRoot, ...$arguments],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('OPS_RELEASE_IDENTITY_UNAVAILABLE');
        }
        $stdout = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0 || !is_string($stdout)) {
            throw new \RuntimeException('OPS_RELEASE_IDENTITY_UNAVAILABLE');
        }
        return trim($stdout);
    }

    private function commit(string $value): string
    {
        if (preg_match('/^[a-f0-9]{40}$/D', $value) !== 1) {
            throw new \RuntimeException('OPS_RELEASE_IDENTITY_UNAVAILABLE');
        }
        return $value;
    }

    private function builtAt(string $path): string
    {
        $timestamp = filemtime($path);
        if (!is_int($timestamp)) {
            throw new \RuntimeException('OPS_RELEASE_IDENTITY_UNAVAILABLE');
        }
        return gmdate('Y-m-d\TH:i:s', $timestamp) . '.000Z';
    }

    /** @return array{applied:int,target:int,pending:int,digest:string,drift:bool,files:array<string,string>} */
    private function migrationState(): array
    {
        $expected = $this->expectedMigrations();
        $statement = $this->pdo->query(
            'SELECT migration_id, checksum, status FROM pa_schema_migration ORDER BY migration_id'
        );
        if ($statement === false) {
            throw new \RuntimeException('OPS_MIGRATION_STATUS_UNAVAILABLE');
        }
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $actual = [];
        foreach ($rows as $row) {
            $actual[(string)$row['migration_id']] = [
                'checksum' => (string)$row['checksum'],
                'status' => (string)$row['status'],
            ];
        }

        $applied = 0;
        $drift = count(array_diff_key($actual, $expected)) > 0;
        foreach ($expected as $id => $checksum) {
            $row = $actual[$id] ?? null;
            if (is_array($row)
                && $row['status'] === 'applied'
                && hash_equals($checksum, $row['checksum'])) {
                $applied++;
                continue;
            }
            if (is_array($row)) {
                $drift = true;
            }
        }

        $target = count($expected);
        return [
            'applied' => $applied,
            'target' => $target,
            'pending' => $target - $applied,
            'digest' => hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'drift' => $drift,
            'files' => $expected,
        ];
    }

    /** @return array<string,string> */
    private function expectedMigrations(): array
    {
        $directory = $this->projectRoot . '/server/database/migrations';
        $files = glob($directory . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        $expected = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (!is_file($file)
                || is_link($file)
                || preg_match('/^[0-9]{8}-[a-z0-9][a-z0-9_-]*\.sql$/D', $name) !== 1) {
                throw new \RuntimeException('OPS_MIGRATION_INVENTORY_INVALID');
            }
            $contents = file_get_contents($file);
            if (!is_string($contents) || trim($contents) === '') {
                throw new \RuntimeException('OPS_MIGRATION_INVENTORY_INVALID');
            }
            $expected[basename($name, '.sql')] = hash('sha256', $contents);
        }
        return $expected;
    }

    /** @return array{applied:int,target:int,pending:int,digest:string,drift:bool,files:array<string,string>} */
    private function unavailableMigrationState(): array
    {
        $expected = $this->expectedMigrations();
        return [
            'applied' => 0,
            'target' => count($expected),
            'pending' => count($expected),
            'digest' => hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'drift' => true,
            'files' => $expected,
        ];
    }

    /** @return array{string,float} */
    private function probe(callable $probe): array
    {
        $started = hrtime(true);
        try {
            $probe();
            return ['up', $this->elapsedMilliseconds($started)];
        } catch (Throwable) {
            return ['down', $this->elapsedMilliseconds($started)];
        }
    }

    /** @return array{key:string,status:string,critical:bool,latency_ms:float} */
    private function check(string $key, string $status, bool $critical, float $latency): array
    {
        return ['key' => $key, 'status' => $status, 'critical' => $critical, 'latency_ms' => $latency];
    }

    private function elapsedMilliseconds(int $started): float
    {
        return min(60000.0, max(0.0, round((hrtime(true) - $started) / 1_000_000, 3)));
    }
}
