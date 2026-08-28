<?php
declare(strict_types=1);

namespace app\common\service\scaffold;

use RuntimeException;
use Throwable;

final class ScaffoldUpgradeRunner
{
    /**
     * Build the immutable scaffold plan without writing a plan file or ledger event.
     *
     * The Platform upgrade-readiness projection uses this method so a read request
     * can evaluate exactly the same ownership/conflict rules as the CLI preflight.
     */
    public function preview(string $projectRoot, string $fromManifestPath, string $toManifestPath): array
    {
        $root = ScaffoldPathGuard::projectRoot($projectRoot);
        [$application, $applicationDigest] = $this->applicationManifest($root);
        $from = ScaffoldManifest::load($fromManifestPath);
        $to = ScaffoldManifest::load($toManifestPath);
        $this->assertReleaseChain($application, $from, $to);
        $parameters = $this->parameters($application);
        $actions = $this->classify($root, $from, $to, $parameters);
        $summary = $this->summary($actions);
        $appOwnedState = $this->ownershipState($root, $application, 'app-owned');
        $managedState = $this->actionState($root, $actions);
        $identity = [
            'from' => $this->releaseIdentity($from),
            'to' => $this->releaseIdentity($to),
            'application_version' => $application['application']['version'],
            'application_manifest_sha256' => $applicationDigest,
            'managed_pre_sha256' => $managedState['digest'],
            'app_owned_pre_sha256' => $appOwnedState['digest'],
        ];
        $candidate = 'scaffold-' . substr(hash('sha256', self::canonicalJson([$identity, $actions])), 0, 24);
        $status = $summary['conflicts'] === 0 ? 'ready' : 'blocked';
        return [
            'schema_version' => 2,
            'protocol' => 'peanut.scaffold-upgrade-plan.v2',
            'candidate' => $candidate,
            'status' => $status,
            'identity' => $identity,
            'manifest_paths' => ['from' => $from->path, 'to' => $to->path],
            'summary' => $summary,
            'managed_pre_state' => $managedState['files'],
            'app_owned_pre_state' => $appOwnedState['files'],
            'actions' => $actions,
        ];
    }

    public function preflight(string $projectRoot, string $fromManifestPath, string $toManifestPath): array
    {
        $root = ScaffoldPathGuard::projectRoot($projectRoot);
        $plan = $this->preview($root, $fromManifestPath, $toManifestPath);
        $stateRoot = ScaffoldPathGuard::projectPath($root, '.peanut/upgrades');
        $path = $stateRoot . '/plans/' . $plan['candidate'] . '.json';
        $this->writeJsonAtomic($path, $plan, 0600);
        $ledger = new ScaffoldUpgradeLedger($stateRoot . '/ledger.ndjson');
        if (!$this->hasEvent($ledger, $plan['candidate'], 'preflight', $plan['status'])) {
            $ledger->append($this->event(
                $plan,
                'preflight',
                $plan['status'],
                $plan['identity']['managed_pre_sha256'],
                null
            ));
        }
        return $plan + ['plan_path' => $this->relative($root, $path)];
    }

