<?php
declare(strict_types=1);

namespace app\Modules\Official\File\Model;

use app\common\model\BaseModel;
use app\common\service\FileService;
use think\model\concern\SoftDelete;

class File extends BaseModel
{
    use SoftDelete;
    protected $name = 'file';
    protected $deleteTime = 'delete_time';

    /** 追加：绝对可访问 URL（相对 uri 拼域名） */
    public function getUrlAttr($value, $data): string
    {
        return FileService::getFileUrl((string)($data['file_key'] ?? ''));
    }
}
