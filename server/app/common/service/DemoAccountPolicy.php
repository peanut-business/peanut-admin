<?php
declare(strict_types=1);

namespace app\common\service;

use PDO;

/** Keeps public demo credentials and password locking out of normal deployments. */
final class DemoAccountPolicy
{
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
        foreach (['PEANUT_DEMO_TENANT_A_EMAIL', 'PEANUT_DEMO_TENANT_B_EMAIL'] as $key) {
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

    private function __construct()
    {
    }
}