    public function apply(string $projectRoot, string $planPath): array
    {
        return $this->locked($projectRoot, function (string $root) use ($planPath): array {
            $plan = $this->loadPlan($root, $planPath);
            $ledger = $this->ledger($root);
            if ($plan['status'] !== 'ready') throw new RuntimeException('SCAFFOLD_PLAN_BLOCKED');
            if (in_array($this->candidateState($ledger, $plan['candidate']), ['applied', 'verified'], true)) {
                return ['status' => 'applied', 'candidate' => $plan['candidate'], 'idempotent' => true];
            }
            $this->assertPlanFresh($root, $plan);
            $to = ScaffoldManifest::load($plan['manifest_paths']['to']);
            $this->assertManifestDigest($to, $plan['identity']['to']['manifest_sha256']);
            $recovery = $this->createRecovery($root, $plan);
            $pre = $plan['identity']['managed_pre_sha256'];
            $ledger->append($this->event($plan, 'apply', 'started', $pre, null));
            try {
                $writes = 0;
                foreach ($plan['actions'] as $action) {
                    if ($action['action'] !== 'delete') continue;
                    $this->assertActionFresh($root, $action);
                    $target = ScaffoldPathGuard::projectPath($root, $action['path']);
                    if (!unlink($target)) throw new RuntimeException('SCAFFOLD_ATOMIC_DELETE_FAILED: ' . $action['path']);
                    $this->pruneEmptyParents(dirname($target), $root);
                    $writes++;
                    $failAfter = getenv('PEANUT_SCAFFOLD_FAIL_AFTER_REPLACEMENTS');
                    if ($failAfter !== false && ctype_digit($failAfter) && $writes >= (int)$failAfter) {
                        throw new RuntimeException('SCAFFOLD_FAULT_INJECTED');
                    }
                }
                foreach ($plan['actions'] as $action) {
                    if (!in_array($action['action'], ['create', 'replace', 'regenerate'], true)) continue;
                    $this->assertActionFresh($root, $action);
                    $artifact = $this->targetContent($root, $to, $action);
                    $this->writeFileAtomic(ScaffoldPathGuard::projectPath($root, $action['path']), $artifact, (int)$action['mode']);
                    $baseline = '.peanut/scaffold-baseline/' . $to->version() . '/files/' . $action['path'];
                    $this->writeFileAtomic(ScaffoldPathGuard::projectPath($root, $baseline), $artifact, 0644);
                    $writes++;
                    $failAfter = getenv('PEANUT_SCAFFOLD_FAIL_AFTER_REPLACEMENTS');
                    if ($failAfter !== false && ctype_digit($failAfter) && $writes >= (int)$failAfter) {
                        throw new RuntimeException('SCAFFOLD_FAULT_INJECTED');
                    }
                }
                foreach ($plan['actions'] as $action) {
                    if ($action['action'] !== 'preserve') continue;
                    $this->assertActionFresh($root, $action);
                    $artifact = $this->targetContent($root, $to, $action);
                    $baseline = '.peanut/scaffold-baseline/' . $to->version() . '/files/' . $action['path'];
                    $this->writeFileAtomic(ScaffoldPathGuard::projectPath($root, $baseline), $artifact, 0644);
                }
                $manifest = $this->nextApplicationManifest($root, $plan, $to);
                $this->writeJsonAtomic(ScaffoldPathGuard::projectPath($root, '.peanut/application-manifest.json'), $manifest, 0644);
                $post = $this->managedDigestFromManifest($root, $manifest);
                $ledger->append($this->event($plan, 'apply', 'applied', $pre, $post, ['recovery' => $recovery]));
                return ['status' => 'applied', 'candidate' => $plan['candidate'], 'managed_post_sha256' => $post, 'recovery' => $recovery, 'idempotent' => false];
            } catch (Throwable $exception) {
                $ledger->append($this->event($plan, 'apply', 'failed', $pre, null, ['error' => $exception->getMessage(), 'recovery' => $recovery]));
                throw $exception;
            }
        });
    }

    public function verify(string $projectRoot, string $planPath): array
    {
        return $this->locked($projectRoot, function (string $root) use ($planPath): array {
            $plan = $this->loadPlan($root, $planPath);
            $ledger = $this->ledger($root);
            if ($this->candidateState($ledger, $plan['candidate']) === 'verified') {
                return ['status' => 'verified', 'candidate' => $plan['candidate'], 'idempotent' => true];
            }
            if (!in_array($this->candidateState($ledger, $plan['candidate']), ['applied','verified'], true)) throw new RuntimeException('SCAFFOLD_APPLY_NOT_COMMITTED');
            [$application] = $this->applicationManifest($root);
            $to = ScaffoldManifest::load($plan['manifest_paths']['to']);
            if (($application['template']['version'] ?? null) !== $to->version()
                || ($application['template']['source_commit'] ?? null) !== $to->release()['source_commit']
                || ($application['template']['source_tree'] ?? null) !== $to->release()['source_tree']
                || ($application['application']['version'] ?? null) !== $plan['identity']['application_version']) {
                throw new RuntimeException('SCAFFOLD_VERIFY_APPLICATION_IDENTITY_MISMATCH');
            }
            $actualAppOwned = $this->ownershipState($root, ['files' => array_values(array_filter($application['files'], static fn(array $file): bool => $file['classification'] === 'app-owned'))], 'app-owned');
            if (!hash_equals($plan['identity']['app_owned_pre_sha256'], $actualAppOwned['digest'])) throw new RuntimeException('SCAFFOLD_VERIFY_APP_OWNED_CHANGED');
            foreach ($application['files'] as $file) {
                if (!in_array($file['classification'], ['managed', 'generated-managed'], true)) continue;
                $path = ScaffoldPathGuard::projectPath($root, $file['path']);
                if (!is_file($path) || !hash_equals($file['sha256'], (string)hash_file('sha256', $path)) || ((fileperms($path) & 0777) !== ($file['mode'] ?? 0644))) {
                    throw new RuntimeException('SCAFFOLD_VERIFY_MANAGED_MISMATCH: ' . $file['path']);
                }
            }
            $post = $this->managedDigestFromManifest($root, $application);
            $ledger->append($this->event($plan, 'verify', 'verified', $plan['identity']['managed_pre_sha256'], $post));
            return ['status' => 'verified', 'candidate' => $plan['candidate'], 'managed_post_sha256' => $post, 'app_owned_sha256' => $actualAppOwned['digest'], 'idempotent' => false];
        });
    }

