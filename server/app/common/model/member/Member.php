<?php
declare(strict_types=1);

namespace app\common\model\member;

use app\common\model\BaseModel;
use app\common\service\FileService;
use think\model\concern\SoftDelete;

class Member extends BaseModel
{
    use SoftDelete;
    protected $name       = 'member';
    protected $deleteTime = 'delete_time';
    protected $hidden     = ['password'];

    public function tags()
    {
        return $this->belongsToMany(MemberTag::class, 'member_tag_relation', 'tag_id', 'member_id');
    }

    public function getAvatarAttr($value): string
    {
        return FileService::getFileUrl((string)$value);
    }

    public function setAvatarAttr($value): string
    {
        return FileService::setFileUrl((string)$value);
    }

    /** 生成唯一会员编号（M + 10位时间戳 + 4位随机） */
    public static function generateSn(): string
    {
        do {
            $sn = 'M' . date('YmdHi') . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (self::where('sn', $sn)->count() > 0);
        return $sn;
    }
}
