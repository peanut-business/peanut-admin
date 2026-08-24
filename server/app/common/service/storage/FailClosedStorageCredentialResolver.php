<?php
declare(strict_types=1);

namespace app\common\service\storage;

final class FailClosedStorageCredentialResolver implements StorageCredentialResolver
{
    public function resolve(string $driver, string $credentialRef, array $bindings = []): array
    {
        if ($driver === 'local') {
            return [];
        }
        $credentialRef = trim($credentialRef);
        if ($credentialRef === '') {
            throw new \RuntimeException('存储密钥引用未配置');
        }
        $configured = config('storage.secret_bindings', []);
        if (is_array($configured) && isset($configured[$credentialRef]) && is_array($configured[$credentialRef])) {
            return $this->normalize($configured[$credentialRef], $credentialRef);
        }
        $envKey = 'PEANUT_STORAGE_SECRET_' . strtoupper((string)preg_replace('/[^A-Za-z0-9]+/', '_', $credentialRef));
        $raw = getenv($envKey);
        if (!is_string($raw) || trim($raw) === '') {
            throw new \RuntimeException('存储密钥引用未解析: ' . $credentialRef);
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('存储密钥引用格式无效: ' . $credentialRef);
        }
        return $this->normalize($decoded, $credentialRef);
    }

    /** @return array{access_key:string,secret_key:string} */
    private function normalize(array $value, string $credentialRef): array
    {
        $accessKey = trim((string)($value['access_key'] ?? ''));
        $secretKey = trim((string)($value['secret_key'] ?? ''));
        if ($accessKey === '' || $secretKey === '') {
            throw new \RuntimeException('存储密钥引用内容不完整: ' . $credentialRef);
        }
        return ['access_key' => $accessKey, 'secret_key' => $secretKey];
    }
}
