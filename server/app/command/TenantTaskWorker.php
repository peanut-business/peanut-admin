<?php
declare(strict_types=1);

namespace app\command;

use app\common\service\async\TaskImportExportRuntimeFactory;
use PDO;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\facade\Db;

final class TenantTaskWorker extends Command
{
    protected function configure()
    {
        $this->setName('tenant-task:work')
            ->setDescription('执行一个 Tenant 的可信异步任务')
            ->addArgument('tenant_id', Argument::REQUIRED, 'Tenant ID');
    }

    protected function execute(Input $input, Output $output)
    {
        $raw = (string)$input->getArgument('tenant_id');
        if (preg_match('/^[1-9][0-9]*$/D', $raw) !== 1) {
            $output->writeln('[tenant-task:work] invalid tenant');
            return 1;
        }
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) {
            $output->writeln('[tenant-task:work] database unavailable');
            return 1;
        }
        try {
            $processed = TaskImportExportRuntimeFactory::fromConfig($pdo)->runTenant(
                (int)$raw,
                'tenant-worker-' . getmypid() . '-' . bin2hex(random_bytes(6)),
            );
            $output->writeln(sprintf('[tenant-task:work] tenant=%d processed=%d', (int)$raw, $processed));
            return 0;
        } catch (\Throwable) {
            $output->writeln('[tenant-task:work] failed');
            return 1;
        }
    }
}
