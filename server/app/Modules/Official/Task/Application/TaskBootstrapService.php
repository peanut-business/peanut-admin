<?php
declare(strict_types=1);

namespace app\Modules\Official\Task\Application;

use app\Modules\Official\Task\Contracts\TaskBootstrapCommands;
use app\Modules\Official\Task\Model\Crontab;

final class TaskBootstrapService implements TaskBootstrapCommands
{
    public function seedDefaults(array $defaults): void
    {
        $existing = array_fill_keys(array_map(
            'strval',
            Crontab::whereIn('command', array_column($defaults, 'command'))->column('command'),
        ), true);
        $missing = array_values(array_filter(
            $defaults,
            static fn(array $row): bool => !isset($existing[$row['command']]),
        ));
        if ($missing !== []) {
            (new Crontab())->saveAll($missing);
        }
    }
}
