<?php
declare(strict_types=1);

namespace app\platform\service;

use app\platform\context\PlatformOperatorContext;
use app\platform\identity\PlatformOperatorAccountBoundary;
use PeanutAdmin\Kernel\Auth\PlatformAuthentication;
use PeanutAdmin\Kernel\Auth\PlatformAuthService;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationEvaluator;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationRepository;

final readonly class PlatformOperatorSessionService
{
    public function __construct(
        private PlatformAuthService $authentication,
        private PlatformAuthorizationEvaluator $authorization,
        private PlatformAuthorizationRepository $permissions,
        private PlatformOperatorAccountBoundary $accounts
    ) {
    }

    public function login(
        string $email,
        string $password,
        string $ipAddress,
        ?string $userAgent,
        string $requestId
    ): PlatformAuthentication {
        $this->accounts->assertEmailIsPlatformOnly($email);
        $result = $this->authentication->login($email, $password, $ipAddress, $userAgent, $requestId);
        try {
            $this->accounts->assertAccountIsPlatformOnly($result->context->accountId);
        } catch (\Throwable $exception) {
            $this->authentication->logout($result->tokens->access->expose());
            throw $exception;
        }

        return $result;
    }

    public function refresh(
        string $refreshToken,
        string $ipAddress,
        ?string $userAgent,
        string $requestId
    ): PlatformAuthentication {
        $result = $this->authentication->refresh($refreshToken, $ipAddress, $userAgent, $requestId);
        try {
            $this->accounts->assertAccountIsPlatformOnly($result->context->accountId);
        } catch (\Throwable $exception) {
            $this->authentication->logout($result->tokens->access->expose());
            throw $exception;
        }

        return $result;
    }

    public function context(string $accessToken, string $requestId): PlatformOperatorContext
    {
        $context = $this->authentication->context($accessToken, $requestId);
        try {
            $this->accounts->assertAccountIsPlatformOnly($context->accountId);
        } catch (\Throwable $exception) {
            $this->authentication->logout($accessToken);
            throw $exception;
        }

        return PlatformOperatorContext::fromValidatedPlatformSession($context);
    }

    public function logout(string $accessToken): void
    {
        $this->authentication->logout($accessToken);
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
