<?php
declare(strict_types=1);

namespace app\adminapi\validate\setting;

use think\Validate;

class StorageValidate extends Validate
{
    protected $rule = [
        'engine' => 'require|in:local,qiniu,aliyun,qcloud',
        'status' => 'require|in:0,1',
        'bucket' => 'max:255',
        'region' => 'max:100',
        'access_key' => 'max:255',
        'secret_key' => 'max:1000',
        'domain' => 'max:500',
    ];

    protected $message = [
        'engine.require' => '请选择存储引擎',
        'engine.in'      => '存储引擎值错误',
        'status.require' => '请选择状态',
        'status.in'      => '状态值错误',
    ];

    protected $scene = [
        'detail' => ['engine'],
        'setup'  => ['engine', 'status' => 'require|in:0,1|checkCloudConfig', 'bucket', 'region', 'access_key', 'secret_key', 'domain'],
        'change' => ['engine'],
    ];

    protected function checkCloudConfig($value, $rule, array $data): bool|string
    {
        if ((int)$value !== 1 || ($data['engine'] ?? 'local') === 'local') {
            return true;
        }
        $required = ['bucket', 'access_key', 'secret_key', 'domain'];
        if (($data['engine'] ?? '') === 'qcloud') {
            $required[] = 'region';
        }
        foreach ($required as $field) {
            if (trim((string)($data[$field] ?? '')) === '') {
                return '启用云存储前请完整填写配置';
            }
        }
        $domain = trim((string)$data['domain']);
        if (filter_var($domain, FILTER_VALIDATE_URL) === false
            || !in_array(strtolower((string)parse_url($domain, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return '云存储访问域名必须为 http/https 绝对地址';
        }
        return true;
    }
}
