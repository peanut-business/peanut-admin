<?php
declare(strict_types=1);

namespace app\common\service\storage;

use app\common\application\BusinessException;
use app\common\service\audit\AuditContractHost;
use app\platform\context\PlatformOperatorContext;
use PDO;
use PeanutAdmin\Kernel\Audit\AuditOutcome;
use Throwable;

final readonly class StorageConfigurationService
{
    private const MUTATION_PERMISSION = 'platform.ops.maintenance.manage';

    public function __construct(private StorageRepository $repo) {}

    public function snapshot(): array
    {
        return [
            'accounts' => $this->repo->accounts(),
            'spaces' => $this->repo->spaces(),
            'routes' => $this->repo->routes(),
            'purposes' => ['material.image', 'material.video', 'material.file', 'export.xlsx', 'export.csv'],
        ];
    }

    public function createAccount(PlatformOperatorContext $context, array $value): int
    {
        return $this->mutate($context, 'storage.account.created', 'STORAGE_ACCOUNT_CREATE', [
            'credential_rotation_requested' => $this->credentialRotationRequested($value),
        ], function () use ($value): int {
            $this->assertKeys($value, ['account_key', 'driver', 'name', 'credentials', 'credential_ref']);
            $account = $this->account($value, true);
            $statement = $this->repo->pdo()->prepare("INSERT INTO pa_storage_account(account_key,driver,name,credential_ciphertext,credential_key_version,credential_rotated_at,status,created_at,updated_at) VALUES(:account_key,:driver,:name,:ciphertext,:key_version,:rotated_at,'active',UTC_TIMESTAMP(3),UTC_TIMESTAMP(3))");
            $statement->execute([
                'account_key' => $account['account_key'], 'driver' => $account['driver'],
                'name' => $account['name'], 'ciphertext' => $account['credential']['ciphertext'],
                'key_version' => $account['credential']['key_version'],
                'rotated_at' => $account['credential']['rotated_at'],
            ]);
            return (int)$this->repo->pdo()->lastInsertId();
        });
    }

    public function updateAccount(PlatformOperatorContext $context, array $value): void
    {
        $this->mutate($context, 'storage.account.updated', 'STORAGE_ACCOUNT_UPDATE', [
            'credential_rotation_requested' => $this->credentialRotationRequested($value),
        ], function () use ($value): void {
            $this->assertKeys($value, ['id', 'name', 'status', 'credentials', 'credential_ref']);
            $id = $this->id($value['id'] ?? 0);
            $statement = $this->repo->pdo()->prepare('SELECT account_key,driver FROM pa_storage_account WHERE id=:id');
            $statement->execute(['id' => $id]);
            $existing = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($existing)) throw new \InvalidArgumentException('存储账号不存在');
            $account = $this->account([
                'account_key' => $existing['account_key'], 'driver' => $existing['driver'],
                'name' => $value['name'] ?? '', 'credentials' => $value['credentials'] ?? null,
                'credential_ref' => $value['credential_ref'] ?? null,
            ], false);
            $sql = 'UPDATE pa_storage_account SET name=:name,status=:status,updated_at=UTC_TIMESTAMP(3) WHERE id=:id';
            $params = [
                'id' => $id, 'name' => $account['name'],
                'status' => $this->status((string)($value['status'] ?? 'active'), ['active', 'disabled']),
            ];
            if ($account['credential'] !== null) {
                $sql = 'UPDATE pa_storage_account SET name=:name,credential_ciphertext=:ciphertext,credential_key_version=:key_version,credential_rotated_at=:rotated_at,status=:status,updated_at=UTC_TIMESTAMP(3) WHERE id=:id';
                $params += [
                    'ciphertext' => $account['credential']['ciphertext'],
                    'key_version' => $account['credential']['key_version'],
                    'rotated_at' => $account['credential']['rotated_at'],
                ];
            }
            $statement = $this->repo->pdo()->prepare($sql);
            $statement->execute($params);
        });
    }

    public function createSpace(PlatformOperatorContext $context, array $value): int
    {
        return $this->mutate($context, 'storage.space.created', 'STORAGE_SPACE_CREATE', [], function () use ($value): int {
            $this->assertKeys($value, [
                'account_id', 'space_key', 'name', 'access_type', 'bucket',
                'region', 'endpoint', 'access_domain', 'local_path',
            ]);
            $space = $this->space($value + ['status' => 'active']);
            $statement = $this->repo->pdo()->prepare("INSERT INTO pa_storage_space(space_key,account_id,name,access_type,bucket,region,endpoint,access_domain,local_path,status,created_at,updated_at) VALUES(:space_key,:account_id,:name,:access_type,:bucket,:region,:endpoint,:access_domain,:local_path,'active',UTC_TIMESTAMP(3),UTC_TIMESTAMP(3))");
            $statement->execute([
                'space_key' => $space['space_key'], 'account_id' => $space['account_id'],
                'name' => $space['name'], 'access_type' => $space['access_type'],
                'bucket' => $space['bucket'], 'region' => $space['region'],
                'endpoint' => $space['endpoint'], 'access_domain' => $space['access_domain'],
                'local_path' => $space['local_path'],
            ]);
            return (int)$this->repo->pdo()->lastInsertId();
        });
    }

    public function updateSpace(PlatformOperatorContext $context, array $value): void
    {
        $this->mutate($context, 'storage.space.updated', 'STORAGE_SPACE_UPDATE', [], function () use ($value): void {
            $this->assertKeys($value, ['id', 'name', 'access_domain', 'status']);
            $id = $this->id($value['id'] ?? 0);
            $statement = $this->repo->pdo()->prepare('SELECT account_id,space_key,access_type,bucket,region,endpoint,local_path FROM pa_storage_space WHERE id=:id');
            $statement->execute(['id' => $id]);
            $existing = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($existing)) throw new \InvalidArgumentException('Space 不存在');
            $space = $this->space([
                ...$existing,
                'name' => $value['name'] ?? '',
                'access_domain' => $value['access_domain'] ?? '',
                'status' => $value['status'] ?? 'active',
            ]);
            $statement = $this->repo->pdo()->prepare('UPDATE pa_storage_space SET name=:name,access_domain=:domain,status=:status,updated_at=UTC_TIMESTAMP(3) WHERE id=:id');
            $statement->execute([
                'id' => $id, 'name' => $space['name'],
                'domain' => $space['access_domain'], 'status' => $space['status'],
            ]);
        });
    }

    public function setRoute(PlatformOperatorContext $context, array $value): void
    {
        $this->mutate($context, 'storage.route.updated', 'STORAGE_ROUTE', [], function () use ($value): void {
            $this->assertKeys($value, ['route_key', 'access_type', 'space_id']);
            $key = $this->key((string)($value['route_key'] ?? ''), 96);
            $access = StorageAccess::assertType((string)($value['access_type'] ?? ''));
            if (str_starts_with($key, 'default.')) {
                if ($key !== 'default.' . $access) throw new \InvalidArgumentException('默认路由属性不匹配');
            } elseif (StoragePurpose::accessType($key) !== $access) {
                throw new \InvalidArgumentException('用途路由属性不匹配');
            }
            $space = $this->id($value['space_id'] ?? 0);
            $statement = $this->repo->pdo()->prepare("SELECT 1 FROM pa_storage_space WHERE id=:id AND access_type=:access AND status='active'");
            $statement->execute(['id' => $space, 'access' => $access]);
            if (!$statement->fetchColumn()) throw new \InvalidArgumentException('路由目标 Space 不可用');
            $statement = $this->repo->pdo()->prepare('INSERT INTO pa_storage_route(route_key,access_type,space_id,updated_at) VALUES(:route_key,:access,:space,UTC_TIMESTAMP(3)) ON DUPLICATE KEY UPDATE access_type=VALUES(access_type),space_id=VALUES(space_id),updated_at=VALUES(updated_at)');
            $statement->execute(['route_key' => $key, 'access' => $access, 'space' => $space]);
        });
    }

    private function account(array $value, bool $credentialRequired): array
    {
        $this->assertNoReference($value);
        $driver = $this->driver((string)($value['driver'] ?? ''));
        return [
            'account_key' => $this->key((string)($value['account_key'] ?? ''), 64),
            'driver' => $driver,
            'name' => $this->name((string)($value['name'] ?? '')),
            'credential' => $this->credential($driver, $value, $credentialRequired),
        ];
    }

    private function space(array $value): array
    {
        $accountId = $this->id($value['account_id'] ?? 0);
        $statement = $this->repo->pdo()->prepare('SELECT driver FROM pa_storage_account WHERE id=:id');
        $statement->execute(['id' => $accountId]);
        $driver = $statement->fetchColumn();
        if (!is_string($driver)) throw new \InvalidArgumentException('存储账号不存在');
        $driver = $this->driver($driver);
        $access = StorageAccess::assertType((string)($value['access_type'] ?? ''));
        $bucket = $this->nullable((string)($value['bucket'] ?? ''));
        $region = $this->nullable((string)($value['region'] ?? ''));
        $endpoint = $this->url((string)($value['endpoint'] ?? ''));
        $domain = $this->url((string)($value['access_domain'] ?? ''));
        $local = $this->nullable((string)($value['local_path'] ?? ''));
        if ($driver === 'local') {
            if ($local !== ($access === StorageAccess::PUBLIC ? 'public/storage' : 'private/storage') || $bucket !== null) {
                throw new \InvalidArgumentException('本地目录与公开属性不匹配');
            }
        } elseif ($bucket === null || $local !== null || ($driver === 'qcloud' && $region === null)
            || ($driver === 'aliyun' && $endpoint === null)
            || (($driver === 'qiniu' || $access === StorageAccess::PUBLIC) && $domain === null)) {
            throw new \InvalidArgumentException('云 Space 位置配置不完整');
        }
        return [
            'account_id' => $accountId, 'space_key' => $this->key((string)($value['space_key'] ?? ''), 64),
            'name' => $this->name((string)($value['name'] ?? '')), 'access_type' => $access,
            'bucket' => $bucket, 'region' => $region, 'endpoint' => $endpoint,
            'access_domain' => $domain, 'local_path' => $local,
            'status' => $this->status((string)($value['status'] ?? 'active'), ['active', 'read_only', 'disabled']),
        ];
    }

    private function mutate(
        PlatformOperatorContext $context,
        string $eventType,
        string $reasonPrefix,
        array $metadata,
        callable $operation,
    ): mixed {
        try {
            $result = $operation();
        } catch (\InvalidArgumentException $exception) {
            $failure = new BusinessException($reasonPrefix . '_INPUT_INVALID', 422, $exception->getMessage());
            $this->audit($context, $eventType, $metadata, AuditOutcome::Error, $failure->errorCode);
            throw $failure;
        } catch (Throwable $exception) {
            $this->audit($context, $eventType, $metadata, AuditOutcome::Error, $reasonPrefix . '_FAILED');
            throw $exception;
        }
        $this->audit($context, $eventType, $metadata, AuditOutcome::Success, null);
        return $result;
    }

    private function audit(
        PlatformOperatorContext $context,
        string $eventType,
        array $metadata,
        AuditOutcome $outcome,
        ?string $reasonCode,
    ): void {
        AuditContractHost::fromPdo($this->repo->pdo())->recordPlatform(
            $eventType,
            self::MUTATION_PERMISSION,
            $context->core->requestId,
            $context->core->operatorId,
            $context->core->accountId,
            $metadata,
            $outcome,
            $reasonCode,
        );
    }

    private function credential(string $driver, array $value, bool $required): ?array
    {
        if ($driver === 'local') {
            if ((array)($value['credentials'] ?? []) !== []) throw new \InvalidArgumentException('本地存储不允许配置凭据');
            return $required ? ['ciphertext' => null, 'key_version' => null, 'rotated_at' => null] : null;
        }
        $raw = $value['credentials'] ?? null;
        if ($raw === null || $raw === []) {
            if ($required) throw new \InvalidArgumentException('存储凭据不完整');
            return null;
        }
        if (!is_array($raw) || array_diff(array_keys($raw), ['access_key', 'secret_key']) !== []) {
            throw new \InvalidArgumentException('存储凭据格式无效');
        }
        $encrypted = StorageCredentialCipher::encrypt($raw);
        return [
            'ciphertext' => $encrypted['ciphertext'],
            'key_version' => $encrypted['key_version'],
            'rotated_at' => gmdate('Y-m-d H:i:s'),
        ];
    }

    private function credentialRotationRequested(array $value): bool
    {
        return isset($value['credentials']) && $value['credentials'] !== [];
    }

    private function assertKeys(array $value, array $allowed): void
    {
        if (array_diff(array_keys($value), $allowed) !== []) throw new \InvalidArgumentException('请求字段无效');
    }

    private function assertNoReference(array $value): void
    {
        if (trim((string)($value['credential_ref'] ?? '')) !== '') throw new \InvalidArgumentException('存储凭据引用已不再支持');
    }

    private function driver(string $value): string
    {
        if (!in_array($value, ['local', 'qiniu', 'aliyun', 'qcloud'], true)) throw new \InvalidArgumentException('存储驱动无效');
        return $value;
    }

    private function id(mixed $value): int
    {
        $id = (int)$value;
        if ($id < 1) throw new \InvalidArgumentException('ID 无效');
        return $id;
    }

    private function key(string $value, int $max): string
    {
        $value = trim($value);
        if (strlen($value) > $max || preg_match('/^[a-z][a-z0-9._-]{2,95}$/D', $value) !== 1) {
            throw new \InvalidArgumentException('标识格式无效');
        }
        return $value;
    }

    private function name(string $value): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 128) throw new \InvalidArgumentException('名称无效');
        return $value;
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function url(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        if (filter_var($value, FILTER_VALIDATE_URL) === false
            || !in_array(strtolower((string)parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('访问地址无效');
        }
        return rtrim($value, '/');
    }

    private function status(string $value, array $allowed): string
    {
        if (!in_array($value, $allowed, true)) throw new \InvalidArgumentException('状态无效');
        return $value;
    }
}
