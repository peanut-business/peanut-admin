<?php
declare(strict_types=1);

namespace app\platform\query;

use app\platform\context\PlatformOperatorContext;
use app\platform\service\PlatformOperatorSessionService;
use PDO;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use PeanutAdmin\Kernel\Authorization\Application\PageRequest;

final readonly class PlatformControlPlaneQueryService
{
    public function __construct(
        private PDO $pdo,
        private PlatformOperatorSessionService $sessions
    ) {
    }

    /** @return array{items:list<array<string,mixed>>,total:int} */
    public function operators(PlatformOperatorContext $context, PageRequest $page): array
    {
        $this->sessions->assertAllowed($context, 'platform.operator.read');
        return $this->page(<<<'SQL'
SELECT po.id, po.account_id, po.display_name, po.status, po.security_revision,
       a.display_name AS account_display_name, a.status AS account_status,
       c.identifier_normalized AS email,
       COALESCE(GROUP_CONCAT(DISTINCT pr.`key` ORDER BY pr.`key` SEPARATOR ','), '') AS role_keys,
       po.created_at, po.updated_at
FROM pa_platform_operator po
JOIN pa_account a ON a.id = po.account_id
LEFT JOIN pa_credential c ON c.account_id = a.id AND c.identifier_type = 'email' AND c.status = 'active'
LEFT JOIN pa_platform_operator_role por ON por.platform_operator_id = po.id
LEFT JOIN pa_platform_role pr ON pr.id = por.platform_role_id
GROUP BY po.id, po.account_id, po.display_name, po.status, po.security_revision,
         a.display_name, a.status, c.identifier_normalized, po.created_at, po.updated_at
ORDER BY po.id DESC
SQL, [], $page, static function (array $row): array {
            $row['role_keys'] = $row['role_keys'] === '' ? [] : explode(',', (string)$row['role_keys']);
            return $row;
        });
    }

    /** @return array{items:list<array<string,mixed>>,total:int} */
    public function roles(PlatformOperatorContext $context, PageRequest $page): array
    {
        $this->sessions->assertAllowed($context, 'platform.role.read');
        return $this->page(<<<'SQL'
SELECT pr.id, pr.`key`, pr.name, pr.description, pr.is_builtin, pr.status, pr.revision,
       COUNT(DISTINCT prp.permission_id) AS permission_count,
       COALESCE(GROUP_CONCAT(DISTINCT p.`key` ORDER BY p.`key` SEPARATOR ','), '') AS permission_keys,
       pr.created_at, pr.updated_at
FROM pa_platform_role pr
LEFT JOIN pa_platform_role_permission prp ON prp.platform_role_id = pr.id
LEFT JOIN pa_permission p ON p.id = prp.permission_id AND p.status = 'active'
GROUP BY pr.id, pr.`key`, pr.name, pr.description, pr.is_builtin, pr.status, pr.revision,
         pr.created_at, pr.updated_at
ORDER BY pr.id DESC
SQL, [], $page, static function (array $row): array {
            $row['permission_keys'] = $row['permission_keys'] === ''
                ? []
                : explode(',', (string)$row['permission_keys']);
            return $row;
        });
    }

    /** @return array{items:list<array<string,mixed>>,total:int} */
    public function permissions(PlatformOperatorContext $context, PageRequest $page): array
    {
        $this->sessions->assertAllowed($context, 'platform.permission.read');
        return $this->page(<<<'SQL'
SELECT id, `key`, module_key, `type`, name, description, risk_level, status,
       manifest_version, created_at, updated_at, retired_at
FROM pa_permission
WHERE module_key = 'platform'
ORDER BY id ASC
SQL, [], $page);
    }

    /** @return array{items:list<array<string,mixed>>,total:int} */
    public function audit(PlatformOperatorContext $context, PageRequest $page): array
    {
        $this->sessions->assertAllowed($context, 'platform.audit.read');
        $result = $this->page(<<<'SQL'
SELECT id, event_type, action, outcome, reason_code, operator_id, account_id,
       target_type, target_id, request_id, operation_id, ip_address,
       user_agent_hash, before_json, after_json, metadata_json, occurred_at
FROM pa_platform_audit_event
ORDER BY id DESC
SQL, [], $page);
        foreach ($result['items'] as &$item) {
            foreach (['before_json', 'after_json', 'metadata_json'] as $column) {
                $item[$column] = $this->decodeJson($item[$column] ?? null);
            }
        }
        unset($item);
        return $result;
    }

    /** @return array{items:list<array<string,mixed>>,total:int} */
    public function moduleStates(
        PlatformOperatorContext $context,
        int $tenantId,
        PageRequest $page
    ): array {
        $this->sessions->assertAllowed($context, 'platform.tenant.read');
        return $this->page(<<<'SQL'
SELECT tm.id, :selected_tenant_id AS tenant_id, mi.module_key,
       COALESCE(tm.status, 'not_enabled') AS status,
       COALESCE(tm.source, 'not_configured') AS source,
       COALESCE(tm.config_revision, 0) AS config_revision,
       tm.effective_at, tm.expires_at, tm.enabled_at, tm.disabled_at,
       tm.disabled_reason, tm.created_at, tm.updated_at,
       mi.installed_version, mi.status AS installation_status
FROM pa_module_installation mi
LEFT JOIN pa_tenant_module tm
  ON tm.tenant_id = :joined_tenant_id AND tm.module_key = mi.module_key
WHERE mi.status = 'active'
ORDER BY mi.module_key ASC
SQL, [
            'selected_tenant_id' => $tenantId,
            'joined_tenant_id' => $tenantId,
        ], $page);
    }

    /** @return array<string,mixed> */
    public function owner(PlatformOperatorContext $context, int $tenantId): array
    {
        $this->sessions->assertAllowed($context, 'platform.tenant.read');
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT tm.id AS member_id, tm.tenant_id, tm.account_id, tm.display_name,
       tm.status AS member_status, tm.security_revision, tm.authorization_revision,
       tm.joined_at, a.display_name AS account_display_name, a.status AS account_status,
       c.identifier_normalized AS email, r.id AS role_id, r.`key` AS role_key,
       tm.created_at, tm.updated_at
FROM pa_tenant_member tm
JOIN pa_account a ON a.id = tm.account_id
JOIN pa_member_role mr ON mr.tenant_id = tm.tenant_id AND mr.tenant_member_id = tm.id
JOIN pa_role r ON r.tenant_id = mr.tenant_id AND r.id = mr.role_id
LEFT JOIN pa_credential c ON c.account_id = a.id AND c.identifier_type = 'email' AND c.status = 'active'
WHERE tm.tenant_id = :tenant_id AND r.`key` = 'core.tenant-owner'
ORDER BY tm.id ASC
LIMIT 1
SQL);
        $statement->execute(['tenant_id' => $tenantId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw AdminAccessException::notFound();
        }
        return $row;
    }

    /** @param array<string,mixed> $parameters @return array{items:list<array<string,mixed>>,total:int} */
    private function page(
        string $sql,
        array $parameters,
        PageRequest $page,
        ?callable $map = null
    ): array {
        $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') AS platform_query';
        $count = $this->pdo->prepare($countSql);
        foreach ($parameters as $name => $value) {
            $count->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $count->execute();
        $total = (int)$count->fetchColumn();

        $statement = $this->pdo->prepare($sql . ' LIMIT :limit OFFSET :offset');
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $page->pageSize, PDO::PARAM_INT);
        $statement->bindValue(':offset', $page->offset(), PDO::PARAM_INT);
        $statement->execute();
        $items = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($map !== null) {
            $items = array_map($map, $items);
        }
        return ['items' => $items, 'total' => $total];
    }

    /** @return array<string,mixed>|null */
    private function decodeJson(mixed $value): ?array
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }
}
