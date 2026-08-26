<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Model;

use app\common\model\BaseModel;

/**
 * 通知场景模型。
 */
class NoticeScene extends BaseModel
{
    protected $name = 'notice_scene';

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    /** @return string[] */
    public function getVariablesAttr(mixed $value): array
    {
        if ($value instanceof \think\model\type\Json) {
            $value = $value->value();
        }
        if (is_array($value)) {
            return array_values($value);
        }
        if ($value === null || $value === '') {
            return [];
        }

        $variables = json_decode((string) $value, true);
        return is_array($variables) ? array_values($variables) : [];
    }

    /** @param string[]|string $value */
    public function setVariablesAttr(array|string $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return (string) json_encode(array_values($value), JSON_UNESCAPED_UNICODE);
    }
}
