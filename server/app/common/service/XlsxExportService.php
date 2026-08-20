<?php
declare(strict_types=1);

namespace app\common\service;

use PeanutAdmin\Kernel\Tenancy\TenantScope;
use PeanutAdmin\Kernel\Auth\TenantContext;
use ZipArchive;

/** 小型无外部依赖 XLSX 导出器，供管理端表格导出复用。 */
class XlsxExportService
{
    /**
     * @deprecated Instance-owned compatibility alias. Tenant exports must use createForTenant().
     */
    public static function create(string $name, array $headings, array $rows): string
    {
        return self::createInstanceOwned($name, $headings, $rows);
    }

    /**
     * @deprecated Instance-owned compatibility alias. Tenant exports must use createForTenant().
     */
    public static function createInDirectory(
        string $name,
        array $headings,
        array $rows,
        string $relativeDirectory
    ): string
    {
        return self::createInstanceOwned($name, $headings, $rows, $relativeDirectory);
    }

    /** Explicit compatibility boundary for artifacts owned by the application instance. */
    public static function createInstanceOwned(
        string $name,
        array $headings,
        array $rows,
        string $relativeDirectory = ''
    ): string
    {
        return self::createArtifact($name, $headings, $rows, $relativeDirectory);
    }

    /** Creates a public synchronous export below the trusted Tenant's physical namespace. */
    public static function createForTenant(
        TenantContext $context,
        string $name,
        array $headings,
        array $rows,
        string $relativeDirectory = ''
    ): string
    {
        $tenantId = self::trustedTenantId($context);
        $relativeDirectory = self::normalizeRelativeDirectory($relativeDirectory);
        $tenantDirectory = sprintf('tenants/v1/%d', $tenantId)
            . ($relativeDirectory === '' ? '' : '/' . $relativeDirectory);

        return self::createArtifact($name, $headings, $rows, $tenantDirectory);
    }

    /** Deletes only an XLSX artifact proven to be inside the trusted Tenant namespace. */
    public static function deleteForTenant(TenantContext $context, string $uri): bool
    {
        $tenantId = self::trustedTenantId($context);
        $uri = trim(str_replace('\\', '/', $uri), '/');
        $prefix = sprintf('storage/exports/tenants/v1/%d/', $tenantId);
        if (!str_starts_with($uri, $prefix)
            || str_contains($uri, '..')
            || preg_match('#^storage/exports/tenants/v1/[1-9][0-9]*(?:/[a-zA-Z0-9][a-zA-Z0-9_-]*)*/[^/]+\.xlsx$#D', $uri) !== 1) {
            throw new \InvalidArgumentException('导出文件不属于当前租户');
        }

        $path = rtrim(public_path($uri), DIRECTORY_SEPARATOR);
        if (!is_file($path)) {
            return false;
        }
        if (!unlink($path)) {
            throw new \RuntimeException('导出文件清理失败');
        }
        return true;
    }

    private static function createArtifact(
        string $name,
        array $headings,
        array $rows,
        string $relativeDirectory
    ): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('服务器未安装 ZipArchive 扩展，无法导出 XLSX');
        }
        $name = preg_replace('/[\\\\\/:*?"<>|]+/u', '_', trim($name)) ?: '导出数据';
        $name = preg_replace('/\.xlsx$/i', '', $name) ?: '导出数据';
        $fileName = $name . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.xlsx';
        $relativeDirectory = self::normalizeRelativeDirectory($relativeDirectory);
        $relativePath = 'storage/exports'
            . ($relativeDirectory === '' ? '' : '/' . $relativeDirectory);
        $directory = public_path($relativePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('导出目录创建失败');
        }
        $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('导出文件创建失败');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::xml(mb_substr($name, 0, 31))
            . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>');
        array_unshift($rows, $headings);
        $zip->addFromString('xl/worksheets/sheet1.xml', self::worksheet($rows));
        $zip->close();
        return $relativePath . '/' . $fileName;
    }

    private static function trustedTenantId(TenantContext $context): int
    {
        if ($context->tenantId < 1
            || $context->accountId < 1
            || $context->memberId < 1
            || $context->authorizationRevision < 1
            || $context->sessionKey === ''
            || $context->clientKey === ''
            || $context->requestId === '') {
            throw new \InvalidArgumentException('可信租户上下文缺失');
        }
        return TenantScope::fromTrustedContext($context->tenantId, $context->requestId)->tenantId();
    }

    private static function normalizeRelativeDirectory(string $relativeDirectory): string
    {
        $relativeDirectory = trim(str_replace('\\', '/', $relativeDirectory), '/');
        if ($relativeDirectory !== ''
            && (!preg_match('#^[a-zA-Z0-9][a-zA-Z0-9/_-]*$#D', $relativeDirectory)
                || str_contains($relativeDirectory, '..'))) {
            throw new \InvalidArgumentException('导出目录非法');
        }
        return $relativeDirectory;
    }

    private static function worksheet(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach (array_values($rows) as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $xml .= '<row r="' . $rowNumber . '">';
            foreach (array_values((array)$row) as $columnIndex => $value) {
                $cell = self::columnName($columnIndex + 1) . $rowNumber;
                if (is_int($value) || is_float($value)) {
                    $numeric = rtrim(rtrim(number_format((float)$value, 10, '.', ''), '0'), '.');
                    $xml .= '<c r="' . $cell . '"><v>' . $numeric . '</v></c>';
                    continue;
                }
                $xml .= '<c r="' . $cell . '" t="inlineStr"><is><t xml:space="preserve">'
                    . self::xml((string)$value) . '</t></is></c>';
            }
            $xml .= '</row>';
        }
        return $xml . '</sheetData></worksheet>';
    }

    private static function xml(string $value): string
    {
        $value = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }
        return $name;
    }
}
