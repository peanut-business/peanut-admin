<?php
declare(strict_types=1);

namespace app\platform\service\ops;

use app\common\service\audit\AuditContractHost;
use PDO;
use PeanutAdmin\Kernel\Context\PlatformContext;

/** Container-owned application boundary for all Platform Ops use cases. */
final readonly class PlatformOpsApplicationService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string,mixed> */
    public function status(PlatformContext $context): array
    {
        return PlatformOpsRuntimeFactory::status($this->pdo)->read($context)->toPublicArray();
    }

    /** @return array<string,mixed> */
    public function upgradeReadiness(PlatformContext $context): array
    {
        return PlatformOpsRuntimeFactory::runtimeStatusProvider($this->pdo)->upgradeReadiness($context);
    }

    /** @return array<string,mixed> */
    public function providers(PlatformContext $context): array
    {
        return PlatformOpsRuntimeFactory::providerQualifications($this->pdo)->snapshot($context);
    }

    /** @return array<string,mixed>|null */
    public function maintenance(PlatformContext $context): ?array
    {
        return PlatformOpsRuntimeFactory::maintenance($this->pdo)->current($context)?->toPublicArray();
    }

    /** @return array<string,mixed> */
    public function scheduleMaintenance(
        PlatformContext $context,
        string $reasonKey,
        string $startsAt,
        string $endsAt,
        int $revision,
        string $idempotencyKey,
    ): array {
        return PlatformOpsRuntimeFactory::maintenance($this->pdo)
            ->schedule($context, $reasonKey, $startsAt, $endsAt, $revision, $idempotencyKey)
            ->toPublicArray();
    }

    /** @return array<string,mixed> */
    public function closeMaintenance(
        PlatformContext $context,
        string $maintenanceKey,
        int $revision,
        string $idempotencyKey,
    ): array {
        return PlatformOpsRuntimeFactory::maintenance($this->pdo)
            ->close($context, $maintenanceKey, $revision, $idempotencyKey)
            ->toPublicArray();
    }

    /** @return array{json:string,sha256:string,filename:string,bytes:int} */
    public function diagnostics(PlatformContext $context, int $windowMinutes, string $requestId): array
    {
        $artifact = (new PlatformDiagnosticBundleService($this->pdo))->create($context, $windowMinutes);
        AuditContractHost::fromPdo($this->pdo)->appendPlatform(
            'platform.ops.diagnostics.downloaded',
            'platform.ops.logs.read',
            $requestId,
            $context->operatorId,
            $context->accountId,
            [
                'artifact_sha256' => $artifact['sha256'],
                'artifact_bytes' => $artifact['bytes'],
                'window_minutes' => $windowMinutes,
            ],
        );
        return $artifact;
    }

    /** @return array<string,mixed> */
    public function submitBackup(
        PlatformContext $context,
        string $providerKey,
        string $idempotencyKey,
    ): array {
        return PlatformOpsRuntimeFactory::tasks($this->pdo)
            ->submitBackup($context, $providerKey, $idempotencyKey)
            ->toPublicArray();
    }

    /** @return array<string,mixed> */
    public function submitRestore(
        PlatformContext $context,
        string $providerKey,
        string $backupReferenceKey,
        string $targetKey,
        string $idempotencyKey,
    ): array {
        return PlatformOpsRuntimeFactory::tasks($this->pdo)
            ->submitRestore($context, $providerKey, $backupReferenceKey, $targetKey, $idempotencyKey)
            ->toPublicArray();
    }

    /** @return array<string,mixed> */
    public function submitUpgrade(PlatformContext $context, string $idempotencyKey): array
    {
        return PlatformOpsRuntimeFactory::upgrades($this->pdo)->submit($context, $idempotencyKey);
    }

    /** @return array<string,mixed> */
    public function submitModuleOperation(
        PlatformContext $context,
        string $requestKey,
        string $idempotencyKey,
    ): array {
        return PlatformOpsRuntimeFactory::moduleOperations($this->pdo)
            ->submit($context, $requestKey, $idempotencyKey);
    }

    /** @return array<string,mixed> */
    public function moduleOperations(PlatformContext $context): array
    {
        return PlatformOpsRuntimeFactory::moduleOperations($this->pdo)->snapshot($context);
    }

    /** @return array<string,mixed> */
    public function upgrades(PlatformContext $context): array
    {
        return PlatformOpsRuntimeFactory::upgrades($this->pdo)->snapshot($context);
    }

    /** @return array<string,mixed> */
    public function backups(PlatformContext $context): array
    {
        return (new PlatformBackupCenterService($this->pdo))->snapshot($context);
    }

    /** @return array<string,mixed> */
    public function task(PlatformContext $context, string $taskKey): array
    {
        $module = PlatformOpsRuntimeFactory::moduleOperations($this->pdo)
            ->taskIfModuleOperation($context, $taskKey);
        $upgrade = PlatformOpsRuntimeFactory::upgrades($this->pdo)
            ->taskIfUpgrade($context, $taskKey);
        return $module ?? $upgrade ?? PlatformOpsRuntimeFactory::tasks($this->pdo)
            ->task($context, $taskKey)
            ->toPublicArray();
    }
}
