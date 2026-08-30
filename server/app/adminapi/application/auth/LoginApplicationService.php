<?php
declare(strict_types=1);

namespace app\adminapi\application\auth;

use app\adminapi\http\AdminRequest;
use app\common\service\authorization\AdminAuthorizationService;
use app\common\application\ApplicationService;
use app\common\service\tenant\TenantEntryBindingResolver;
use app\common\service\tenant\ApplicationHostPolicy;
use app\tenant\service\TenantAuthRuntimeFactory;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantAuthentication;
use PeanutAdmin\Kernel\Auth\TenantSelectionRequired;

final class LoginApplicationService extends ApplicationService
{
    public function login(array $params): array|false
    {
        try {
            ApplicationHostPolicy::production()->assertTenantAdmin(request());
            $tenantCode = TenantEntryBindingResolver::production()->loginTenantCode(
                request(),
                TenantEntryBindingResolver::ADMIN_CLIENT,
                isset($params['tenant_code']) ? (string)$params['tenant_code'] : null,
            );
            $outcome = TenantAuthRuntimeFactory::service()->login(
                trim((string)$params['account']),
                (string)$params['password'],
                $tenantCode,
                request()->ip(),
                request()->header('User-Agent'),
                AdminRequest::requestId(request()),
            );
            if ($outcome instanceof TenantSelectionRequired) {
                return $outcome->responseData();
            }
            if (!$outcome instanceof TenantAuthentication) {
                throw new \DomainException('TENANT_AUTHENTICATION_INVALID');
            }

            $principal = (new AdminAuthorizationService())->principal($outcome->context)->toArray();
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
        } catch (AuthException|\DomainException|\InvalidArgumentException) {
            self::setError('账号或密码错误');
            return false;
        }
    }

    public function logout(string $token): void
    {
        if (!str_starts_with($token, 'pa_tat_')) {
            return;
        }
        try {
            TenantAuthRuntimeFactory::service()->logout($token, 'admin-logout-' . bin2hex(random_bytes(8)));
        } catch (\Throwable) {
            // Logout is idempotent for the compatibility Admin endpoint.
        }
    }
}
