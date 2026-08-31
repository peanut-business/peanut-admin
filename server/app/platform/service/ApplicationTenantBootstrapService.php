<?php
declare(strict_types=1);

namespace app\platform\service;

use PDO;
use app\Modules\Official\Notification\Contracts\NotificationCommands;
use app\common\execution\ExecutionContext;
use app\common\execution\ExecutionContextStore;
use app\common\service\config\BrandDefaults;
use app\common\service\tenant\TenantSettingsBootstrapRuntimeFactory;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** Seeds the application-owned defaults that every new Tenant must receive. */
final readonly class ApplicationTenantBootstrapService
{
    private const REQUIRED_TABLES = [
        'pa_crontab',
        'pa_customer_service_setting',
        'pa_decorate_page',
        'pa_decorate_tabbar',
        'pa_decorate_tabbar_setting',
        'pa_external_channel_binding',
        'pa_notice_scene',
        'pa_permission',
        'pa_role_permission',
        'pa_tenant_setting',
        'pa_transaction_setting',
    ];

    public function __construct(
        private PDO $pdo,
        private NotificationCommands $notifications,
        private ExecutionContextStore $executionContexts,
    ) {
    }

    public function provision(int $tenantId, int $ownerMemberId, int $ownerRoleId, string $tenantCode): void
    {
        if (min($tenantId, $ownerMemberId, $ownerRoleId) < 1 || trim($tenantCode) === '') {
            throw new \DomainException('TENANT_APPLICATION_BOOTSTRAP_INPUT_INVALID');
        }
        if (!$this->applicationSchemaPresent()) {
            return;
        }

        $this->grantOwnerPermissions($tenantId, $ownerMemberId, $ownerRoleId);
        $this->seedCrontab($tenantId);
        $this->seedNoticeScenes($tenantId, $tenantCode);
        $this->seedDecoration($tenantId);
        $this->seedSettings($tenantId);
        $this->seedExternalBindings($tenantId, $tenantCode);
    }

    private function applicationSchemaPresent(): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('
            . implode(',', array_fill(0, count(self::REQUIRED_TABLES), '?')) . ')'
        );
        $statement->execute(self::REQUIRED_TABLES);
        $tables = $statement->fetchAll(PDO::FETCH_COLUMN);
        if ($tables === []) {
            // Core package tests intentionally exercise this adapter without the application schema.
            return false;
        }
        sort($tables, SORT_STRING);
        $expected = self::REQUIRED_TABLES;
        sort($expected, SORT_STRING);
        if ($tables !== $expected) {
            throw new \DomainException('TENANT_APPLICATION_SCHEMA_INCOMPLETE');
        }
        return true;
    }

    private function grantOwnerPermissions(int $tenantId, int $ownerMemberId, int $ownerRoleId): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT IGNORE INTO pa_role_permission
  (tenant_id, role_id, permission_id, granted_by_member_id, granted_at)
SELECT :tenant_id, :role_id, permission.id, :member_id, UTC_TIMESTAMP(3)
FROM pa_permission permission
WHERE permission.module_key = 'peanut.admin' AND permission.status = 'active'
SQL);
        $statement->execute([
            'tenant_id' => $tenantId,
            'role_id' => $ownerRoleId,
            'member_id' => $ownerMemberId,
        ]);
    }

    private function seedCrontab(int $tenantId): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_crontab
  (name,type,command,params,status,expression,error,last_time,time,max_time,sort,remark,create_time,update_time,tenant_id)
