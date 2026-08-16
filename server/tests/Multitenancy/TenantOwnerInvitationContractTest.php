<?php
declare(strict_types=1);

use app\platform\invitation\OneTimeInvitationToken;
use app\platform\invitation\OwnerInvitationDelivery;
use app\platform\invitation\TenantOwnerInvitationException;
use app\platform\invitation\UnavailableOwnerInvitationDeliveryPort;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function ownerInvitationExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$token = OneTimeInvitationToken::issue();
ownerInvitationExpect(strlen($token->expose()) === 43, 'invitation token entropy surface changed');
ownerInvitationExpect(strlen($token->hash()) === 64, 'invitation token hash surface changed');
ownerInvitationExpect(
    hash_equals($token->hash(), OneTimeInvitationToken::fromPlaintext($token->expose())->hash()),
    'invitation token lookup hash is not deterministic'
);
ownerInvitationExpect(
    ($token->__debugInfo()['token_hash'] ?? null) === $token->hash()
        && !in_array($token->expose(), $token->__debugInfo(), true),
    'invitation token debug output exposes plaintext'
);
try {
    OneTimeInvitationToken::fromPlaintext('predictable-token');
    throw new RuntimeException('weak invitation token unexpectedly passed');
} catch (TenantOwnerInvitationException $exception) {
    ownerInvitationExpect($exception->errorCode === 'INVITATION_TOKEN_INVALID', 'weak token denial changed');
}

$delivery = new OwnerInvitationDelivery(
    1,
    1,
    'Example Tenant',
    'owner@example.test',
    'Example Owner',
    new DateTimeImmutable('+1 day'),
    $token
);
$deliveryResult = (new UnavailableOwnerInvitationDeliveryPort())->deliver($delivery);
ownerInvitationExpect($deliveryResult->status === 'pending_delivery', 'missing provider fabricated delivery');
ownerInvitationExpect($deliveryResult->provider === null, 'missing provider fabricated provider identity');
ownerInvitationExpect(
    $delivery->idempotencyKey() === 'tenant-owner-invitation:1:1',
    'delivery port lacks a generation-scoped idempotency key'
);
ownerInvitationExpect(!in_array($token->expose(), $delivery->__debugInfo(), true), 'delivery debug output exposes token');

$serverRoot = dirname(__DIR__, 2);
$migration = (string)file_get_contents(
    $serverRoot . '/database/migrations/20260816-tenant-owner-invitation.sql'
);
foreach (['pending', 'accepted', 'revoked', 'expired'] as $status) {
    ownerInvitationExpect(str_contains($migration, "'{$status}'"), "invitation status missing: {$status}");
}
ownerInvitationExpect(str_contains($migration, '`token_hash` CHAR(64)'), 'migration does not persist a token hash');
ownerInvitationExpect(!str_contains($migration, '`token` VARCHAR'), 'migration persists a plaintext token column');
ownerInvitationExpect(
    str_contains($migration, 'GENERATED ALWAYS AS')
        && str_contains($migration, 'uk_owner_invitation_pending_tenant'),
    'migration lacks the one-pending-invitation concurrency guard'
);
ownerInvitationExpect(
    str_contains($migration, 'pending_delivery') && str_contains($migration, 'delivery_error_code'),
    'migration lacks honest delivery state'
);

$adminService = (string)file_get_contents(
    $serverRoot . '/app/platform/invitation/TenantOwnerInvitationAdminService.php'
);
$publicService = (string)file_get_contents(
    $serverRoot . '/app/platform/invitation/TenantOwnerInvitationPublicService.php'
);
ownerInvitationExpect(str_contains($adminService, "TenantStatus::Provisioning"), 'Tenant is not left provisioning');
ownerInvitationExpect(str_contains($adminService, "'token_hash' => \$token->hash()"), 'plaintext token may reach persistence');
ownerInvitationExpect(!str_contains($adminService, 'Log::'), 'invitation service logs token-bearing state');
ownerInvitationExpect(str_contains($publicService, 'FOR UPDATE'), 'acceptance lacks a row lock');
ownerInvitationExpect(
    str_contains($publicService, 'consumed_token_hash'),
    'accepted invitation does not invalidate its one-time token'
);
ownerInvitationExpect(
    str_contains($publicService, 'pendingOrActiveMemberWithRoleExists'),
    'acceptance lacks a first-owner concurrency check'
);
ownerInvitationExpect(
    str_contains($publicService, 'EXISTING_ACCOUNT_PASSWORD_FORBIDDEN'),
    'acceptance can overwrite an existing account password'
);
ownerInvitationExpect(
    str_contains($publicService, 'TenantMemberStatus::Active')
        && str_contains($publicService, "'core.tenant-owner'"),
    'acceptance does not activate and authorize the owner membership'
);
ownerInvitationExpect(
    str_contains($publicService, 'appendTenantSystem'),
    'acceptance does not append a Core audit event'
);

echo "TENANT-OWNER-INVITATION-CONTRACT-001 passed\n";
