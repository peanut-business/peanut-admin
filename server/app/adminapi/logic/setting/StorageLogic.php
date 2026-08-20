<?php
declare(strict_types=1);

namespace app\adminapi\logic\setting;

use app\common\logic\BaseLogic;
use app\common\service\ConfigService;
use think\facade\Cache;

/**
 * 存储设置逻辑层。
 * 引擎：local / qiniu / aliyun / qcloud，配置存 pa_config(type=storage)。
 * 注意：ConfigService::get 返回原始字符串，云引擎配置需显式 json_decode。
 */
class StorageLogic extends BaseLogic
{
    /** 引擎清单（含展示信息） */
    private const ENGINES = [
        'local'  => ['name' => '本地存储',   'path' => '存储在本地服务器'],
        'qiniu'  => ['name' => '七牛云存储', 'path' => '存储在七牛云，请前往七牛云开通存储服务'],
        'aliyun' => ['name' => '阿里云OSS',  'path' => '存储在阿里云，请前往阿里云开通存储服务'],
        'qcloud' => ['name' => '腾讯云COS',  'path' => '存储在腾讯云，请前往腾讯云开通存储服务'],
    ];

    /** @notes 存储引擎列表 */
    public static function lists(): array
    {
        self::clearError();
        $default = (string) ConfigService::get('storage', 'default', 'local');
        $data = [];
        foreach (self::ENGINES as $engine => $info) {
            $data[] = [
                'name'   => $info['name'],
                'path'   => $info['path'],
                'engine' => $engine,
                'status' => $default === $engine ? 1 : 0,
            ];
        }
        return $data;
    }

    /** @notes 某引擎的配置详情 */
    public static function detail(array $param): array
    {
        self::clearError();
        $engine  = (string) ($param['engine'] ?? 'local');
        $default = (string) ConfigService::get('storage', 'default', '');

        $defaults = [
            'local'  => [],
            'qiniu'  => ['bucket' => '', 'access_key' => '', 'secret_key' => '', 'domain' => ''],
            'aliyun' => ['bucket' => '', 'access_key' => '', 'secret_key' => '', 'domain' => ''],
            'qcloud' => ['bucket' => '', 'region' => '', 'access_key' => '', 'secret_key' => '', 'domain' => ''],
        ];

        $result = $defaults[$engine] ?? [];
        if ($engine !== 'local') {
            $result = array_merge($result, self::decode($engine));
            if (($result['secret_key'] ?? '') !== '') {
                $result['secret_key'] = '******';
            }
        }
        $result['status'] = $default === $engine ? 1 : 0;
        return $result;
    }

    /**
     * @notes 保存存储配置
     * @return bool|string true 或提示语
     */
    public static function setup(array $params)
    {
        self::clearError();
        $engine = (string) ($params['engine'] ?? 'local');
        $status = (int) ($params['status'] ?? 0);

        $config = [];
        switch ($engine) {
            case 'local':
                $config = [];
                break;
            case 'qiniu':
            case 'aliyun':
                $config = [
                    'bucket'     => $params['bucket'] ?? '',
                    'access_key' => $params['access_key'] ?? '',
                    'secret_key' => self::retainedSecret($engine, (string)($params['secret_key'] ?? '')),
                    'domain'     => $params['domain'] ?? '',
                ];
                break;
            case 'qcloud':
                $config = [
                    'bucket'     => $params['bucket'] ?? '',
                    'region'     => $params['region'] ?? '',
                    'access_key' => $params['access_key'] ?? '',
                    'secret_key' => self::retainedSecret($engine, (string)($params['secret_key'] ?? '')),
                    'domain'     => $params['domain'] ?? '',
                ];
                break;
        }
        if ($status === 1) {
            self::assertUsable($engine, $config);
        }
        ConfigService::setManyAtomic('storage', [
            $engine => $config,
            'default' => $status === 1 ? $engine : 'local',
        ]);

        self::clearCache();
        if ($engine === 'local' && $status === 0) {
            return '默认开启本地存储';
        }
        return true;
    }

    /** @notes 切换默认引擎（再次点击当前默认则回落 local） */
    public static function change(array $params): bool
    {
        self::clearError();
        try {
            $engine  = (string)($params['engine'] ?? 'local');
            $default = (string)ConfigService::get('storage', 'default', 'local');
            $next = $default === $engine ? 'local' : $engine;
            if ($next !== 'local') {
                self::assertUsable($next, self::decode($next));
            }
            ConfigService::setManyAtomic('storage', ['default' => $next]);
            self::clearCache();
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    /** 读取某引擎配置（JSON 字符串 → 数组） */
    private static function decode(string $engine): array
    {
        $raw = ConfigService::get('storage', $engine, '');
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function clearCache(): void
    {
        Cache::delete('STORAGE_DEFAULT');
        Cache::delete('STORAGE_ENGINE');
    }

    private static function retainedSecret(string $engine, string $value): string
    {
        if ($value !== '******') {
            return trim($value);
        }
        return (string)(self::decode($engine)['secret_key'] ?? '');
    }

    private static function assertUsable(string $engine, array $config): void
    {
        if ($engine === 'local') {
            return;
        }
        $required = ['bucket', 'access_key', 'secret_key', 'domain'];
        if ($engine === 'qcloud') {
            $required[] = 'region';
        }
        foreach ($required as $field) {
            if (trim((string)($config[$field] ?? '')) === '') {
                throw new \RuntimeException('云存储配置不完整，不能设为默认引擎');
            }
        }
        $domain = trim((string)$config['domain']);
        if (filter_var($domain, FILTER_VALIDATE_URL) === false
            || !in_array(strtolower((string)parse_url($domain, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new \RuntimeException('云存储访问域名无效');
        }
    }
}
