<?php
declare(strict_types=1);

namespace tests\Ablation;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

/**
 * EXP-01: Multi-Tenant Data Isolation Ablation Experiment
 * Evaluates the necessity of explicit composite Tenant JOIN conditions vs naive ID-only JOINs.
 */
final class DataIsolationAblationTest
{
    public static function run(): void
    {
        echo "====================================================================\n";
        echo " [EXP-01] 消融实验：多租户数据隔离防御 (Data Isolation Ablation)\n";
        echo "====================================================================\n\n";

        $bindings = [
            ['id' => 101, 'tenant_id' => 1, 'provider' => 'wechat', 'config' => '{"app_id":"wx_tenant_1"}'],
            ['id' => 202, 'tenant_id' => 2, 'provider' => 'wechat', 'config' => '{"app_id":"wx_tenant_2"}'],
        ];

        $grants = [
            ['id' => 1, 'tenant_id' => 1, 'provider' => 'wechat', 'external_binding_id' => 101],
            ['id' => 2, 'tenant_id' => 2, 'provider' => 'wechat', 'external_binding_id' => 101],
        ];

        echo "1. 场景构造：\n";
        echo "   - 租户 1 合法拥有微信支付配置 (binding_id: 101)\n";
        echo "   - 租户 2 恶意提交或数据库脏数据存在 cross-grant (tenant_id: 2 -> binding_id: 101)\n\n";

        $tenantId = 2;
        $targetBindingId = 101;

        // Baseline: 严格校验 binding 的 tenant_id
        $baselineResult = null;
        $baselineBlocked = false;
        foreach ($grants as $g) {
            if ($g['tenant_id'] === $tenantId && $g['external_binding_id'] === $targetBindingId) {
                foreach ($bindings as $b) {
                    if ($b['id'] === $g['external_binding_id'] && $b['tenant_id'] === $tenantId) {
                        $baselineResult = $b;
                    }
                }
                if ($baselineResult === null) {
                    $baselineBlocked = true;
                }
            }
        }

        // Ablated: 仅按 ID 关联，不校验 b.tenant_id
        $ablatedResult = null;
        $ablatedLeaked = false;
        foreach ($grants as $g) {
            if ($g['tenant_id'] === $tenantId && $g['external_binding_id'] === $targetBindingId) {
                foreach ($bindings as $b) {
                    if ($b['id'] === $g['external_binding_id']) {
                        $ablatedResult = $b;
                        $ablatedLeaked = true;
                    }
                }
            }
        }

        echo "2. 消融实验结果比对：\n";
        printf("   %-25s | %-15s | %-20s\n", "架构模式", "越权读取结果", "安全判定");
        echo "   --------------------------+-----------------+---------------------\n";
        printf("   %-23s | %-15s | %-20s\n", "Baseline (现行强约束)", $baselineBlocked ? "None (已拦截)" : "Leaked", $baselineBlocked ? "✅ 100% 安全隔离" : "❌ 存在漏洞");
        printf("   %-23s | %-15s | %-20s\n", "Ablated (消融租户校验)", $ablatedLeaked ? "wx_tenant_1" : "None", $ablatedLeaked ? "❌ P0 级跨租户泄漏" : "✅ 安全");

        echo "\n3. 结论：\n";
        echo "   在底层数据库未建立 (tenant_id, external_binding_id) 复合外键前，\n";
        echo "   应用层 JOIN / Command 显式约束 `b.tenant_id = g.tenant_id` 是防止越权的绝对核心防线。\n\n";
    }
}

DataIsolationAblationTest::run();