    public function recover(string $projectRoot, string $planPath): array
    {
        return $this->locked($projectRoot, function (string $root) use ($planPath): array {
            $plan = $this->loadPlan($root, $planPath);
            $ledger = $this->ledger($root);
            $manifestPath = ScaffoldPathGuard::projectPath($root, '.peanut/upgrades/backups/' . $plan['candidate'] . '/recovery.json');
            if (!is_file($manifestPath)) throw new RuntimeException('SCAFFOLD_RECOVERY_NOT_FOUND');
            $recovery = json_decode((string)file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($recovery) || ($recovery['candidate'] ?? null) !== $plan['candidate'] || !is_array($recovery['files'] ?? null)
                || !hash_equals((string)($recovery['pre_tree_sha256'] ?? ''), 'sha256:' . hash('sha256', self::canonicalJson($recovery['files'])))) {
                throw new RuntimeException('SCAFFOLD_RECOVERY_INVALID');
            }
            $already = $this->recoveryMatches($root, $recovery);
            if (!$already) {
                foreach ($recovery['files'] as $relative => $state) {
                    $target = ScaffoldPathGuard::projectPath($root, (string)$relative);
                    if ($state['present']) {
                        $backup = ScaffoldPathGuard::existingFileWithin(dirname($manifestPath), dirname($manifestPath) . '/' . $state['backup'], 'SCAFFOLD_RECOVERY_BACKUP_INVALID');
                        $content = file_get_contents($backup);
                        if (!is_string($content) || !hash_equals($state['sha256'], hash('sha256', $content))) throw new RuntimeException('SCAFFOLD_RECOVERY_BACKUP_DRIFT');
                        $this->writeFileAtomic($target, $content, (int)$state['mode']);
                    } elseif (file_exists($target)) {
                        if (!is_file($target) || is_link($target)) throw new RuntimeException('SCAFFOLD_RECOVERY_PATH_COLLISION: ' . $relative);
                        unlink($target);
                        $this->pruneEmptyParents(dirname($target), $root);
                    }
                }
                if (!$this->recoveryMatches($root, $recovery)) throw new RuntimeException('SCAFFOLD_RECOVERY_VERIFY_FAILED');
            }
            if (!$this->hasEvent($ledger, $plan['candidate'], 'recover', 'recovered')) {
                $ledger->append($this->event($plan, 'recover', 'recovered', $plan['identity']['managed_pre_sha256'], $plan['identity']['managed_pre_sha256']));
            }
            return ['status' => 'recovered', 'candidate' => $plan['candidate'], 'tree_sha256' => $recovery['pre_tree_sha256'], 'idempotent' => $already];
        });
    }

    private function assertReleaseChain(array $application, ScaffoldManifest $from, ScaffoldManifest $to): void
    {
        if (version_compare($from->version(), $to->version(), '>=') || ($application['template']['version'] ?? null) !== $from->version()
            || ($application['template']['source_commit'] ?? null) !== $from->release()['source_commit']
            || ($application['template']['source_tree'] ?? null) !== $from->release()['source_tree']) {
            throw new RuntimeException('SCAFFOLD_RELEASE_CHAIN_INVALID');
        }
    }

