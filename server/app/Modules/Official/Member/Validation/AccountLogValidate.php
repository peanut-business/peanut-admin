<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Validation;

use think\Validate;

class AccountLogValidate extends Validate
{
    protected $rule = [
        'page_no' => 'integer|gt:0',
        'page_size' => 'integer|gt:0|pageSizeMax',
        'page_type' => 'in:0,1',
        'change_type' => 'integer',
        'start_time' => 'date',
        'end_time' => 'date|gt:start_time',
        'export' => 'in:1,2',
    ];

    protected $message = [
        'end_time.gt' => '搜索的时间范围不正确',
    ];

    public function sceneLists(): self
    {
        return $this->only([
            'page_no', 'page_size', 'page_type', 'change_type',
            'start_time', 'end_time', 'export',
        ]);
    }

    protected function pageSizeMax($value): bool|string
    {
        if ((int)$value > 25000) {
            return '已超出系统限制数量，请分页查询或导出，当前最多记录数为：25000';
        }
        return true;
    }
}
