<?php
declare(strict_types=1);

namespace app\modules\official\notification\contracts;

interface NotificationCommands
{
    public function saveChannel(string $section, array $input): void;

    public function saveScene(array $params): void;
}
