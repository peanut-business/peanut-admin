<?php
declare(strict_types=1);

namespace app\adminapi\application\auth;

use app\common\service\authorization\AdminAuthorizationService;
use app\common\application\BusinessException;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantAuthentication;
use PeanutAdmin\Kernel\Auth\TenantAuthService;
use PeanutAdmin\Kernel\Auth\TenantSelectionRequired;

final class LoginApplicationService
{
    public function __construct(
        private readonly TenantAuthService $tenantAuth,
        private readonly AdminAuthorizationService $authorization,
    ) {}

    public function login(
        array $params,
        ?string $tenantCode,
        string $ip,
        string $userAgent,
        string $requestId,
    ): array
    {
        try {
            $outcome = $this->tenantAuth->login(
                trim((string)$params['account']),
                (string)$params['password'],
                $tenantCode,
                $ip,
                $userAgent,
                $requestId,
            );
            if ($outcome instanceof TenantSelectionRequired) {
                return $outcome->responseData();
            }
            if (!$outcome instanceof TenantAuthentication) {
                throw new \DomainException('TENANT_AUTHENTICATION_INVALID');
            }

            $principal = $this->authorization->principal($outcome->context)->toArray();
            return [
                'state' => 'authenticated',
                'token' => $outcome->tokens->access->expose(),
                'access_token' => $outcome->tokens->access->expose(),
                'token_type' => 'Bearer',
                'expires_in' => 900,
                'admin_id' => $principal['id'],
                'account' => $principal['account'],
                'username' => $principal['username'],
                'name' => $principal['name'],
                'avatar' => $principal['avatar'],
                'role_name' => $principal['role_name'],
                'terminal' => (int)$params['terminal'],
                'context' => [
                    'audience' => 'tenant',
                    'account_id' => (string)$outcome->context->accountId,
                    'tenant_id' => (string)$outcome->context->tenantId,
                    'tenant_member_id' => (string)$outcome->context->memberId,
                ],
            ];
        } catch (AuthException|\DomainException|\InvalidArgumentException $exception) {
            throw new BusinessException('ADMIN_LOGIN_REJECTED', 401, '账号或密码错误');
        }
    }

    public function logout(string $token): void
    {
        if (!str_starts_with($token, 'pa_tat_')) {
            return;
        }
        try {
            $this->tenantAuth->logout($token, 'admin-logout-' . bin2hex(random_bytes(8)));
        } catch (\Throwable) {
            // Logout is idempotent for the Admin endpoint.
        }
    }
}
