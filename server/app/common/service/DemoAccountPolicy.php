<?php
declare(strict_types=1);

namespace app\common\service;

use PDO;

/** Keeps public demo credentials and password locking out of normal deployments. */
final class DemoAccountPolicy
{
    /**
     * Disposable demo credential only. Normal account passwords continue to
     * use Core's stronger minimum-length policy.
     */
    private const FIXED_PASSWORD = 'peanut1234';

    public static function enabled(): bool
    {
        return getenv('PEANUT_DEMO_MODE') === 'enabled';
    }

    public static function isDemoEmail(string $email): bool
    {
        if (!self::enabled()) {
            return false;
        }
        $email = strtolower(trim($email));
        foreach ([
            'ADMIN_INITIAL_EMAIL',
            'PLATFORM_INITIAL_EMAIL',
            'PEANUT_DEMO_TENANT_A_EMAIL',
            'PEANUT_DEMO_TENANT_B_EMAIL',
        ] as $key) {
            $candidate = strtolower(trim((string)(getenv($key) ?: '')));
            if ($candidate !== '' && hash_equals($candidate, $email)) {
                return true;
            }
        }
        return false;
    }

    public static function assertPasswordChangeAllowed(PDO $pdo, int $accountId): void
    {
        if (!self::enabled()) {
            return;
        }
        $statement = $pdo->prepare(<<<'SQL'
SELECT identifier_normalized
FROM pa_credential
WHERE account_id = :account_id
  AND kind = 'email_password'
  AND identifier_type = 'email'
  AND status = 'active'
ORDER BY id
LIMIT 2
SQL);
        $statement->execute(['account_id' => $accountId]);
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $email) {
            if (self::isDemoEmail((string)$email)) {
                throw new \DomainException('演示账号密码已锁定，不能在页面中修改');
            }
        }
    }

    public static function mutationLocked(array $adminInfo, string $path): bool
    {
        if (!self::isDemoEmail((string)($adminInfo['username'] ?? ''))) {
            return false;
        }
        $path = strtolower(trim($path, '/'));
        foreach ([
            'menu/', 'role/', 'admin/add', 'admin/edit', 'admin/editself', 'admin/delete', 'admin/status',
            'dept/', 'jobs/', 'config/', 'setting/', 'storage/setup', 'storage/change',
            'decoration/',
        ] as $prefix) {
            if (str_starts_with($path, $prefix)) return true;
        }
        return false;
    }

    public static function platformMutationLocked(PDO $pdo, int $accountId): bool
    {
        if (!self::enabled() || $accountId < 1) {
            return false;
        }
        $statement = $pdo->prepare(<<<'SQL'
SELECT c.identifier_normalized
FROM pa_credential c
WHERE c.account_id = :account_id
  AND c.kind = 'email_password'
  AND c.identifier_type = 'email'
  AND c.status = 'active'
ORDER BY c.id
LIMIT 2
SQL);
        $statement->execute(['account_id' => $accountId]);
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $email) {
            if (self::isDemoEmail((string)$email)) {
                return true;
            }
        }
        return false;
    }

    public static function bootstrapPassword(): string
    {
        if (!self::enabled()) {
            throw new \LogicException('演示密码策略未启用');
        }
        return bin2hex(random_bytes(24)) . 'A1';
    }

    public static function replaceCredentialHashes(PDO $pdo, array $emails): void
    {
        if (!self::enabled()) {
            throw new \LogicException('演示密码策略未启用');
        }
        $hash = password_hash(self::FIXED_PASSWORD, PASSWORD_ARGON2ID, [
            'memory_cost' => 65_536,
            'time_cost' => 4,
            'threads' => 2,
        ]);
        if (!is_string($hash) || $hash === '') {
            throw new \RuntimeException('演示密码摘要生成失败');
        }
        $statement = $pdo->prepare(<<<'SQL'
UPDATE pa_credential
SET secret_hash = :secret_hash,
    failed_attempts = 0,
    locked_until = NULL,
    secret_changed_at = UTC_TIMESTAMP(3),
    revision = revision + 1,
    updated_at = UTC_TIMESTAMP(3)
WHERE identifier_type = 'email'
  AND kind = 'email_password'
  AND status = 'active'
  AND identifier_normalized = :email
SQL);
        foreach (array_values(array_unique(array_map('strtolower', $emails))) as $email) {
            if (trim($email) !== '') {
                $statement->execute(['secret_hash' => $hash, 'email' => trim($email)]);
            }
        }
    }

    private function __construct()
    {
    }
}
