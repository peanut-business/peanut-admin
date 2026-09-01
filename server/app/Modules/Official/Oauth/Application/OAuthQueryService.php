<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Application;

use app\Modules\Official\Oauth\Contracts\OAuthQueries;
use app\Modules\Official\Oauth\Contracts\OAuthPersistence;
use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class OAuthQueryService implements OAuthQueries
{
    public function __construct(private readonly OAuthPersistence $persistence) {}

    public function wechatSubjectForMember(
        AuthenticatedMemberContext|TenantContext $context,
        int $memberId,
        int $terminal,
    ): string {
        if ($memberId < 1 || $terminal < 1) {
            return '';
        }

        return $this->persistence->wechatSubjectForMember($context, $memberId, $terminal);
    }
}
