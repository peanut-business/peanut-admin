<?php
declare(strict_types=1);

namespace app\common\service\config;

use app\common\model\config\Config;
use think\facade\Db;

final class ThinkPhpInstanceConfigStore implements InstanceConfigStore
{
    public function get(string $type, string $name = '', mixed $default = null): mixed
    {
        if ($name === '') {
            return Config::where('type', $type)->column('value', 'name') ?: [];
        }

        $value = Config::where('type', $type)->where('name', $name)->value('value');
        return $value === null ? $default : $value;
    }

    public function set(string $type, string $name, mixed $value): void
    {
        $stored = is_array($value) || is_object($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            : (string)$value;
        $row = Config::where('type', $type)->where('name', $name)->findOrEmpty();
        if ($row->isEmpty()) {
            Config::create(['type' => $type, 'name' => $name, 'value' => $stored]);
            return;
        }
        $row->value = $stored;
        $row->save();
    }

    public function setManyAtomically(string $type, array $data): void
    {
        Db::transaction(function () use ($type, $data): void {
            foreach ($data as $name => $value) {
                $this->set($type, (string)$name, $value);
            }
        });
    }
}
