<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment;

use app\Modules\Official\Payment\Contracts\PaymentChannelGrantCommands;
use app\Modules\Official\Payment\Infrastructure\ThinkPhpPaymentChannelGrantCommands;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.payment';
    }

    public function channelGrantCommands(): PaymentChannelGrantCommands
    {
        return new ThinkPhpPaymentChannelGrantCommands();
    }
}
