<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment;

use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.payment';
    }
}