    private function classify(string $root, ScaffoldManifest $from, ScaffoldManifest $to, array $parameters): array
    {
        if ($from->renames() !== [] || $to->renames() !== []) throw new RuntimeException('SCAFFOLD_RENAME_UNSUPPORTED');
        $old = $from->files(); $new = $to->files(); $actions = [];
        $paths = array_unique(array_merge(array_keys($old), array_keys($new))); sort($paths, SORT_STRING);
        foreach ($paths as $path) {
            $before = $old[$path] ?? null; $after = $new[$path] ?? null;
            if ($after === null) {
                $projectPath = ScaffoldPathGuard::projectPath($root, $path);
                $current = $this->regularFileState($projectPath, $path);
                if (!$current['present']) {
                    $actions[] = $this->action($path, $before ?? [], 'conflict', 'managed_file_missing', true, $current, null);
                    continue;
                }
                $oldContent = $this->renderArtifact($from, $before, $parameters);
                $oldDigest = hash('sha256', $oldContent);
                $actions[] = hash_equals($oldDigest, $current['sha256'])
                    ? $this->action($path, $before, 'delete', 'upstream_removed_only', false, $current, null)
                    : $this->action($path, $before, 'conflict', 'project_modified_upstream_removed', true, $current, null);
                continue;
            }
            $targetContent = $this->renderArtifact($to, $after, $parameters);
            $targetDigest = hash('sha256', $targetContent);
            $projectPath = ScaffoldPathGuard::projectPath($root, $path);
            $current = $this->regularFileState($projectPath, $path);
            if ($before === null) {
                $actions[] = $current['present']
                    ? $this->action($path, $after, 'conflict', 'new_path_already_exists', true, $current, $targetDigest)
                    : $this->action($path, $after, 'create', 'new_managed_file', false, $current, $targetDigest);
                continue;
            }
            $oldContent = $this->renderArtifact($from, $before, $parameters);
            $oldDigest = hash('sha256', $oldContent);
            if (!$current['present']) {
                $actions[] = $this->action($path, $after, 'conflict', 'managed_file_missing', true, $current, $targetDigest);
                continue;
            }
            if (hash_equals($targetDigest, $current['sha256']) && ($current['mode'] ?? null) === ($after['mode'] ?? null)) {
                $actions[] = $this->action($path, $after, 'preserve', 'already_at_target', false, $current, $targetDigest);
                continue;
            }
            $projectChanged = !hash_equals($oldDigest, $current['sha256']);
            $upstreamChanged = !hash_equals($oldDigest, $targetDigest) || ($before['mode'] ?? null) !== ($after['mode'] ?? null);
            if ($projectChanged && $upstreamChanged) $actions[] = $this->action($path, $after, 'conflict', 'both_project_and_upstream_modified', true, $current, $targetDigest);
            elseif ($projectChanged) $actions[] = $this->action($path, $after, 'preserve', 'project_modified_only', false, $current, $targetDigest);
            elseif ($upstreamChanged) $actions[] = $this->action($path, $after, $after['classification'] === 'generated-managed' ? 'regenerate' : 'replace', 'upstream_modified_only', false, $current, $targetDigest);
            else $actions[] = $this->action($path, $after, 'preserve', 'unchanged', false, $current, $targetDigest);
        }
        return $actions;
    }

    private function action(string $path, array $file, string $action, string $reason, bool $conflict, ?array $current, ?string $target): array
    {
        return ['path' => $path, 'classification' => $file['classification'] ?? 'managed', 'owner' => $file['owner'] ?? 'host',
            'policy' => $file['policy'] ?? 'managed', 'transform' => $file['transform'] ?? 'copy', 'mode' => $file['mode'] ?? 0644,
            'source' => $file['source'] ?? null, 'template_sha256' => $file['template_sha256'] ?? null, 'action' => $action,
            'reason' => $reason, 'conflict' => $conflict, 'current' => $current, 'target_sha256' => $target];
    }

    private function renderArtifact(ScaffoldManifest $manifest, array $file, array $parameters): string
    {
        $path = $manifest->artifactPath($file);
        $raw = file_get_contents($path);
        if (!is_string($raw) || !hash_equals($file['template_sha256'], hash('sha256', $raw))) throw new RuntimeException('SCAFFOLD_ARTIFACT_DIGEST_MISMATCH: ' . $file['path']);
        $tokens=$manifest->release()['tokens'];
        $expectedKeys=$manifest->supportsApplicationVersion()
            ? ['product_name','slug','package_identity','application_version']
            : ['product_name','slug','package_identity'];
        if(array_keys($tokens)!==$expectedKeys)throw new RuntimeException('SCAFFOLD_RELEASE_TOKENS_INVALID');
        $values=[
            'product_name'=>$parameters['PRODUCT_NAME'],
            'slug'=>$parameters['SLUG'],
            'package_identity'=>$parameters['PACKAGE_IDENTITY'],
            'application_version'=>$parameters['APPLICATION_VERSION'],
        ];
        $rendered=$raw;
        foreach($tokens as $key=>$token)$rendered=str_replace($token,$values[$key],$rendered);
        return $rendered;
    }

