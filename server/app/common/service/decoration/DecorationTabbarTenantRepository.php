<?php
declare(strict_types=1);

namespace app\common\service\decoration;

use app\common\model\decoration\DecorateTabbar;
use app\common\model\decoration\DecorationTabbarSetting;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class DecorationTabbarTenantRepository
{
    private const DEFAULT_STYLE = [
        'default_color' => '#666666',
        'selected_color' => '#2F80ED',
    ];

    public static function items(
        TenantContext|TenantSystemContext $context,
        string $operation = ''
    ) {
        return DecorateTabbar::where(
            'tenant_id',
            DecorationTenantContext::tenantId($context, $operation)
        );
    }

    public static function settings(
        TenantContext|TenantSystemContext $context,
        string $operation = ''
    ) {
        return DecorationTabbarSetting::where(
            'tenant_id',
            DecorationTenantContext::tenantId($context, $operation)
        );
    }

    public static function readStyle(
        TenantContext|TenantSystemContext $context,
        string $operation = ''
    ): array {
        $raw = self::settings($context, $operation)->value('style');
        if ($raw === null) {
            return self::DEFAULT_STYLE;
        }
        $style = json_decode((string)$raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($style) || array_is_list($style)) {
            throw new \RuntimeException('Tabbar 样式配置无效');
        }
        return $style;
    }

    /** @param list<array<string,mixed>> $items */
    public static function replace(TenantContext $context, array $style, array $items): void
    {
        $tenantId = DecorationTenantContext::tenantId($context);
        $setting = self::settings($context)->lock(true)->findOrEmpty();
        $storedStyle = json_encode(
            $style,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        if ($setting->isEmpty()) {
            DecorationTabbarSetting::create(['tenant_id' => $tenantId, 'style' => $storedStyle]);
        } else {
            $setting->style = $storedStyle;
            $setting->save();
        }

        self::items($context)->delete();
        foreach ($items as $position => $item) {
            DecorateTabbar::create([
                'tenant_id' => $tenantId,
                'position' => $position,
                'name' => trim((string)$item['name']),
                'selected' => (string)$item['selected'],
                'unselected' => (string)$item['unselected'],
                'link' => json_encode(
                    $item['link'],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                ),
                'is_show' => (int)$item['is_show'],
            ]);
        }
    }

    private function __construct()
    {
    }
}