SELECT :name,1,:command,'',1,:expression,'',0,0,0,:sort,:remark,0,0,:tenant_id
WHERE NOT EXISTS (
  SELECT 1 FROM pa_crontab WHERE tenant_id = :tenant_scope AND command = :command_scope
)
SQL);
        foreach ([
            ['退款状态收敛', 'refund:reconcile', '* * * * *', 100, '查询支付渠道并收敛充值退款最终状态'],
            ['代码生成归档清理', 'generator:cleanup', '0 3 * * *', 20, '清理已使用或过期的代码生成下载令牌和隔离归档'],
        ] as [$name, $command, $expression, $sort, $remark]) {
            $statement->execute([
                'name' => $name,
                'command' => $command,
                'expression' => $expression,
                'sort' => $sort,
                'remark' => $remark,
                'tenant_id' => $tenantId,
                'tenant_scope' => $tenantId,
                'command_scope' => $command,
            ]);
        }
    }

    private function seedNoticeScenes(int $tenantId, string $tenantCode): void
    {
        $operationId = $this->executionContexts->current()?->requestId
            ?? 'tenant-bootstrap:' . $tenantCode;
        $this->executionContexts->run(
            ExecutionContext::system(new TenantSystemContext(
                $tenantId,
                'platform.tenant-bootstrap',
                'notification.provision-tenant-defaults',
                $operationId,
            )),
            fn() => $this->notifications->provisionTenantDefaults(),
        );
    }

    private function seedDecoration(int $tenantId): void
    {
        $pages = [
            [1, '移动端首页', '[{"title":"搜索","name":"search","disabled":1,"content":{},"styles":{}},{"title":"首页轮播图","name":"banner","content":{"enabled":1,"style":1,"bg_style":1,"data":[{"is_show":1,"image":"","bg":"","name":"","link":{"target_type":"shop","target":"home"}}]},"styles":{}},{"title":"导航菜单","name":"nav","content":{"enabled":1,"style":2,"per_line":5,"show_line":2,"data":[{"is_show":1,"image":"","name":"资讯中心","link":{"target_type":"shop","target":"news"}}]},"styles":{}},{"title":"首页中部轮播图","name":"middle-banner","content":{"enabled":1,"data":[{"is_show":1,"image":"","name":"","link":{"target_type":"shop","target":"home"}}]},"styles":{}},{"title":"资讯","name":"news","disabled":1,"content":{},"styles":{}}]', '[{"title":"页面设置","name":"page-meta","content":{"title":"首页","title_type":1,"title_img":"","bg_type":1,"bg_color":"#2F80ED","bg_image":"","text_color":1},"styles":{}}]'],
            [2, '个人中心', '[{"title":"用户信息","name":"user-info","disabled":1,"content":{},"styles":{}},{"title":"我的服务","name":"my-service","content":{"enabled":1,"style":1,"title":"我的服务","data":[{"is_show":1,"image":"","name":"我的收藏","link":{"target_type":"shop","target":"favorites"}}]},"styles":{}},{"title":"个人中心广告图","name":"user-banner","content":{"enabled":1,"data":[{"is_show":1,"image":"","name":"","link":{"target_type":"shop","target":"profile"}}]},"styles":{}}]', '[{"title":"页面设置","name":"page-meta","content":{"title":"个人中心","title_type":1,"title_img":"","bg_type":1,"bg_color":"#2F80ED","bg_image":"","text_color":1},"styles":{}}]'],
            [3, '客服设置', '[{"title":"客服设置","name":"customer-service","content":{"title":"添加客服二维码","time":"9:30 - 19:00","mobile":"","qrcode":"","remark":"长按添加客服或拨打客服热线"},"styles":{}}]', '[]'],
            [4, 'PC 首页', '[{"title":"首页轮播图","name":"pc-banner","content":{"enabled":1,"data":[{"image":"","name":"","link":{"target_type":"shop","target":"home"}}]},"styles":{"position":"absolute","left":"40px","top":"75px","width":"750px","height":"340px"}}]', '[]'],
            [5, '系统风格', '{"themeColorId":3,"topTextColor":"white","navigationBarColor":"#A74BFD","themeColor1":"#A74BFD","themeColor2":"#CB60FF","buttonColor":"white"}', '[]'],
        ];
        $page = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_decorate_page (type,name,data,meta,create_time,update_time,tenant_id)
SELECT :type,:name,:data,:meta,0,0,:tenant_id
WHERE NOT EXISTS (
  SELECT 1 FROM pa_decorate_page WHERE tenant_id = :tenant_scope AND type = :type_scope
)
SQL);
        foreach ($pages as [$type, $name, $data, $meta]) {
            $page->execute([
                'type' => $type,
                'name' => $name,
                'data' => $data,
                'meta' => $meta,
                'tenant_id' => $tenantId,
                'tenant_scope' => $tenantId,
                'type_scope' => $type,
            ]);
        }

        $tabbar = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_decorate_tabbar
  (position,name,selected,unselected,link,is_show,create_time,update_time,tenant_id)
