<?php
declare(strict_types=1);

namespace app\modules\official\oauth\services;

use app\modules\official\oauth\contracts\OAuthQueries;
use app\modules\official\oauth\contracts\OAuthPersistence;
use PeanutAdmin\Kernel\Context\AuthenticatedMemberContext;
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
