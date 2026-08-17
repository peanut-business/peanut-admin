<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$runner = $root . '/scripts/release-upgrade-policy';
$temporary = sys_get_temp_dir() . '/pa-release-upgrade-policy-' . bin2hex(random_bytes(8));
$releaseRoot = $temporary . '/release';
$migrationDirectory = $releaseRoot . '/server/database/migrations';
if (!mkdir($migrationDirectory, 0700, true) && !is_dir($migrationDirectory)) {
    throw new RuntimeException('unable to create release policy test directory');
}

function releasePolicyExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function releasePolicyWrite(string $path, array $registry): void
{
    file_put_contents(
        $path,
        json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
    );
}

/** @param list<string> $arguments @return array{exit:int,output:string} */
function releasePolicyRun(string $runner, array $arguments): array
{
    $pipes = [];
    $process = proc_open(
        array_merge([$runner], $arguments),
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) throw new RuntimeException('unable to execute release policy CLI');
    $output = (string)stream_get_contents($pipes[1]) . (string)stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['exit' => proc_close($process), 'output' => $output];
}

function releasePolicyRemoveTree(string $path): void
{
    if (!file_exists($path) && !is_link($path)) return;
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') releasePolicyRemoveTree($path . '/' . $entry);
        }
        rmdir($path);
        return;
    }
    unlink($path);
}

$baseRegistry = [
    'schema_version' => 1,
    'protocol' => 'peanut.release-upgrade-transitions.v1',
    'project_id' => 'peanut-admin',
    'transitions' => [],
];

