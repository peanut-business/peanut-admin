<?php
declare(strict_types=1);

namespace app\common\service\external;

use app\common\service\audit\AuditContractHost;
use app\common\service\member\AuthenticatedMemberContext;
use app\common\service\module\ModuleExecutionContext;
use app\platform\service\module\PdoModuleGovernanceProvider;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use think\facade\Db;

/** @deprecated Application adapter around the framework-agnostic Core resolver. */
final class ExternalTenantResolver
{
    public const ACTOR = \PeanutAdmin\IntegrationSecurity\External\ExternalTenantResolver::ACTOR;
    public const WECHAT_PAYMENT = \PeanutAdmin\IntegrationSecurity\External\ExternalTenantResolver::WECHAT_PAYMENT;
    public const ALIPAY_PAYMENT = \PeanutAdmin\IntegrationSecurity\External\ExternalTenantResolver::ALIPAY_PAYMENT;
    public const WECHAT_OFFICIAL_CALLBACK = \PeanutAdmin\IntegrationSecurity\External\ExternalTenantResolver::WECHAT_OFFICIAL_CALLBACK;
    public const WECHAT_OFFICIAL_OAUTH = \PeanutAdmin\IntegrationSecurity\External\ExternalTenantResolver::WECHAT_OFFICIAL_OAUTH;
    public const WECHAT_OPEN_PLATFORM = \PeanutAdmin\IntegrationSecurity\External\ExternalTenantResolver::WECHAT_OPEN_PLATFORM;
    public const WECHAT_MINI_PROGRAM = \PeanutAdmin\IntegrationSecurity\External\ExternalTenantResolver::WECHAT_MINI_PROGRAM;

    private readonly \PeanutAdmin\IntegrationSecurity\External\ExternalTenantResolver $core;

    public function __construct(ExternalTenantBindingRepository $bindings, ExternalTenantAudit $audit)
    {
        $this->core = new \PeanutAdmin\IntegrationSecurity\External\ExternalTenantResolver($bindings, $audit);
    }

    public static function production(): self
    {
        return new self(
            new ThinkPhpExternalTenantBindingRepository(),
            new ThinkPhpExternalTenantAudit(AuditContractHost::production()),
        );
    }

    public function verifiedCallback(string $provider, string $callbackKey, string $operation, string $operationId, callable $verifier): ExternalTenantResolution
    {
        return $this->resolution(fn() => $this->core->verifiedCallback($provider, $callbackKey, $operation, $operationId, $verifier));
    }

    public function verifiedModuleCallback(string $moduleKey, string $provider, string $callbackKey, string $operation, string $operationId, callable $verifier): ExternalTenantResolution
    {
        $resolution = $this->verifiedCallback($provider, $callbackKey, $operation, $operationId, $verifier);
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) {
            throw new ExternalTenantResolutionException();
        }
        PdoModuleGovernanceProvider::forExecution($pdo)
            ->executionGuard($moduleKey)
            ->assertExternalCallback(ModuleExecutionContext::system($moduleKey, $resolution->context));
        return $resolution;
    }

    public function clientIdentity(string $provider, string $clientIdentity, string $operation, string $operationId): ExternalTenantResolution
    {
        return $this->resolution(fn() => $this->core->clientIdentity($provider, $clientIdentity, $operation, $operationId));
    }

    public function onlyActiveBinding(string $provider, string $operation, string $operationId): ExternalTenantResolution
    {
        return $this->resolution(fn() => $this->core->onlyActiveBinding($provider, $operation, $operationId));
    }

    public function oauthState(string $provider, string $state, string $operationId): ExternalTenantResolution
    {
        return $this->resolution(fn() => $this->core->oauthState($provider, $state, $operationId));
    }

    public function oauthTicket(string $ticket, string $operationId): ExternalTenantResolution
    {
        return $this->resolution(fn() => $this->core->oauthTicket($ticket, $operationId));
    }

    public function bindingForTenant(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context, string $provider, bool $requireActive = true): ExternalTenantBinding
    {
        return $this->binding(fn() => $this->core->bindingForTenant(ExternalTenantContext::tenantId($context), $provider, $requireActive));
    }

    public static function oauthProvider(string $scene): string
    {
        try {
            return \PeanutAdmin\IntegrationSecurity\External\ExternalTenantResolver::oauthProvider($scene);
        } catch (\PeanutAdmin\IntegrationSecurity\External\ExternalTenantResolutionException) {
            throw new ExternalTenantResolutionException();
        }
    }

    private function resolution(callable $resolve): ExternalTenantResolution
    {
        try {
            $resolution = $resolve();
            return new ExternalTenantResolution($resolution->context, $this->adaptBinding($resolution->binding), $resolution->verifiedValue);
        } catch (\PeanutAdmin\IntegrationSecurity\External\ExternalTenantResolutionException) {
            throw new ExternalTenantResolutionException();
        }
    }

    private function binding(callable $resolve): ExternalTenantBinding
    {
        try {
            return $this->adaptBinding($resolve());
        } catch (\PeanutAdmin\IntegrationSecurity\External\ExternalTenantResolutionException) {
            throw new ExternalTenantResolutionException();
        }
    }

    private function adaptBinding(\PeanutAdmin\IntegrationSecurity\External\ExternalTenantBinding $binding): ExternalTenantBinding
    {
        if ($binding instanceof ExternalTenantBinding) {
            return $binding;
        }
        return new ExternalTenantBinding($binding->id, $binding->tenantId, $binding->provider, $binding->callbackKey, $binding->identityHash, $binding->identityHint, $binding->config, $binding->active, $binding->tenantActive);
    }
}
