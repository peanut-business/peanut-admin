<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\model\config\Config;
use think\facade\Db;

/**
 * 实例级配置读写服务。
 *
 * pa_config 仅保存 Platform/部署拥有的配置（当前为 storage）。
 * Tenant 可修改的展示、登录、通知、支付等设置不得使用本服务。
 * 值统一以字符串存储；数组/对象写入时 json 编码，读取时按需由调用方解码。
 */
class ConfigService
{
    /**
     * 读取配置
     * - name 为空：返回该 type 下的全部 name=>value 映射
     * - name 非空：返回单个值，不存在则返回 $default
     */
    public static function get(string $type, string $name = '', mixed $default = null): mixed
    {
        if ($name === '') {
            $rows = Config::where('type', $type)->column('value', 'name');
            return $rows ?: [];
        }

        $value = Config::where('type', $type)->where('name', $name)->value('value');
        return $value === null ? $default : $value;
    }

    /** 写入单个配置（存在则更新，否则新增） */
    public static function set(string $type, string $name, mixed $value): void
    {
        $stored = is_array($value) || is_object($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE)
            : (string) $value;

        $row = Config::where('type', $type)->where('name', $name)->findOrEmpty();
        if ($row->isEmpty()) {
            Config::create(['type' => $type, 'name' => $name, 'value' => $stored]);
        } else {
            $row->value = $stored;
            $row->save();
        }
    }

    /** 批量写入某 type 下的多个配置 */
    public static function setMany(string $type, array $data): void
    {
        foreach ($data as $name => $value) {
            self::set($type, (string) $name, $value);
        }
    }

    /** 在同一事务内批量写入配置，避免相关配置出现部分更新。 */
    public static function setManyAtomic(string $type, array $data): void
    {
        Db::transaction(function () use ($type, $data): void {
            self::setMany($type, $data);
        });
    }
}
