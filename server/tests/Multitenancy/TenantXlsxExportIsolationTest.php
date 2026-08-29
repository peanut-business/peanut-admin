<?php
declare(strict_types=1);

use app\common\service\XlsxExportService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;

require dirname(__DIR__, 2) . '/bootstrap/environment.php';
require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectTenantXlsx(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function tenantXlsxContext(int $tenantId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId + 1000,
        '01JMT03XLSX' . str_pad((string)$memberId, 13, '0', STR_PAD_LEFT),
        $tenantId,
        $memberId + 1000,
        $memberId,
        'admin-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function tenantXlsxSheet(string $path): string
{
    $zip = new ZipArchive();
    expectTenantXlsx($zip->open($path) === true, 'Tenant export is not a readable XLSX');
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    expectTenantXlsx(is_string($sheet), 'Tenant export worksheet is missing');
    return $sheet;
}

$serverRoot = dirname(__DIR__, 2);
$app = new think\App();
$app->initialize();
$runId = strtolower(bin2hex(random_bytes(6)));
$alpha = tenantXlsxContext(101, 501, 'mt03-xlsx-alpha-' . $runId);
$beta = tenantXlsxContext(202, 502, 'mt03-xlsx-beta-' . $runId);
$invalid = tenantXlsxContext(303, 0, 'mt03-xlsx-invalid-' . $runId);
$fixtureDirectory = 'fixture-' . $runId;
$invalidDirectory = $serverRoot . '/public/storage/exports/tenants/v1/303/' . $fixtureDirectory;
$alphaUri = '';
$betaUri = '';
$alphaPath = '';
$betaPath = '';

try {
    $before = glob($serverRoot . '/public/storage/exports/tenants/v1/*/*' . $runId . '*.xlsx');
    try {
        XlsxExportService::createForTenant(
            null,
            'same-' . $runId,
            ['marker'],
            [['missing-context']]
        );
        throw new RuntimeException('Missing TenantContext unexpectedly created an export');
    } catch (TypeError) {
        expectTenantXlsx(
            glob($serverRoot . '/public/storage/exports/tenants/v1/*/*' . $runId . '*.xlsx') === $before,
            'Missing TenantContext produced a filesystem side effect'
        );
    }
    try {
        XlsxExportService::createForTenant(
            $invalid,
            'same-' . $runId,
            ['marker'],
            [['invalid-context']],
            $fixtureDirectory
        );
        throw new RuntimeException('Untrusted TenantContext unexpectedly created an export');
    } catch (InvalidArgumentException) {
        expectTenantXlsx(!is_dir($invalidDirectory), 'Untrusted TenantContext created its target directory');
    }

    $alphaUri = XlsxExportService::createForTenant(
        $alpha,
        'same-' . $runId,
        ['marker'],
        [['alpha-only-' . $runId]],
        $fixtureDirectory
    );
    $betaUri = XlsxExportService::createForTenant(
        $beta,
        'same-' . $runId,
        ['marker'],
        [['beta-only-' . $runId]],
        $fixtureDirectory
    );
    expectTenantXlsx(
        str_starts_with($alphaUri, 'storage/exports/tenants/v1/101/' . $fixtureDirectory . '/'),
        'Alpha export escaped its Tenant namespace'
    );
    expectTenantXlsx(
        str_starts_with($betaUri, 'storage/exports/tenants/v1/202/' . $fixtureDirectory . '/'),
        'Beta export escaped its Tenant namespace'
    );
    expectTenantXlsx(dirname($alphaUri) !== dirname($betaUri), 'Tenant exports share a physical directory');

    $alphaPath = $serverRoot . '/public/' . $alphaUri;
    $betaPath = $serverRoot . '/public/' . $betaUri;
    $alphaSheet = tenantXlsxSheet($alphaPath);
    $betaSheet = tenantXlsxSheet($betaPath);
    expectTenantXlsx(str_contains($alphaSheet, 'alpha-only-' . $runId), 'Alpha export lost its content');
    expectTenantXlsx(!str_contains($alphaSheet, 'beta-only-' . $runId), 'Alpha export leaked Beta content');
    expectTenantXlsx(str_contains($betaSheet, 'beta-only-' . $runId), 'Beta export lost its content');
    expectTenantXlsx(!str_contains($betaSheet, 'alpha-only-' . $runId), 'Beta export leaked Alpha content');

    try {
        XlsxExportService::deleteForTenant($alpha, $betaUri);
        throw new RuntimeException('Alpha deleted a Beta export');
    } catch (InvalidArgumentException) {
        expectTenantXlsx(is_file($betaPath), 'Cross-Tenant cleanup touched the Beta export');
    }
    expectTenantXlsx(XlsxExportService::deleteForTenant($alpha, $alphaUri), 'Alpha cleanup did not delete Alpha export');
    expectTenantXlsx(!is_file($alphaPath), 'Alpha export survived its own cleanup');
    expectTenantXlsx(is_file($betaPath), 'Alpha cleanup deleted Beta export');

    foreach ([
        'app/Modules/Official/Member/Application/MemberAdministrationService.php',
        'app/Modules/Official/Payment/Application/RechargeAdministrationService.php',
        'app/adminapi/application/log/OperationLogApplicationService.php',
    ] as $relativePath) {
        $source = (string)file_get_contents($serverRoot . '/' . $relativePath);
        expectTenantXlsx(str_contains($source, 'XlsxExportService::createForTenant('), 'Tenant caller did not adopt the trusted export API: ' . $relativePath);
        expectTenantXlsx(!str_contains($source, 'XlsxExportService::create('), 'Tenant caller retained the legacy instance-owned API: ' . $relativePath);
    }

    $tenantCreate = new ReflectionMethod(XlsxExportService::class, 'createForTenant');
    expectTenantXlsx(
        $tenantCreate->getParameters()[0]->getType()?->getName() === TenantContext::class,
        'Tenant export boundary does not require TenantContext'
    );

    echo "MT03-TENANT-XLSX-EXPORT-001 passed\n";
} finally {
    foreach ([$alphaPath, $betaPath] as $path) {
        if ($path !== '' && is_file($path)) {
            unlink($path);
        }
    }
    foreach ([dirname($alphaPath), dirname($betaPath)] as $directory) {
        if ($directory !== '.' && is_dir($directory)) {
            rmdir($directory);
        }
    }
}
