<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment;

use app\common\composition\ModuleBindingContributor;
use app\common\service\external\ExternalTenantBindingRepository;
use app\common\service\external\ExternalTenantResolver;
use app\common\service\payment\PaymentServiceFactory;
use app\common\tenancy\PlatformTenantDataGateway;
use app\Modules\Official\Payment\Application\RechargeApplicationService;
use app\Modules\Official\Payment\Contracts\PaymentChannelGrantCommands;
use app\Modules\Official\Payment\Contracts\RechargeCommands;
use app\Modules\Official\Payment\Contracts\RechargeQueries;
use app\Modules\Official\Payment\Contracts\RefundReconciliationCommands;
use app\Modules\Official\Payment\Infrastructure\ThinkPhpPaymentChannelGrantCommands;
use app\Modules\Official\Payment\Infrastructure\ThinkPhpRefundReconciliationCommands;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use think\App;

final class ModuleProvider implements ModuleProviderContract, ModuleBindingContributor
{
    public function moduleKey(): string
    {
        return 'official.payment';
    }

    public function channelGrantCommands(App $app): PaymentChannelGrantCommands
    {
        return new ThinkPhpPaymentChannelGrantCommands(
            $app->make(ExternalTenantBindingRepository::class),
            $app->make(PlatformTenantDataGateway::class),
        );
    }

    public function bindings(): array
    {
        return [
            PaymentChannelGrantCommands::class => fn(App $app): PaymentChannelGrantCommands => $this->channelGrantCommands($app),
            PaymentServiceFactory::class => fn(App $app): PaymentServiceFactory => new PaymentServiceFactory(
                $app->make(ExternalTenantResolver::class),
            ),
            RechargeCommands::class => fn(App $app): RechargeCommands => $app->make(RechargeApplicationService::class),
            RechargeQueries::class => fn(App $app): RechargeQueries => $app->make(RechargeApplicationService::class),
            RefundReconciliationCommands::class => fn(App $app): RefundReconciliationCommands => $app->make(ThinkPhpRefundReconciliationCommands::class),
        ];
    }
}
