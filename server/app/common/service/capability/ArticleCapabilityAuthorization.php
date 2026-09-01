<?php
declare(strict_types=1);

namespace app\common\service\capability;

use app\Modules\Official\Article\ModuleProvider;
use app\Modules\Official\Article\Model\Article;
use PDO;
use Closure;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\AuthorizationDecision;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use PeanutAdmin\Kernel\Context\RequestedTargetSet;
use Throwable;

/** CAP06 Article boundary: trusted Tenant + Host permission + visible typed target. */
final class ArticleCapabilityAuthorization
{
    public const RESOURCE_KEY = 'article';

    private readonly Closure $permissionPolicy;

    /** @param callable(TenantContext, string, string): bool $permissionPolicy */
    public function __construct(
        private readonly PDO $pdo,
        callable $permissionPolicy,
    ) {
        $this->permissionPolicy = Closure::fromCallable($permissionPolicy);
    }

    public function authorizedContext(
        TenantContext $tenant,
        string $articleKey,
        string $operation,
    ): AuthorizedOperationContext {
        (new ModuleProvider())->access($this->pdo)->assertTenant($tenant->tenantId);
        if ($operation === '' || preg_match('/^[1-9][0-9]*$/D', $articleKey) !== 1) {
            throw self::denied();
        }
        $articleId = filter_var($articleKey, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($articleId === false
            || !$this->trusted($tenant)
            || !($this->permissionPolicy)($tenant, $operation, (string) $articleId)
            || !$this->visible($tenant->tenantId, (int) $articleId)) {
            throw self::denied();
        }

        $target = new RequestedTargetSet(self::RESOURCE_KEY, [(string) $articleId]);
        $basis = hash('sha256', implode("\0", [
            (string) $tenant->tenantId,
            (string) $tenant->memberId,
            (string) $tenant->authorizationRevision,
            self::RESOURCE_KEY,
            $operation,
            (string) $articleId,
        ]));

        return AuthorizedOperationContext::fromDecision(AuthorizationDecision::allow(
            $tenant,
            self::RESOURCE_KEY,
            $operation,
            [$target],
            $basis,
        ));
    }

    public static function denied(): ApiException
    {
        return new ApiException('ARTICLE_CAPABILITY_DENIED', 404, 'Article capability is unavailable.');
    }

    private function trusted(TenantContext $tenant): bool
    {
        return $tenant->tenantId > 0
            && $tenant->accountId > 0
            && $tenant->memberId > 0
            && $tenant->authorizationRevision > 0
            && $tenant->sessionKey !== ''
            && $tenant->clientKey !== ''
            && $tenant->requestId !== '';
    }

    private function visible(int $tenantId, int $articleId): bool
    {
        try {
            return !Article::where('tenant_id', $tenantId)
                ->where('id', $articleId)
                ->where('is_show', 1)
                ->findOrEmpty()
                ->isEmpty();
        } catch (Throwable) {
            return false;
        }
    }
}
