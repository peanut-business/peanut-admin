<?php
declare(strict_types=1);

namespace app\platform\infrastructure;

use app\common\model\decoration\DecoratePage;
use app\common\model\decoration\DecorateTabbar;
use app\common\model\decoration\DecorationTabbarSetting;
use app\common\model\setting\TransactionSetting;
use app\platform\service\TenantApplicationBootstrapPersistence;

final class ThinkPhpTenantApplicationBootstrapPersistence implements TenantApplicationBootstrapPersistence
{
    public function seedDecoration(array $pages, array $tabbars): void
    {
        $existingPageTypes = array_fill_keys(array_map(
            'intval',
            DecoratePage::whereIn('type', array_column($pages, 0))->column('type'),
        ), true);
        $missingPages = [];
        foreach ($pages as [$type, $name, $data, $meta]) {
            if (!isset($existingPageTypes[$type])) {
                $missingPages[] = compact('type', 'name', 'data', 'meta') + [
                    'create_time' => 0,
                    'update_time' => 0,
                ];
            }
        }
        if ($missingPages !== []) {
            (new DecoratePage())->saveAll($missingPages);
        }

        $existingPositions = array_fill_keys(array_map(
            'intval',
            DecorateTabbar::whereIn('position', array_column($tabbars, 0))->column('position'),
        ), true);
        $missingTabbars = [];
        foreach ($tabbars as [$position, $name, $link]) {
            if (!isset($existingPositions[$position])) {
                $missingTabbars[] = compact('position', 'name', 'link') + [
                    'selected' => '',
                    'unselected' => '',
                    'is_show' => 1,
                    'create_time' => 0,
                    'update_time' => 0,
                ];
            }
        }
        if ($missingTabbars !== []) {
            (new DecorateTabbar())->saveAll($missingTabbars);
        }
    }

    public function ensureSettings(array $tabbarSetting, array $transactionSetting): void
    {
        if (DecorationTabbarSetting::where([])->find() === null) {
            DecorationTabbarSetting::create($tabbarSetting);
        }
        if (TransactionSetting::where([])->find() === null) {
            TransactionSetting::create($transactionSetting);
        }
    }
}
