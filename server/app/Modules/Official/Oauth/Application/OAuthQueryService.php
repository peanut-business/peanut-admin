<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Application;

use app\Modules\Official\Oauth\Contracts\OAuthQueries;
use app\common\service\member\AuthenticatedMemberContext;
use app\common\service\oauth\OAuthTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class OAuthQueryService implements OAuthQueries
{
    public function wechatSubjectForMember(
        AuthenticatedMemberContext|TenantContext $context,
        int $memberId,
        int $terminal,
    ): string {
        if ($memberId < 1 || $terminal < 1) {
            return '';
        }

        return (string) OAuthTenantRepository::identities($context)->where([
            'provider' => 'wechat',
            'member_id' => $memberId,
            'terminal' => $terminal,
        ])->value('subject');
    }
}
