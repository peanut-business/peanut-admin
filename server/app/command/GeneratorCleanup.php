<?php
declare(strict_types=1);

namespace app\command;

use app\adminapi\service\generator\GeneratorArchiveService;
use app\common\model\generator\GeneratorDownload;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/** 清理已使用或过期的代码生成下载令牌与隔离归档。 */
class GeneratorCleanup extends Command
{
    protected function configure()
    {
        $this->setName('generator:cleanup')->setDescription('清理代码生成器过期归档');
    }

    protected function execute(Input $input, Output $output)
    {
        $rows = GeneratorDownload::where(function ($query): void {
            $query->where('used_time', '>', 0)->whereOr('expire_time', '<=', time());
        })->order('id', 'asc')->limit(1000)->select();

        $cleaned = 0;
        foreach ($rows as $row) {
            try {
                GeneratorArchiveService::cleanup((string)$row->archive_path, (int)$row->admin_id);
                $row->delete();
                $cleaned++;
            } catch (\Throwable) {
                // 保留记录供下一轮重试，不扩大删除范围。
            }
        }
        $output->writeln(sprintf('[generator:cleanup] cleaned=%d', $cleaned));
        return 0;
    }
}