    private function targetContent(string $root, ScaffoldManifest $manifest, array $action): string
    {
        [$application] = $this->applicationManifest($root);
        return $this->renderArtifact($manifest, $action, $this->parameters($application));
    }

    private function summary(array $actions): array
    {
        $summary = ['total' => count($actions), 'automatic' => 0, 'preserved' => 0, 'conflicts' => 0];
        foreach ($actions as $action) $action['conflict'] ? $summary['conflicts']++ : (in_array($action['action'], ['create','delete','replace','regenerate'], true) ? $summary['automatic']++ : $summary['preserved']++);
        return $summary;
    }

    private function parameters(array $application): array
    {
        return [
            'APPLICATION_VERSION' => (string)$application['application']['version'],
            'PACKAGE_IDENTITY' => (string)$application['application']['package_identity'],
            'PRODUCT_NAME' => (string)$application['application']['name'],
            'SLUG' => (string)$application['application']['slug'],
        ];
    }

    private function releaseIdentity(ScaffoldManifest $manifest): array { return $manifest->release() + ['manifest_sha256' => $manifest->digest()]; }
    private function assertManifestDigest(ScaffoldManifest $manifest, string $digest): void { if (!hash_equals($digest, $manifest->digest())) throw new RuntimeException('SCAFFOLD_MANIFEST_CHECKSUM_DRIFT'); }

