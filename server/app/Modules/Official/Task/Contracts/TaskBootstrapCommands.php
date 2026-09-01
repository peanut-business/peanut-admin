<?php
declare(strict_types=1);

namespace app\Modules\Official\Task\Contracts;

interface TaskBootstrapCommands
{
    /** @param list<array<string,mixed>> $defaults */
    public function seedDefaults(array $defaults): void;
}
