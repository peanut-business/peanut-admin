<?php
declare(strict_types=1);

namespace app\platform\infrastructure;

use PDO;
use app\common\execution\CurrentExecutionContext;
use app\platform\service\TenantApplicationBootstrapPersistence;

/** Framework-free application bootstrap persistence for installer and deployment CLI processes. */
final readonly class PdoTenantApplicationBootstrapPersistence implements TenantApplicationBootstrapPersistence
{
    public function __construct(
        private PDO $pdo,
        private CurrentExecutionContext $currentExecution,
    ) {
    }

    public function seedDecoration(array $pages, array $tabbars): void
    {
        $tenantId = $this->tenantId();
        $pageStatement = $this->pdo->prepare(<<<'SQL'
INSERT IGNORE INTO pa_decorate_page
  (tenant_id,type,name,data,meta,create_time,update_time)
VALUES
  (:tenant_id,:type,:name,:data,:meta,0,0)
SQL);
        foreach ($pages as [$type, $name, $data, $meta]) {
            $pageStatement->execute([
                'tenant_id' => $tenantId,
                'type' => $type,
                'name' => $name,
                'data' => $data,
                'meta' => $meta,
            ]);
        }

        $tabbarStatement = $this->pdo->prepare(<<<'SQL'
INSERT IGNORE INTO pa_decorate_tabbar
  (tenant_id,position,name,link,is_show,selected,unselected,create_time,update_time)
VALUES
  (:tenant_id,:position,:name,:link,1,'','',0,0)
SQL);
        foreach ($tabbars as [$position, $name, $link]) {
            $tabbarStatement->execute([
                'tenant_id' => $tenantId,
                'position' => $position,
                'name' => $name,
                'link' => $link,
            ]);
        }
    }

    public function ensureSettings(array $tabbarSetting, array $transactionSetting): void
    {
        $tenantId = $this->tenantId();
        $tabbarStatement = $this->pdo->prepare(<<<'SQL'
INSERT IGNORE INTO pa_decorate_tabbar_setting
  (tenant_id,style,create_time,update_time)
VALUES
  (:tenant_id,:style,:create_time,:update_time)
SQL);
        $tabbarStatement->execute([
            'tenant_id' => $tenantId,
            'style' => (string)($tabbarSetting['style'] ?? '{}'),
            'create_time' => (int)($tabbarSetting['create_time'] ?? 0),
            'update_time' => (int)($tabbarSetting['update_time'] ?? 0),
        ]);

        $transactionStatement = $this->pdo->prepare(<<<'SQL'
INSERT IGNORE INTO pa_transaction_setting
  (tenant_id,cancel_unpaid_orders,cancel_unpaid_orders_times,verification_orders,verification_orders_times,create_time,update_time)
VALUES
  (:tenant_id,:cancel_unpaid_orders,:cancel_unpaid_orders_times,:verification_orders,:verification_orders_times,:create_time,:update_time)
SQL);
        $transactionStatement->execute([
            'tenant_id' => $tenantId,
            'cancel_unpaid_orders' => (int)($transactionSetting['cancel_unpaid_orders'] ?? 1),
            'cancel_unpaid_orders_times' => (int)($transactionSetting['cancel_unpaid_orders_times'] ?? 30),
            'verification_orders' => (int)($transactionSetting['verification_orders'] ?? 1),
            'verification_orders_times' => (int)($transactionSetting['verification_orders_times'] ?? 24),
            'create_time' => (int)($transactionSetting['create_time'] ?? 0),
            'update_time' => (int)($transactionSetting['update_time'] ?? 0),
        ]);
    }

    private function tenantId(): int
    {
        $system = $this->currentExecution->system();
        if ($system->tenantId < 1
            || $system->actorKey !== 'platform.tenant-bootstrap'
            || $system->operationId === '') {
            throw new \DomainException('TENANT_APPLICATION_BOOTSTRAP_CONTEXT_INVALID');
        }
        return $system->tenantId;
    }
}
