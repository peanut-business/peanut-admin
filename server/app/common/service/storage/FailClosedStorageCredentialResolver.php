<?php
declare(strict_types=1);

namespace app\common\service\storage;

final class FailClosedStorageCredentialResolver implements StorageCredentialResolver
{
    public function resolve(array $account): array
    {
        if ((string)($account['driver'] ?? '') === 'local') {
            return [];
        }
        $ciphertext = (string)($account['credential_ciphertext'] ?? ''); $keyVersion = (string)($account['credential_key_version'] ?? '');
        if ($ciphertext === '' || $keyVersion === '') throw new \RuntimeException('存储凭据未配置');
        return StorageCredentialCipher::decrypt($ciphertext, $keyVersion);
    }
}
