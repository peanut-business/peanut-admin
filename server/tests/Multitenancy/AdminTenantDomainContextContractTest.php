<?php
declare(strict_types=1);

function expectAdminTenantDomainContext(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$serverRoot = dirname(__DIR__, 2);
foreach ([
    'app/common/service/member/MemberTenantContext.php',
    'app/common/service/article/ArticleTenantContext.php',
    'app/common/service/file/FileTenantContext.php',
    'app/common/service/finance/FinanceTenantContext.php',
] as $path) {
    $source = (string)file_get_contents($serverRoot . '/' . $path);
    expectAdminTenantDomainContext(
        str_contains($source, 'AuthenticatedMemberContext|TenantContext'),
        'domain context does not accept both customer and native Admin identities: ' . $path
    );
    expectAdminTenantDomainContext(
        str_contains($source, '$request->tenantContext ?? null'),
        'domain context does not read the native Admin TenantContext: ' . $path
    );
    expectAdminTenantDomainContext(
        str_contains($source, 'sessionKey')
            && str_contains($source, 'authorizationRevision')
            && str_contains($source, 'requestId'),
        'domain context accepts an untrusted Admin TenantContext: ' . $path
    );
}

echo "ADMIN-TENANT-DOMAIN-CONTEXT-001 passed\n";
