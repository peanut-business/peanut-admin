<?php
declare(strict_types=1);

namespace app\adminapi\validate\setting;

use think\Validate;

class StorageValidate extends Validate
{
    protected $rule = [
        'engine' => 'require|in:local,qiniu,aliyun,qcloud',
        'status' => 'require|in:0,1',
    ];

    protected $message = [
        'engine.require' => '请选择存储引擎',
        'engine.in'      => '存储引擎值错误',
        'status.require' => '请选择状态',
        'status.in'      => '状态值错误',
    ];

    protected $scene = [
        'detail' => ['engine'],
        'setup'  => ['engine', 'status'],
        'change' => ['engine'],
    ];
}
