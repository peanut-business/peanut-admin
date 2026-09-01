<?php
declare(strict_types=1);

namespace app\common\service\payment;

use app\common\service\finance\FinanceTenantContext;
use PDO;
use PeanutAdmin\Kernel\Tenancy\TenantLockNamespace;
use PeanutAdmin\Kernel\Tenancy\TenantScope;

/** Payment-owned adapter for the tenant-scoped retry lock. */
final readonly class PaymentRetryLock
{
    public function __construct(private PDO $pdo)
    {
    }

    public function name(object $context, int $recordId): string
    {
        if ($recordId < 1) {
            throw new \InvalidArgumentException('退款记录 ID 无效');
        }

        $scope = TenantScope::fromTrustedContext(
            FinanceTenantContext::tenantId($context),
            (string)($context->requestId ?? ''),
        );

        return (new TenantLockNamespace($scope))->name('recharge:refund-retry:' . $recordId);
    }

    /** MySQL 会话级互斥覆盖完整渠道调用周期，避免快速失败时排队请求再次获准。 */
    public function acquire(string $lockName): bool
    {
        $statement = $this->pdo->prepare('SELECT GET_LOCK(:lock_name, 0)');
        $statement->execute(['lock_name' => $lockName]);
        return (int)$statement->fetchColumn() === 1;
    }

    public function release(string $lockName): void
    {
        try {
            $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $statement->execute(['lock_name' => $lockName]);
        } catch (\Throwable) {
            // MySQL 连接关闭时命名锁会自动释放，不覆盖本次退款结果。
        }
    }
}
