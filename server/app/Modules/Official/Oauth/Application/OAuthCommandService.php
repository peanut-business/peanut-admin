<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Application;

use app\Modules\Official\Oauth\Contracts\OAuthCommands;
use app\api\logic\OAuthLogic;
use app\common\service\external\ExternalTenantBinding;
use app\common\service\member\AuthenticatedMemberContext;
use app\common\service\oauth\contract\OAuthTransportInterface;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** The HTTP layer reaches OAuth writes only through this Module contract. */
final class OAuthCommandService implements OAuthCommands
{
    private string $error = '';

    public function locateState(string $provider, string $stateHash): array
    {
        return OAuthCallbackLocator::byState($provider, $stateHash);
    }

    public function locateTicket(string $ticketHash): array
    {
        return OAuthCallbackLocator::byTicket($ticketHash);
    }

    public function begin(TenantSystemContext $context, string $scene, string $returnPath, string $redirectUri, ExternalTenantBinding $binding, ?OAuthTransportInterface $transport = null): array|false
    {
        return $this->capture(OAuthLogic::begin($context, $scene, $returnPath, $redirectUri, $binding, $transport));
    }

    public function callback(TenantSystemContext $context, string $scene, string $code, string $state, ExternalTenantBinding $binding, ?OAuthTransportInterface $transport = null): array|false
    {
        return $this->capture(OAuthLogic::callback($context, $scene, $code, $state, $binding, $transport));
    }

    public function miniProgramLogin(TenantSystemContext $context, string $code, ExternalTenantBinding $binding, ?OAuthTransportInterface $transport = null): array|false
    {
        return $this->capture(OAuthLogic::miniProgramLogin($context, $code, $binding, $transport));
    }

    public function complete(TenantContext|TenantSystemContext $context, array $params): array|false
    {
        return $this->capture(OAuthLogic::complete($context, $params));
    }

    public function bind(AuthenticatedMemberContext $context, int $memberId, string $scene, string $code, ?OAuthTransportInterface $transport = null): bool
    {
        return $this->capture(OAuthLogic::bind($context, $memberId, $scene, $code, $transport));
    }

    public function error(): string
    {
        return $this->error;
    }

    private function capture(array|bool $result): array|bool
    {
        $this->error = $result === false ? OAuthLogic::getError() : '';
        return $result;
    }
}
