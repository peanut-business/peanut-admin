<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Contracts;

use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;

/** Read-only OAuth identity lookup exposed to dependent Modules. */
interface OAuthQueries
{
    public function wechatSubjectForMember(
        AuthenticatedMemberContext|TenantContext $context,
        int $memberId,
        int $terminal,
    ): string;
}
