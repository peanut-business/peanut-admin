<?php
declare(strict_types=1);

namespace app\Modules\Official\Article\Infrastructure\Authorization;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use app\Modules\Official\Article\Contracts\ArticleModuleAccess;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\PdoTenantAuthorizationRepository;
use PeanutAdmin\Kernel\Module\ModuleGuard;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;

final readonly class PdoArticleModuleAccess implements ArticleModuleAccess
{
    public function __construct(private PDO $pdo)
    {
    }

    public function assertTenant(int $tenantId): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $guard = new ModuleGuard(new PdoModuleRuntimeRepository($this->pdo));
        $guard->assertDeployment('official.article');
        $guard->assertTenant($tenantId, 'official.article', $now);
    }

    public function assertMember(TenantContext $context, string $permission, bool $rootBypass = false): void
    {
        $permissions = (new PdoTenantAuthorizationRepository($this->pdo))->permissions(
            $context->tenantId,
            $context->memberId
        );
        (new ModuleGuard(new PdoModuleRuntimeRepository($this->pdo)))->assertMemberAccess(
            $context->tenantId,
            'official.article',
            $rootBypass || $permissions->allows($permission),
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
    }
}
