<?php
declare(strict_types=1);

namespace app\common\service\member;

/** @deprecated Compatibility subtype for the Kernel authenticated-member context. */
final class AuthenticatedMemberContext extends \PeanutAdmin\Kernel\Context\AuthenticatedMemberContext
{
    /** public readonly int $memberId; inherited from the Kernel context. */
}
