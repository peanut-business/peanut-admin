<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Contracts;

use app\common\service\external\ExternalTenantBinding;
use app\common\service\member\AuthenticatedMemberContext;
use app\common\service\oauth\contract\OAuthTransportInterface;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/**
 * OAuth's write and callback boundary. Implementations consume state/tickets
 * atomically and delegate member, notification and channel ownership to their
 * respective Module contracts.
 */
interface OAuthCommands
{
    /** @return list<ExternalTenantBinding> */
    public function locateState(string $provider, string $stateHash): array;

    /** @return list<ExternalTenantBinding> */
    public function locateTicket(string $ticketHash): array;

    public function begin(TenantSystemContext $context, string $scene, string $returnPath, string $redirectUri, ExternalTenantBinding $binding, ?OAuthTransportInterface $transport = null): array|false;

    public function callback(TenantSystemContext $context, string $scene, string $code, string $state, ExternalTenantBinding $binding, ?OAuthTransportInterface $transport = null): array|false;

    public function miniProgramLogin(TenantSystemContext $context, string $code, ExternalTenantBinding $binding, ?OAuthTransportInterface $transport = null): array|false;

    public function complete(TenantContext|TenantSystemContext $context, array $params): array|false;

    public function bind(AuthenticatedMemberContext $context, int $memberId, string $scene, string $code, ?OAuthTransportInterface $transport = null): bool;

    public function error(): string;
}
