<?php
declare(strict_types=1);

use app\common\service\config\BrandDefaults;
use app\common\service\config\WebsiteConfigService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function brandExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$website = BrandDefaults::website();
brandExpect(
    array_keys($website) === WebsiteConfigService::fields(),
    'bootstrap manifest and website Runtime fields must match exactly'
);
brandExpect($website['name'] === 'Peanut Admin', 'default product name must be complete');
brandExpect($website['shop_name'] === 'Peanut Admin', 'default consumer name must be complete');
brandExpect($website['pc_title'] === 'Peanut Admin', 'default PC title must be complete');
brandExpect($website['official_url'] === '', 'environment-specific official URL must not be a template default');
brandExpect(
    $website['github_url'] === 'https://github.com/peanut-business/peanut-admin',
    'GitHub entry must point to the application source repository'
);

$publicRoot = dirname(__DIR__, 2) . '/public/';
foreach (['web_favicon', 'web_logo', 'login_image', 'shop_logo', 'pc_logo', 'pc_ico', 'h5_favicon'] as $field) {
    $asset = $publicRoot . $website[$field];
    brandExpect(is_file($asset), "missing bootstrap asset for {$field}");
    $content = file_get_contents($asset);
    brandExpect(is_string($content) && str_contains($content, '<svg'), "invalid SVG asset for {$field}");
}

echo "PB08A-BRAND-SCAFFOLD-001 bootstrap passed\n";
