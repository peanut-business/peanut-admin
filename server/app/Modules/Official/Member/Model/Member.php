<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Model;

use app\common\model\TenantOwnedModel;
use app\common\service\FileService;
use app\Modules\Official\Member\Infrastructure\Persistence\MemberTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use think\model\concern\SoftDelete;

class Member extends TenantOwnedModel
{
    use SoftDelete;
    protected $name       = 'member';
    protected $deleteTime = 'delete_time';
    protected $hidden     = ['password'];

    public function setAvatarAttr($value): string
    {
        return FileService::setFileUrl((string)$value);
    }

    /** 生成唯一会员编号（M + 10位时间戳 + 4位随机） */
    public static function generateSn(TenantContext|TenantSystemContext $context): string
    {
        do {
            $sn = 'M' . date('YmdHi') . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (MemberTenantRepository::members($context)->where('sn', $sn)->count() > 0);
        return $sn;
    }
}
