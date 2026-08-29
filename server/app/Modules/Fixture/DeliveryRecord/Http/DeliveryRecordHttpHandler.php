<?php
declare(strict_types=1);

namespace app\Modules\Fixture\DeliveryRecord\Http;

use app\Modules\Fixture\DeliveryRecord\Contracts\DeliveryRecordCommands;
use app\common\execution\CurrentExecutionContext;
use PeanutAdmin\Kernel\Module\ModuleException;

/** Tenant-member HTTP boundary; root and system actors never bypass Module commands. */
final readonly class DeliveryRecordHttpHandler
{
    public function __construct(
        private DeliveryRecordCommands $commands,
        private CurrentExecutionContext $executionContext,
    ) {}

    /** @return list<array<string,mixed>> */
    public function lists(): array
    {
        $this->assertMemberContext();
        return $this->commands->list();
    }

    /** @return array<string,mixed> */
    public function record(string $reference): array
    {
        $this->assertMemberContext();
        return $this->commands->record($reference);
    }

    private function assertMemberContext(): void
    {
        $context = $this->executionContext->tenantAdmin();
        $principal = $this->executionContext->actor();
        if ((int)($principal['tenant_id'] ?? 0) !== $context->tenantId
            || (int)($principal['account_id'] ?? 0) !== $context->accountId
            || (int)($principal['id'] ?? 0) !== $context->memberId) {
            throw new ModuleException(
                'AUTHORIZATION_PERMISSION_DENIED',
                'The Tenant member principal does not match the request context.'
            );
        }
    }
}
