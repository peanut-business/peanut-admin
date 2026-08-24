<?php
declare(strict_types=1);

namespace app\common\service\idempotency;

use LogicException;
use PDO;
use app\common\contract\idempotency\IdempotentCommandExecutor;
use app\common\contract\idempotency\IdempotencyCommand;
use app\common\contract\idempotency\IdempotencyReceipt;
use app\common\contract\idempotency\IdempotencyResult;
use PeanutAdmin\Kernel\Idempotency\PdoIdempotencyRepository;

final class PdoIdempotentCommandExecutor implements IdempotentCommandExecutor
{
    private PdoIdempotencyRepository $repository;

    public function __construct(PDO $pdo)
    {
        $this->repository = new PdoIdempotencyRepository($pdo);
    }

    public function begin(IdempotencyCommand $command): IdempotencyResult
    {
        return IdempotencyResult::fromRecord($this->repository->beginTenant(
            $command->context->tenantId,
            $command->context->memberId,
            $command->operationKey,
            $command->key,
            $command->requestHash,
            $command->expiresAt,
        ));
    }

    public function complete(IdempotencyResult $execution, IdempotencyReceipt $receipt): void
    {
        $this->assertExecutionOwner($execution);
        $this->repository->completeTenant(
            $execution->id(),
            $receipt->status,
            $receipt->body,
            $receipt->resourceType,
            $receipt->resourceId,
        );
    }

    public function fail(IdempotencyResult $execution, IdempotencyReceipt $receipt): void
    {
        $this->assertExecutionOwner($execution);
        $this->repository->failTenant(
            $execution->id(),
            $receipt->status,
            $receipt->body,
            $receipt->resourceType,
            $receipt->resourceId,
        );
    }

    private function assertExecutionOwner(IdempotencyResult $execution): void
    {
        if (!$execution->isExecutionOwner()) {
            throw new LogicException('Only the idempotency execution owner may finalize a command.');
        }
    }
}
