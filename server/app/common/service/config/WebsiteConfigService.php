<?php
declare(strict_types=1);

namespace app\common\service\config;

use app\common\contract\config\WebsiteConfigStore;
use Closure;
use InvalidArgumentException;

final class WebsiteConfigService
{
    private const FIELDS = [
        'name' => '',
        'web_favicon' => '',
        'web_logo' => '',
        'login_image' => '',
        'shop_name' => '',
        'shop_logo' => '',
        'pc_logo' => '',
        'pc_title' => '',
        'pc_ico' => '',
        'pc_desc' => '',
        'pc_keywords' => '',
        'h5_favicon' => '',
    ];

    private const IMAGE_FIELDS = [
        'web_favicon', 'web_logo', 'login_image', 'shop_logo',
        'pc_logo', 'pc_ico', 'h5_favicon',
    ];

    private const MAX_LENGTHS = [
        'name' => 60,
        'web_favicon' => 500,
        'web_logo' => 500,
        'login_image' => 500,
        'shop_name' => 60,
        'shop_logo' => 500,
        'pc_logo' => 500,
        'pc_title' => 120,
        'pc_ico' => 500,
        'pc_desc' => 500,
        'pc_keywords' => 500,
        'h5_favicon' => 500,
    ];

    public function __construct(
        private WebsiteConfigStore $store,
        private Closure $urlForRead,
        private Closure $urlForStorage,
    ) {}

    /** @return array<string, string> */
    public function get(): array
    {
        $stored = $this->store->read();
        $result = [];
        foreach (self::FIELDS as $field => $default) {
            $value = is_string($stored[$field] ?? null) ? $stored[$field] : $default;
            $result[$field] = $this->isImage($field)
                ? (string)($this->urlForRead)($value)
                : $value;
        }
        return $result;
    }

    public function save(array $params): void
    {
        $normalized = [];
        foreach (self::FIELDS as $field => $default) {
            $raw = $params[$field] ?? $default;
            if (!is_string($raw)) {
                throw new InvalidArgumentException($this->label($field) . '格式错误');
            }
            $value = trim($raw);
            if (($field === 'name' || $field === 'shop_name') && $value === '') {
                throw new InvalidArgumentException($this->label($field) . '不能为空');
            }
            if (mb_strlen($value) > self::MAX_LENGTHS[$field]) {
                throw new InvalidArgumentException($this->label($field) . '长度超出限制');
            }
            $normalized[$field] = $this->isImage($field)
                ? (string)($this->urlForStorage)($value)
                : $value;
        }

        $this->store->replaceAtomically($normalized);
    }

    /** @return list<string> */
    public static function fields(): array
    {
        return array_keys(self::FIELDS);
    }

    private function isImage(string $field): bool
    {
        return in_array($field, self::IMAGE_FIELDS, true);
    }

    private function label(string $field): string
    {
        return match ($field) {
            'name' => '网站名称',
            'shop_name' => '商城名称',
            'pc_title' => 'PC 页面标题',
            default => '网站配置字段 ' . $field,
        };
    }
}
