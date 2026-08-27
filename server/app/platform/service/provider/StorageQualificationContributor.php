<?php
declare(strict_types=1);

namespace app\platform\service\provider;

use PDO;

final readonly class StorageQualificationContributor implements ProviderQualificationContributor
{
    public function __construct(private PDO $pdo, private string $digestKey)
    {
        if (strlen($digestKey) < 32) {
            throw new \InvalidArgumentException('PROVIDER_QUALIFICATION_DIGEST_KEY_INVALID');
        }
    }

    public function subjects(): array
    {
        $rows = $this->pdo->query(<<<'SQL'
SELECT a.id,a.account_key,a.driver,a.credential_ciphertext,a.credential_key_version,
       a.credential_rotated_at,a.status,a.updated_at,COUNT(s.id) AS active_space_count,
       MAX(s.updated_at) AS space_updated_at
FROM pa_storage_account a
LEFT JOIN pa_storage_space s ON s.account_id=a.id AND s.status='active'
GROUP BY a.id,a.account_key,a.driver,a.credential_ciphertext,a.credential_key_version,
         a.credential_rotated_at,a.status,a.updated_at
ORDER BY a.id
SQL)->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function (array $row): ProviderQualificationSubject {
            $driver = (string)$row['driver'];
            $configured = (string)$row['status'] === 'active'
                && (int)$row['active_space_count'] > 0
                && ($driver === 'local' || (
                    trim((string)$row['credential_ciphertext']) !== ''
                    && trim((string)$row['credential_key_version']) !== ''
                    && trim((string)$row['credential_rotated_at']) !== ''
                ));
            $payload = implode("\0", array_map(static fn(mixed $value): string => (string)$value, [
                $row['id'], $row['account_key'], $driver, $row['credential_ciphertext'],
                $row['credential_key_version'], $row['credential_rotated_at'], $row['status'],
                $row['updated_at'], $row['active_space_count'], $row['space_updated_at'],
            ]));
            return new ProviderQualificationSubject(
                'storage.' . $driver,
                'storage',
                'instance',
                null,
                (string)$row['account_key'],
                $configured,
                false,
                $driver === 'local' ? null : $this->iso((string)$row['credential_rotated_at']),
                hash_hmac('sha256', $payload, $this->digestKey),
            );
        }, $rows);
    }

    private function iso(string $value): ?string
    {
        $timestamp = strtotime($value . ' UTC');
        return $timestamp === false ? null : gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }
}
