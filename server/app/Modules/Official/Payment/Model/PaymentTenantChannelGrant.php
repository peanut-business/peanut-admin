<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment\Model;

use app\common\model\TenantOwnedModel;

final class PaymentTenantChannelGrant extends TenantOwnedModel
{
    protected $name = 'payment_tenant_channel_grant';
}
