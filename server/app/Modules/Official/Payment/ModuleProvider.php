<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment;

use app\common\composition\ModuleBindingContributor;
use app\common\service\external\ExternalTenantResolver;
use app\common\service\payment\PaymentServiceFactory;
use app\Modules\Official\Payment\Contracts\PaymentChannelGrantCommands;
use app\Modules\Official\Payment\Infrastructure\ThinkPhpPaymentChannelGrantCommands;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use think\App;

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
            PaymentServiceFactory::class => fn(App $app): PaymentServiceFactory => new PaymentServiceFactory(
                $app->make(ExternalTenantResolver::class),
            ),
        ];
    }
}
