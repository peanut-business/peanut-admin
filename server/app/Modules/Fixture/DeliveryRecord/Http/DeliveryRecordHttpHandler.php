<?php
declare(strict_types=1);

namespace app\Modules\Fixture\DeliveryRecord\Http;

use app\Modules\Fixture\DeliveryRecord\Contracts\DeliveryRecordCommands;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Module\ModuleException;

/** Tenant-member HTTP boundary; root and system actors never bypass Module commands. */
final readonly class DeliveryRecordHttpHandler
{
    public function __construct(private DeliveryRecordCommands $commands)
    {
    }

    /** @param array<string,mixed> $principal @return list<array<string,mixed>> */
    public function lists(mixed $context, array $principal): array
    {
        return $this->commands->list($this->memberContext($context, $principal));
    }

    /** @param array<string,mixed> $principal @return array<string,mixed> */
    public function record(mixed $context, array $principal, string $reference): array
    {
        return $this->commands->record($this->memberContext($context, $principal), $reference);
    }

    /** @param array<string,mixed> $principal */
    private function memberContext(mixed $context, array $principal): TenantContext
    {
        if (!$context instanceof TenantContext) {
            throw new ModuleException(
                'CONTEXT_TENANT_REQUIRED',
                'A validated Tenant member context is required.'
            );
        }
        if ((int)($principal['tenant_id'] ?? 0) !== $context->tenantId
            || (int)($principal['account_id'] ?? 0) !== $context->accountId
            || (int)($principal['id'] ?? 0) !== $context->memberId) {
            throw new ModuleException(
                'AUTHORIZATION_PERMISSION_DENIED',
                'The Tenant member principal does not match the request context.'
            );
        }

        return $context;
    }
}
