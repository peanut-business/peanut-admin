<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Contracts;

use app\common\service\external\ExternalTenantBinding;

interface OAuthCallbackLocator
{
    /** @return list<ExternalTenantBinding> */
    public function locateState(string $provider, string $stateHash): array;

    /** @return list<ExternalTenantBinding> */
    public function locateTicket(string $ticketHash): array;
}
