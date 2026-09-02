<?php
declare(strict_types=1);

namespace app\common\service;

use PDO;

/** Keeps public demo credentials and password locking out of normal deployments. */
final class DemoAccountPolicy
{
    /** @param list<string> $demoEmails */
    public function __construct(
        private readonly PDO $pdo,
        private readonly bool $enabled,
        private readonly array $demoEmails,
    ) {}

    /**
     * Disposable demo credential only. Normal account passwords continue to
     * use Core's stronger minimum-length policy.
     */
    private const FIXED_PASSWORD = 'peanut1234';

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function isDemoEmail(string $email): bool
    {
        if (!$this->enabled) {
            return false;
        }
        $email = strtolower(trim($email));
        foreach ($this->demoEmails as $candidate) {
            $candidate = strtolower(trim($candidate));
            if ($candidate !== '' && hash_equals($candidate, $email)) {
                return true;
            }
        }
        return false;
    }

    public function assertPasswordChangeAllowed(int $accountId): void
    {
        if (!$this->enabled) {
            return;
        }
        $statement = $this->pdo->prepare(<<<'SQL'
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
            if ($this->isDemoEmail((string)$email)) {
                throw new \DomainException('演示账号密码已锁定，不能在页面中修改');
            }
        }
    }

    public function mutationLocked(array $adminInfo, string $path): bool
    {
        if (!$this->isDemoEmail((string)($adminInfo['username'] ?? ''))) {
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

    public function platformMutationLocked(int $accountId): bool
    {
        if (!$this->enabled || $accountId < 1) {
            return false;
        }
        $statement = $this->pdo->prepare(<<<'SQL'
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
            if ($this->isDemoEmail((string)$email)) {
                return true;
            }
        }
        return false;
    }

    public function bootstrapPassword(): string
    {
        if (!$this->enabled) {
            throw new \LogicException('演示密码策略未启用');
        }
        return bin2hex(random_bytes(24)) . 'A1';
    }

    public function replaceCredentialHashes(array $emails): void
    {
        if (!$this->enabled) {
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
        $statement = $this->pdo->prepare(<<<'SQL'
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
}
