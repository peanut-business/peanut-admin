<?php
declare(strict_types=1);

namespace app\common\service\config;

use RuntimeException;

final class BrandDefaults
{
    /** @return array<string, string> */
    public static function website(): array
    {
        $path = dirname(__DIR__, 4) . '/config/brand.json';
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('无法读取品牌默认配置');
        }

        $manifest = json_decode($json, true);
        if (!is_array($manifest)
            || ($manifest['schema_version'] ?? null) !== 1
            || !is_array($manifest['website'] ?? null)) {
            throw new RuntimeException('品牌默认配置格式错误');
        }

        $website = [];
        foreach ($manifest['website'] as $field => $value) {
            if (!is_string($field) || !is_string($value)) {
                throw new RuntimeException('品牌默认字段必须是字符串');
            }
            $website[$field] = $value;
        }
        return $website;
    }
}