SELECT :position,:name,'','',:link,1,0,0,:tenant_id
WHERE NOT EXISTS (
  SELECT 1 FROM pa_decorate_tabbar WHERE tenant_id = :tenant_scope AND position = :position_scope
)
SQL);
        foreach ([
            [0, '首页', '{"target_type":"shop","target":"home"}'],
            [1, '资讯', '{"target_type":"shop","target":"news"}'],
            [2, '我的', '{"target_type":"shop","target":"profile"}'],
        ] as [$position, $name, $link]) {
            $tabbar->execute([
                'position' => $position,
                'name' => $name,
                'link' => $link,
                'tenant_id' => $tenantId,
                'tenant_scope' => $tenantId,
                'position_scope' => $position,
            ]);
        }
    }

    private function seedSettings(int $tenantId): void
    {
        $documents = [
            'website' => BrandDefaults::website(),
            'copyright' => ['config' => []],
            'agreement' => [
                'service_title' => '',
                'service_content' => '',
                'privacy_title' => '',
                'privacy_content' => '',
            ],
            'site-statistics' => ['clarity_code' => ''],
            'member-profile' => ['user_avatar' => 'brand/avatar-member.svg'],
            'login' => [
                'login_way' => [1, 2],
                'coerce_mobile' => 0,
                'login_agreement' => 0,
                'third_auth' => 0,
                'wechat_auth' => 0,
            ],
            'web-page' => ['status' => 1, 'page_status' => 0, 'page_url' => ''],
            'hot-search' => ['status' => 0],
        ];
        TenantSettingsBootstrapRuntimeFactory::forProvisioning($this->pdo)
            ->seedDefaults($tenantId, $documents);
        $this->insertIgnore(
            'INSERT IGNORE INTO pa_customer_service_setting (tenant_id,qr_file_id,wechat,phone,service_time,create_time,update_time) VALUES (?,NULL,\'\',\'\',\'\',0,0)',
            [$tenantId]
        );
        $this->insertIgnore(
            'INSERT IGNORE INTO pa_decorate_tabbar_setting (tenant_id,style,create_time,update_time) VALUES (?,\'{"default_color":"#666666","selected_color":"#2F80ED"}\',0,0)',
            [$tenantId]
        );
        $this->insertIgnore(
            'INSERT IGNORE INTO pa_transaction_setting (tenant_id,cancel_unpaid_orders,cancel_unpaid_orders_times,verification_orders,verification_orders_times,create_time,update_time) VALUES (?,1,30,1,24,0,0)',
            [$tenantId]
        );
    }

    private function seedExternalBindings(int $tenantId, string $tenantCode): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_external_channel_binding
  (tenant_id,provider,callback_key,identity_hash,identity_hint,config_json,status,create_time,update_time)
SELECT :tenant_id,:provider,:callback_key,:identity_hash,:identity_hint,JSON_OBJECT(),0,0,0
WHERE NOT EXISTS (
  SELECT 1 FROM pa_external_channel_binding WHERE tenant_id = :tenant_scope AND provider = :provider_scope
)
SQL);
        foreach ([
            'payment.wechat',
            'payment.alipay',
            'wechat.official-account',
            'oauth.wechat.oa',
            'oauth.wechat.mini-program',
            'oauth.wechat.open-pc',
        ] as $provider) {
            $statement->execute([
                'tenant_id' => $tenantId,
                'provider' => $provider,
                'callback_key' => bin2hex(random_bytes(32)),
                'identity_hash' => hash('sha256', "unconfigured:{$tenantCode}:{$provider}"),
                'identity_hint' => '',
                'tenant_scope' => $tenantId,
                'provider_scope' => $provider,
            ]);
        }
    }

    /** @param list<mixed> $values */
    private function insertIgnore(string $sql, array $values): void
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($values);
    }
}
