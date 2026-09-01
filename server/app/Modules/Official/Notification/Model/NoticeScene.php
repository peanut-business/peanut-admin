<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Model;

use app\common\model\TenantOwnedModel;

/**
 * 通知场景模型。
 */
class NoticeScene extends TenantOwnedModel
{
    protected $name = 'notice_scene';

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    /** @param list<array{0:string,1:string,2:string,3:string}> $scenes */
    public static function provisionDefaults(array $scenes): void
    {
        foreach ($scenes as [$code, $name, $description, $content]) {
            if (self::where('code', $code)->find() !== null) {
                continue;
            }
            self::create([
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'recipient' => '用户',
                'variables' => json_encode(['code'], JSON_THROW_ON_ERROR),
                'sms_template_id' => '',
                'sms_content' => $content,
                'sms_status' => self::STATUS_DISABLED,
                'create_time' => 0,
                'update_time' => 0,
            ]);
        }
    }
}
