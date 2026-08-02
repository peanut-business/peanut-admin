<?php
declare(strict_types=1);

namespace app\common\validate;

use think\Validate;

/** 公共列表分页参数校验。 */
class ListsValidate extends Validate
{
    private const PAGE_SIZE_MAX = 25000;

    protected $rule = [
        'page_no' => 'integer|gt:0',
        'page_size' => 'integer|gt:0|pageSizeMax',
    ];

    protected function pageSizeMax($value): bool|string
    {
        return (int) $value > self::PAGE_SIZE_MAX
            ? '已超出系统限制数量，请分页查询或导出，当前最多记录数为：25000'
            : true;
    }
}
