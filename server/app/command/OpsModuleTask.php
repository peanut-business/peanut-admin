<?php
declare(strict_types=1);

namespace app\command;

use app\platform\service\ops\PdoModuleOperationTaskExecutionService;
use PDO;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;
use think\facade\Db;
use Throwable;

/** Deployment-control bridge for one fixed Module operation task. */
final class OpsModuleTask extends Command
{
    protected function configure(): void
    {
        $this->setName('ops-module:task')
            ->addArgument('action', Argument::REQUIRED, 'claim, advance, execute, heartbeat, succeed, or fail')
            ->addOption('task-key', null, Option::VALUE_OPTIONAL, 'Opaque task key')
            ->addOption('revision', null, Option::VALUE_OPTIONAL, 'Claimed execution revision')
            ->addOption('error-code', null, Option::VALUE_OPTIONAL, 'Stable allowlisted failure code')
            ->setDescription('Advance one deployment-owned Module package operation');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $pdo = Db::connect()->connect();
            if (!$pdo instanceof PDO) throw new \RuntimeException('OPS_MODULE_DATABASE_UNAVAILABLE');
            $config = Config::get('modules', []);
            if (!is_array($config)) throw new \RuntimeException('OPS_MODULE_CONFIG_INVALID');
            $service = new PdoModuleOperationTaskExecutionService(
                $pdo,
                dirname(__DIR__, 3),
                $config,
                $this->trustedKeys(),
            );
            $action = trim((string)$input->getArgument('action'));
            $result = match ($action) {
                'claim' => $service->claim(),
                'advance' => $service->advance($this->taskKey($input), $this->revision($input)),
                'execute' => $service->execute($this->taskKey($input), $this->revision($input)),
                'heartbeat' => $service->heartbeat($this->taskKey($input), $this->revision($input)),
                'succeed' => $service->succeed($this->taskKey($input), $this->revision($input)),
                'fail' => $service->fail(
                    $this->taskKey($input),
                    $this->revision($input),
                    trim((string)$input->getOption('error-code')),
                ),
                default => throw new \RuntimeException('OPS_MODULE_TASK_ACTION_INVALID'),
            };
            $output->writeln(json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            return 0;
        } catch (Throwable $exception) {
            $code = preg_match('/^(OPS|PLUGIN|MODULE|PACKAGE)_[A-Z0-9_]+$/D', $exception->getMessage()) === 1
                ? $exception->getMessage() : 'OPS_MODULE_WORKER_FAILED';
            $output->writeln(json_encode(['ok' => false, 'error_code' => $code], JSON_THROW_ON_ERROR));
            return 1;
        }
    }

    private function taskKey(Input $input): string
    {
        $key = trim((string)$input->getOption('task-key'));
        if (preg_match('/^job_[a-f0-9]{32}$/D', $key) !== 1) throw new \RuntimeException('OPS_MODULE_TASK_KEY_INVALID');
        return $key;
    }

    private function revision(Input $input): int
    {
        $revision = trim((string)$input->getOption('revision'));
        if (preg_match('/^[1-9][0-9]*$/D', $revision) !== 1) throw new \RuntimeException('OPS_MODULE_EXECUTION_REVISION_INVALID');
        return (int)$revision;
    }

    /** @return array<string,string> */
    private function trustedKeys(): array
    {
        $trusted = [];
        foreach ((array)Config::get('module_packages.trusted_ed25519_keys', []) as $keyId => $encoded) {
            $decoded = is_string($encoded) ? base64_decode($encoded, true) : false;
            if (is_string($keyId) && is_string($decoded) && strlen($decoded) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                $trusted[$keyId] = $decoded;
            }
        }
        return $trusted;
    }
}
