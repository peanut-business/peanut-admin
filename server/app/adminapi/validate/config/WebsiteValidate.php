<?php
declare(strict_types=1);

namespace app\adminapi\validate\config;

use think\Validate;

class WebsiteValidate extends Validate
{
    protected $rule = [
        'name'      => 'require|max:60',
        'logo'      => 'max:255',
        'favicon'   => 'max:255',
        'copyright' => 'max:255',
        'icp'       => 'max:60',
    ];

    protected $message = [
        'name.require' => '网站名称不能为空',
        'name.max'     => '网站名称最多 60 个字符',
    ];
}
