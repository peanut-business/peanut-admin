<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Contracts;

interface NotificationCommands
{
    public function saveChannel(string $section, array $input): void;

    public function saveScene(array $params): void;
}
