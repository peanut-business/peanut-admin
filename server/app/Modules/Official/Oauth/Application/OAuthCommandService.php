<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Application;

use app\Modules\Official\Oauth\Contracts\OAuthCommands;
use app\api\application\OAuthApplicationService;
use app\common\service\external\ExternalTenantBinding;
use app\common\service\member\AuthenticatedMemberContext;
use app\common\service\oauth\contract\OAuthTransportInterface;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** The HTTP layer reaches OAuth writes only through this Module contract. */
final class OAuthCommandService implements OAuthCommands
{
    public function __construct(private readonly OAuthApplicationService $oauth)
    {
    }

    public function begin(TenantSystemContext $context, string $scene, string $returnPath, string $redirectUri, ExternalTenantBinding $binding, ?OAuthTransportInterface $transport = null): array
    {
        return $this->oauth->begin($context, $scene, $returnPath, $redirectUri, $binding, $transport);
    }

    public function callback(TenantSystemContext $context, string $scene, string $code, string $state, ExternalTenantBinding $binding, ?OAuthTransportInterface $transport = null): array
    {
        return $this->oauth->callback($context, $scene, $code, $state, $binding, $transport);
    }

    public function miniProgramLogin(TenantSystemContext $context, string $code, ExternalTenantBinding $binding, ?OAuthTransportInterface $transport = null): array
    {
        return $this->oauth->miniProgramLogin($context, $code, $binding, $transport);
    }

    public function complete(TenantContext|TenantSystemContext $context, array $params): array
    {
        return $this->oauth->complete($context, $params);
    }

    public function bind(AuthenticatedMemberContext $context, int $memberId, string $scene, string $code, ?OAuthTransportInterface $transport = null): bool
    {
        return $this->oauth->bind($context, $memberId, $scene, $code, $transport);
    }
}
