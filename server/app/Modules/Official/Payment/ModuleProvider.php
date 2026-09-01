<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment;

use app\common\composition\ModuleBindingContributor;
use app\Modules\Official\Payment\Contracts\PaymentChannelGrantCommands;
use app\Modules\Official\Payment\Infrastructure\ThinkPhpPaymentChannelGrantCommands;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract, ModuleBindingContributor
{
    public function moduleKey(): string
    {
        return 'official.payment';
    }

    public function channelGrantCommands(): PaymentChannelGrantCommands
    {
        return new ThinkPhpPaymentChannelGrantCommands();
    }

    public function bindings(): array
    {
        return [
            PaymentChannelGrantCommands::class => fn(): PaymentChannelGrantCommands => $this->channelGrantCommands(),
        ];
    }
}