    private function applicationManifest(string $root): array
    {
        $path = ScaffoldPathGuard::projectPath($root, '.peanut/application-manifest.json');
        if (!is_file($path) || is_link($path)) throw new RuntimeException('SCAFFOLD_APPLICATION_MANIFEST_MISSING');
        $raw = file_get_contents($path); $data = is_string($raw) ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : null;
        if (!is_array($data) || !is_array($data['application'] ?? null) || !is_array($data['files'] ?? null)) {
            throw new RuntimeException('SCAFFOLD_APPLICATION_MANIFEST_INVALID');
        }
        $protocol = $data['protocol'] ?? null;
        if (($data['schema_version'] ?? null) === 2 && $protocol === 'peanut.application-scaffold.v2') {
            $version = $data['application']['version'] ?? null;
        } elseif (($data['schema_version'] ?? null) === 1 && $protocol === 'peanut.application-scaffold.v1') {
            $version = $this->legacyApplicationVersion($root);
            $data['application']['version'] = $version;
        } else {
            throw new RuntimeException('SCAFFOLD_APPLICATION_MANIFEST_INVALID');
        }
        if (preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:[-+][0-9A-Za-z.-]+)?$/D', (string)$version) !== 1) {
            throw new RuntimeException('SCAFFOLD_APPLICATION_VERSION_INVALID');
        }
        return [$data, 'sha256:' . hash('sha256', (string)$raw)];
    }

    private function legacyApplicationVersion(string $root): string
    {
        $path = ScaffoldPathGuard::projectPath($root, 'RELEASE_METADATA.json');
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('SCAFFOLD_LEGACY_APPLICATION_VERSION_UNAVAILABLE');
        }
        try {
            $metadata = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('SCAFFOLD_LEGACY_APPLICATION_VERSION_UNAVAILABLE', 0, $exception);
        }
        $candidates = [];
        if (is_array($metadata) && is_string($metadata['version'] ?? null)) {
            $candidates[] = $metadata['version'];
        }
        if (is_array($metadata['application'] ?? null) && is_string($metadata['application']['version'] ?? null)) {
            $candidates[] = $metadata['application']['version'];
        }
        $candidates = array_values(array_unique($candidates, SORT_STRING));
        if (count($candidates) !== 1
            || preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:[-+][0-9A-Za-z.-]+)?$/D', $candidates[0]) !== 1) {
            throw new RuntimeException('SCAFFOLD_LEGACY_APPLICATION_VERSION_AMBIGUOUS');
        }
        return $candidates[0];
    }

    private function regularFileState(string $path, string $relative): array
    {
        if (!file_exists($path)) return ['present' => false, 'sha256' => null, 'mode' => null];
        if (!is_file($path) || is_link($path)) throw new RuntimeException('SCAFFOLD_PATH_TYPE_REJECTED: ' . $relative);
        $stat = lstat($path);
        if (!is_array($stat) || ($stat['nlink'] ?? 0) !== 1) throw new RuntimeException('SCAFFOLD_PATH_HARDLINK_REJECTED: ' . $relative);
        return ['present' => true, 'sha256' => hash_file('sha256', $path), 'mode' => fileperms($path) & 0777];
    }

    private function actionState(string $root, array $actions): array
    {
        $files = [];
        foreach ($actions as $action) $files[$action['path']] = $this->regularFileState(ScaffoldPathGuard::projectPath($root, $action['path']), $action['path']);
        ksort($files, SORT_STRING); return ['files' => $files, 'digest' => 'sha256:' . hash('sha256', self::canonicalJson($files))];
    }

    private function ownershipState(string $root, array $application, string $classification): array
    {
        $files = [];
        foreach ($application['files'] as $file) if (($file['classification'] ?? null) === $classification) $files[$file['path']] = $this->regularFileState(ScaffoldPathGuard::projectPath($root, $file['path']), $file['path']);
        ksort($files, SORT_STRING); return ['files' => $files, 'digest' => 'sha256:' . hash('sha256', self::canonicalJson($files))];
    }

    private function loadPlan(string $root, string $path): array
    {
        $resolved = ScaffoldPathGuard::existingFileWithin($root, $path, 'SCAFFOLD_PLAN_PATH_INVALID');
        $data = json_decode((string)file_get_contents($resolved), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['protocol'] ?? null) !== 'peanut.scaffold-upgrade-plan.v2' || preg_match('/^scaffold-[a-f0-9]{24}$/D', (string)($data['candidate'] ?? '')) !== 1) throw new RuntimeException('SCAFFOLD_PLAN_INVALID');
        $expected='scaffold-'.substr(hash('sha256',self::canonicalJson([$data['identity']??null,$data['actions']??null])),0,24);
        if(!hash_equals($expected,$data['candidate']))throw new RuntimeException('SCAFFOLD_PLAN_CHECKSUM_DRIFT');
        $expectedStatus=count(array_filter($data['actions'],static fn(array $action):bool=>($action['conflict']??null)===true))===0?'ready':'blocked';
        if(($data['status']??null)!==$expectedStatus)throw new RuntimeException('SCAFFOLD_PLAN_STATUS_DRIFT');
        return $data;
    }

    private function assertActionFresh(string $root, array $action): void
    {
        $actual=$this->regularFileState(ScaffoldPathGuard::projectPath($root,$action['path']),$action['path']);
        if($actual!==$action['current'])throw new RuntimeException('SCAFFOLD_PLAN_PROJECT_CHANGED: '.$action['path']);
    }

    private function assertPlanFresh(string $root, array $plan): void
    {
        [, $manifestDigest] = $this->applicationManifest($root);
        if (!hash_equals($plan['identity']['application_manifest_sha256'], $manifestDigest)) throw new RuntimeException('SCAFFOLD_PLAN_APPLICATION_LOCK_CHANGED');
        $managed = $this->actionState($root, $plan['actions']);
        if (!hash_equals($plan['identity']['managed_pre_sha256'], $managed['digest'])) throw new RuntimeException('SCAFFOLD_PLAN_PROJECT_CHANGED');
        [$application] = $this->applicationManifest($root); $app = $this->ownershipState($root, $application, 'app-owned');
        if (!hash_equals($plan['identity']['app_owned_pre_sha256'], $app['digest'])) throw new RuntimeException('SCAFFOLD_PLAN_PROJECT_CHANGED');
        $this->assertManifestDigest(ScaffoldManifest::load($plan['manifest_paths']['from']), $plan['identity']['from']['manifest_sha256']);
        $this->assertManifestDigest(ScaffoldManifest::load($plan['manifest_paths']['to']), $plan['identity']['to']['manifest_sha256']);
    }

    private function ledger(string $root): ScaffoldUpgradeLedger { return new ScaffoldUpgradeLedger(ScaffoldPathGuard::projectPath($root, '.peanut/upgrades/ledger.ndjson')); }
    private function hasEvent(ScaffoldUpgradeLedger $ledger, string $candidate, string $operation, string $status): bool { foreach ($ledger->entries($candidate) as $entry) if (($entry['operation'] ?? null) === $operation && ($entry['status'] ?? null) === $status) return true; return false; }
    private function candidateState(ScaffoldUpgradeLedger $ledger, string $candidate): ?string { $entries=$ledger->entries($candidate); return $entries === [] ? null : (string)($entries[array_key_last($entries)]['status'] ?? ''); }
    private function event(array $plan, string $operation, string $status, ?string $pre, ?string $post, array $extra = []): array { return ['schema_version'=>2,'candidate'=>$plan['candidate'],'operation'=>$operation,'status'=>$status,'from'=>$plan['identity']['from'],'to'=>$plan['identity']['to'],'pre_sha256'=>$pre,'post_sha256'=>$post] + $extra; }

    private function locked(string $projectRoot, callable $operation): array
    {
        $root = ScaffoldPathGuard::projectRoot($projectRoot); $path = ScaffoldPathGuard::projectPath($root, '.peanut/upgrades/project.lock'); ScaffoldPathGuard::ensureDirectory(dirname($path));
        $handle = fopen($path, 'c+b'); if ($handle === false || !flock($handle, LOCK_EX)) throw new RuntimeException('SCAFFOLD_PROJECT_LOCK_FAILED');
        try { return $operation($root); } finally { flock($handle, LOCK_UN); fclose($handle); }
    }

    private function createRecovery(string $root, array $plan): string
    {
        $directory = ScaffoldPathGuard::projectPath($root, '.peanut/upgrades/backups/' . $plan['candidate']);
        ScaffoldPathGuard::ensureDirectory($directory . '/files');
        chmod($directory, 0700); chmod($directory . '/files', 0700);
        $paths = ['.peanut/application-manifest.json'];
        foreach ($plan['actions'] as $action) {
            $paths[] = $action['path'];
            $paths[] = '.peanut/scaffold-baseline/' . $plan['identity']['to']['version'] . '/files/' . $action['path'];
        }
        $paths = array_values(array_unique($paths)); sort($paths, SORT_STRING);
        $files = [];
        foreach ($paths as $relative) {
            $state = $this->regularFileState(ScaffoldPathGuard::projectPath($root, $relative), $relative);
            if ($state['present']) {
                $content = file_get_contents(ScaffoldPathGuard::projectPath($root, $relative));
                if (!is_string($content)) throw new RuntimeException('SCAFFOLD_BACKUP_READ_FAILED: ' . $relative);
                $backupRelative = 'files/' . $relative;
                $this->writeFileAtomic($directory . '/' . $backupRelative, $content, 0600);
                $state['backup'] = $backupRelative;
            } else {
                $state['backup'] = null;
            }
            $files[$relative] = $state;
        }
        $recovery = ['schema_version'=>2,'protocol'=>'peanut.scaffold-recovery.v2','candidate'=>$plan['candidate'],
            'pre_tree_sha256'=>'sha256:'.hash('sha256',self::canonicalJson($files)),'files'=>$files];
        $path = $directory . '/recovery.json';
        if (is_file($path)) {
            $existing = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if ($existing !== $recovery) throw new RuntimeException('SCAFFOLD_RECOVERY_COLLISION');
        } else {
            $this->writeJsonAtomic($path, $recovery, 0600);
        }
        return $this->relative($root, $path);
    }

    private function nextApplicationManifest(string $root, array $plan, ScaffoldManifest $to): array
    {
        [$application] = $this->applicationManifest($root);
        $oldByPath = [];
        foreach ($application['files'] as $file) $oldByPath[$file['path']] = $file;
        $managedPaths = array_column($plan['actions'], 'path');
        $files = [];
        foreach ($application['files'] as $file) {
            if (!in_array($file['classification'], ['managed','generated-managed'], true)) {
                $state = $this->regularFileState(ScaffoldPathGuard::projectPath($root, $file['path']), $file['path']);
                $file['sha256'] = $state['sha256']; $file['mode'] = $state['mode'];
                $files[] = $file;
            }
        }
        foreach ($plan['actions'] as $action) {
            if ($action['action'] === 'delete') continue;
            $state = $this->regularFileState(ScaffoldPathGuard::projectPath($root, $action['path']), $action['path']);
            if (!$state['present']) throw new RuntimeException('SCAFFOLD_APPLY_MANAGED_MISSING: ' . $action['path']);
            $files[] = ['path'=>$action['path'],'sha256'=>$state['sha256'],'mode'=>$state['mode'],'classification'=>$action['classification'],
                'owner'=>'scaffold','source'=>$action['path'],'baseline_path'=>'.peanut/scaffold-baseline/'.$to->version().'/files/'.$action['path']];
        }
        usort($files, static fn(array $a,array $b): int => strcmp($a['path'],$b['path']));
        $managed = array_values(array_filter($files, static fn(array $f): bool => in_array($f['classification'],['managed','generated-managed'],true)));
        $appOwned = array_values(array_filter($files, static fn(array $f): bool => $f['classification']==='app-owned'));
        $application['template'] = ['version'=>$to->version(),'inventory_sha256'=>$to->release()['inventory_sha256'],
            'source_commit'=>$to->release()['source_commit'],'source_tree'=>$to->release()['source_tree']];
        $application['schema_version'] = 2;
        $application['protocol'] = 'peanut.application-scaffold.v2';
        $application['application']['version'] = $plan['identity']['application_version'];
        $application['ownership']['baseline_root'] = '.peanut/scaffold-baseline/' . $to->version() . '/files';
        $application['digests'] = ['managed_tree_sha256'=>$this->manifestTreeDigest($managed),'app_owned_tree_sha256'=>$this->manifestTreeDigest($appOwned)];
        $application['files'] = $files;
        $application['last_scaffold_upgrade'] = ['candidate'=>$plan['candidate'],'from'=>$plan['identity']['from']['version'],'to'=>$to->version()];
        return $application;
    }

    private function managedDigestFromManifest(string $root, array $manifest): string
    {
        $files=[];
        foreach($manifest['files'] as $file) if(in_array($file['classification'],['managed','generated-managed'],true)) $files[$file['path']]=$this->regularFileState(ScaffoldPathGuard::projectPath($root,$file['path']),$file['path']);
        ksort($files,SORT_STRING); return 'sha256:'.hash('sha256',self::canonicalJson($files));
    }
    private function manifestTreeDigest(array $files): string { $rows=array_map(static fn(array $f):string=>$f['path']."\0".$f['sha256'],$files); sort($rows,SORT_STRING); return hash('sha256',implode("\n",$rows)); }
    private function recoveryMatches(string $root, array $recovery): bool { foreach ($recovery['files'] as $relative=>$state) { $actual=$this->regularFileState(ScaffoldPathGuard::projectPath($root,(string)$relative),(string)$relative); if ($actual['present'] !== $state['present'] || $actual['sha256'] !== $state['sha256'] || $actual['mode'] !== $state['mode']) return false; } return true; }

    private function writeFileAtomic(string $path, string $content, int $mode): void
    {
        ScaffoldPathGuard::ensureDirectory(dirname($path)); $tmp=dirname($path).'/.'.basename($path).'.stage-'.bin2hex(random_bytes(6));
        if (file_put_contents($tmp,$content,LOCK_EX)===false || !chmod($tmp,$mode) || !rename($tmp,$path)) { @unlink($tmp); throw new RuntimeException('SCAFFOLD_ATOMIC_WRITE_FAILED: '.$path); }
    }
    private function pruneEmptyParents(string $directory,string $root): void
    {
        while($directory!==$root&&str_starts_with($directory,$root.DIRECTORY_SEPARATOR)){
            if(!is_dir($directory)||is_link($directory)||(scandir($directory)?:[])!==['.','..']||!rmdir($directory))return;
            $directory=dirname($directory);
        }
    }
    private function writeJsonAtomic(string $path, array $data, int $mode): void { $this->writeFileAtomic($path,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n",$mode); }
    private function relative(string $root,string $path): string { return str_replace(DIRECTORY_SEPARATOR,'/',substr($path,strlen($root)+1)); }
    private static function canonicalJson(array $value): string { self::sortRecursive($value); return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
    private static function sortRecursive(array &$value): void { if(!array_is_list($value))ksort($value,SORT_STRING); foreach($value as &$item)if(is_array($item))self::sortRecursive($item); }
}
