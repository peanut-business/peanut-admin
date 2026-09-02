<?php
declare(strict_types=1);

namespace app\platform\service;

use app\common\application\BusinessException;
use app\platform\context\PlatformOperatorContext;
use PeanutAdmin\Kernel\Auth\PlatformAuthentication;
use PeanutAdmin\Kernel\Auth\PlatformAuthService;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationEvaluator;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationRepository;

final readonly class PlatformOperatorSessionService
{
    public function __construct(
        private PlatformAuthService $authentication,
        private PlatformAuthorizationEvaluator $authorization,
        private PlatformAuthorizationRepository $permissions
    ) {
    }

    public function login(
        string $email,
        string $password,
        string $ipAddress,
        ?string $userAgent,
        string $requestId
    ): PlatformAuthentication {
        try {
            return $this->authentication->login($email, $password, $ipAddress, $userAgent, $requestId);
        } catch (\PeanutAdmin\Kernel\Auth\AuthException|\DomainException|\InvalidArgumentException) {
            throw new BusinessException(
                'PLATFORM_AUTHENTICATION_REJECTED',
                401,
                'Email or password is incorrect.',
            );
        }
    }

    public function refresh(
        string $refreshToken,
        string $ipAddress,
        ?string $userAgent,
        string $requestId
    ): PlatformAuthentication {
        return $this->authentication->refresh($refreshToken, $ipAddress, $userAgent, $requestId);
    }

    public function context(string $accessToken, string $requestId): PlatformOperatorContext
    {
        return PlatformOperatorContext::fromValidatedPlatformSession(
            $this->authentication->context($accessToken, $requestId)
        );
    }

    public function logout(string $accessToken): void
    {
        try {
            $this->authentication->logout($accessToken);
        } catch (\PeanutAdmin\Kernel\Auth\AuthException) {
            // Logout is idempotent when the supplied credential is already invalid.
        }
    }

    public function assertAllowed(PlatformOperatorContext $context, string $permission): void
    {
        $this->authorization->assertAllowed($context->core, $permission);
    }

    /** @return list<string> */
    public function permissionKeys(PlatformOperatorContext $context): array
    {
        return $this->permissions->permissions($context->core->operatorId)->keys();
    }
}