try {
    $deployScript = (string)file_get_contents($root . '/scripts/deploy-release');
    foreach ([
        "--upgrade requires --from",
        'resources/release-upgrade-transitions.json',
        'release-upgrade-policy',
        'current deployment release identity is missing',
        'registered paired backup binding is invalid',
        'mysqldump --single-transaction',
        'sha256sum -c SHA256SUMS',
        'resume_old_runtime=0',
        '.release-transition-sha256',
    ] as $requiredDeploymentGate) {
        releasePolicyExpect(
            str_contains($deployScript, $requiredDeploymentGate),
            'deploy-release is missing its 2.x upgrade gate: ' . $requiredDeploymentGate
        );
    }

    $checkedIn = releasePolicyRun($runner, [
        '--release-root', $root,
        'validate',
    ]);
    releasePolicyExpect($checkedIn['exit'] === 0, 'checked-in empty transition registry must validate');
    $checkedInPayload = json_decode($checkedIn['output'], true, 512, JSON_THROW_ON_ERROR);
    releasePolicyExpect(
        ($checkedInPayload['transition_count'] ?? null) === 0,
        'checked-in registry must report zero transitions before a post-2.0 release exists'
    );

    $migrationPath = $migrationDirectory . '/20260818_release_transition.sql';
    file_put_contents($migrationPath, "SELECT 1;\n");
    $transition = [
        'from' => 'v2.0.0',
        'to' => 'v2.1.0',
        'backup' => ['required' => true],
        'migrations' => [[
            'path' => 'server/database/migrations/20260818_release_transition.sql',
            'sha256' => hash_file('sha256', $migrationPath),
        ]],
    ];
    $validRegistry = $baseRegistry;
    $validRegistry['transitions'][] = $transition;
    $validPath = $temporary . '/valid.json';
    releasePolicyWrite($validPath, $validRegistry);
    $valid = releasePolicyRun($runner, [
        '--registry', $validPath,
        '--release-root', $releaseRoot,
        'resolve', '--from', 'v2.0.0', '--to', 'v2.1.0',
    ]);
    releasePolicyExpect($valid['exit'] === 0, 'one valid direct 2.x transition must resolve');
    $resolved = json_decode($valid['output'], true, 512, JSON_THROW_ON_ERROR);
    releasePolicyExpect(
        ($resolved['transition']['migrations'][0]['sha256'] ?? null) === hash_file('sha256', $migrationPath)
            && ($resolved['transition']['backup']['required'] ?? null) === true,
        'resolved transition must preserve the verified migration digest and backup gate'
    );

    $missing = releasePolicyRun($runner, [
        '--registry', $validPath,
        '--release-root', $releaseRoot,
        'resolve', '--from', 'v2.0.0', '--to', 'v2.0.1',
    ]);
    releasePolicyExpect(
        $missing['exit'] !== 0 && str_contains($missing['output'], 'found 0'),
        'an undeclared direct transition must fail closed'
    );

    $duplicateRegistry = $validRegistry;
    $duplicateRegistry['transitions'][] = $transition;
    $duplicatePath = $temporary . '/duplicate.json';
    releasePolicyWrite($duplicatePath, $duplicateRegistry);
    $duplicate = releasePolicyRun($runner, [
        '--registry', $duplicatePath, '--release-root', $releaseRoot, 'validate',
    ]);
    releasePolicyExpect(
        $duplicate['exit'] !== 0 && str_contains($duplicate['output'], 'duplicate transition'),
        'duplicate direct transitions must fail closed'
    );

    foreach ([
        ['name' => 'reverse', 'from' => 'v2.1.0', 'to' => 'v2.0.0', 'error' => 'wrong direction'],
        ['name' => 'cross-major', 'from' => 'v2.0.0', 'to' => 'v3.0.0', 'error' => 'crosses a major'],
    ] as $invalidDirection) {
        $invalidRegistry = $baseRegistry;
        $invalidTransition = $transition;
        $invalidTransition['from'] = $invalidDirection['from'];
        $invalidTransition['to'] = $invalidDirection['to'];
        $invalidRegistry['transitions'][] = $invalidTransition;
        $invalidPath = $temporary . '/' . $invalidDirection['name'] . '.json';
        releasePolicyWrite($invalidPath, $invalidRegistry);
        $result = releasePolicyRun($runner, [
            '--registry', $invalidPath, '--release-root', $releaseRoot, 'validate',
        ]);
        releasePolicyExpect(
            $result['exit'] !== 0 && str_contains($result['output'], $invalidDirection['error']),
            $invalidDirection['name'] . ' transition must fail closed'
        );
    }

    $noBackupRegistry = $validRegistry;
    $noBackupRegistry['transitions'][0]['backup']['required'] = false;
    $noBackupPath = $temporary . '/no-backup.json';
    releasePolicyWrite($noBackupPath, $noBackupRegistry);
    $noBackup = releasePolicyRun($runner, [
        '--registry', $noBackupPath, '--release-root', $releaseRoot, 'validate',
    ]);
    releasePolicyExpect(
        $noBackup['exit'] !== 0 && str_contains($noBackup['output'], 'backup.required=true'),
        'transition without a required backup must fail closed'
    );

    $badPathRegistry = $validRegistry;
    $badPathRegistry['transitions'][0]['migrations'][0]['path'] = '../outside.sql';
    $badPath = $temporary . '/bad-path.json';
    releasePolicyWrite($badPath, $badPathRegistry);
    $badPathResult = releasePolicyRun($runner, [
        '--registry', $badPath, '--release-root', $releaseRoot, 'validate',
    ]);
    releasePolicyExpect(
        $badPathResult['exit'] !== 0 && str_contains($badPathResult['output'], 'path must name'),
        'migration path outside the fixed directory must fail closed'
    );

    $badDigestRegistry = $validRegistry;
    $badDigestRegistry['transitions'][0]['migrations'][0]['sha256'] = str_repeat('0', 64);
    $badDigestPath = $temporary . '/bad-digest.json';
    releasePolicyWrite($badDigestPath, $badDigestRegistry);
    $badDigest = releasePolicyRun($runner, [
        '--registry', $badDigestPath, '--release-root', $releaseRoot, 'validate',
    ]);
    releasePolicyExpect(
        $badDigest['exit'] !== 0 && str_contains($badDigest['output'], 'digest mismatch'),
        'migration digest mismatch must fail closed'
    );

    $emptyPathRegistry = $validRegistry;
    $emptyPathRegistry['transitions'][0]['migrations'][0]['path'] = '';
    $emptyPath = $temporary . '/empty-path.json';
    releasePolicyWrite($emptyPath, $emptyPathRegistry);
    $emptyPathResult = releasePolicyRun($runner, [
        '--registry', $emptyPath, '--release-root', $releaseRoot, 'validate',
    ]);
    releasePolicyExpect(
        $emptyPathResult['exit'] !== 0 && str_contains($emptyPathResult['output'], 'empty or missing'),
        'empty migration path must fail closed'
    );
} finally {
    releasePolicyRemoveTree($temporary);
}

echo "RELEASE-UPGRADE-POLICY-TEST-001 passed\n";
