<?php
declare(strict_types=1);

namespace app\common\service\export;

use PDO;
use PeanutAdmin\ImportExport\Application\ImportExportException;
use PeanutAdmin\ImportExport\File\FileMediaGateway;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;

/** Private runtime storage adapter; no generated object is placed below public/. */
final readonly class AppFileMediaGateway implements FileMediaGateway
{
    public function __construct(private PDO $pdo, private string $root)
    {
        if (trim($root) === '') {
            throw ImportExportException::fileUnavailable();
        }
    }

    public function openCsvInput(AuthorizedOperationContext $context, string $fileKey)
    {
        throw ImportExportException::denied();
    }

    public function storePrivateCsv(
        AuthorizedOperationContext $context,
        string $operationKey,
        string $purpose,
        string $filename,
        $stream,
    ): string {
        if (!is_resource($stream)
            || $purpose !== 'result'
            || preg_match('/^iox_[0-9a-f]{32}$/D', $operationKey) !== 1
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}\.csv$/D', $filename) !== 1
        ) {
            throw ImportExportException::fileUnavailable();
        }
        $tenantId = $context->tenantContext->tenantId;
        $memberId = $context->tenantContext->memberId;
        if ($tenantId < 1 || $memberId < 1) {
            throw ImportExportException::denied();
        }

        $directory = rtrim($this->root, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . 'v1'
            . DIRECTORY_SEPARATOR . $tenantId . DIRECTORY_SEPARATOR . 'exports';
        if ((!is_dir($directory) && !mkdir($directory, 0700, true)) || !is_dir($directory)) {
            throw ImportExportException::fileUnavailable();
        }
        @chmod($directory, 0700);

        $fileKey = 'file_' . bin2hex(random_bytes(16));
        $storageKey = sprintf('tenants/v1/%d/exports/%s.csv', $tenantId, $fileKey);
        $path = $this->path($storageKey);
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
        $output = fopen($temporary, 'x+b');
        if (!is_resource($output)) {
            throw ImportExportException::fileUnavailable();
        }
        try {
            $bytes = stream_copy_to_stream($stream, $output, 20 * 1024 * 1024 + 1);
            if (!is_int($bytes) || $bytes < 1 || $bytes > 20 * 1024 * 1024) {
                throw ImportExportException::limitExceeded();
            }
            fflush($output);
            fclose($output);
            $output = null;
            @chmod($temporary, 0600);
            if (!rename($temporary, $path)) {
                throw ImportExportException::fileUnavailable();
            }
            $sha256 = hash_file('sha256', $path);
            if (!is_string($sha256)) {
                throw ImportExportException::fileUnavailable();
            }

            $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_file_object (
  file_key, tenant_id, storage_provider_key, storage_key, original_name,
  media_type, size_bytes, sha256, status, created_by_member_id, revision,
  created_at, updated_at, archived_at
) VALUES (
  :file_key, :tenant_id, 'app.private-runtime', :storage_key, :original_name,
  'text/csv', :size_bytes, :sha256, 'ready', :member_id, 1,
  UTC_TIMESTAMP(3), UTC_TIMESTAMP(3), NULL
)
SQL);
            $statement->execute([
                'file_key' => $fileKey,
                'tenant_id' => $tenantId,
                'storage_key' => $storageKey,
                'original_name' => $filename,
                'size_bytes' => $bytes,
                'sha256' => $sha256,
                'member_id' => $memberId,
            ]);
        } catch (\Throwable $exception) {
            if (is_resource($output)) {
                fclose($output);
            }
            @unlink($temporary);
            @unlink($path);
            throw $exception;
        }

        return $fileKey;
    }

    /** @return array{path:string,filename:string} */
    public function authorizedDownload(AuthorizedOperationContext $context, string $fileKey): array
    {
        if (preg_match('/^file_[0-9a-f]{32}$/D', $fileKey) !== 1) {
            throw ImportExportException::fileUnavailable();
        }
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT f.storage_key, f.original_name
FROM pa_file_object f
JOIN pa_import_export_operation o
  ON o.tenant_id = f.tenant_id
 AND o.result_file_key = f.file_key
 AND o.status = 'succeeded'
 AND o.retention_until > UTC_TIMESTAMP(3)
WHERE f.tenant_id = :tenant_id
  AND f.file_key = :file_key
  AND f.storage_provider_key = 'app.private-runtime'
  AND f.status = 'ready'
LIMIT 1
SQL);
        $statement->execute([
            'tenant_id' => $context->tenantContext->tenantId,
            'file_key' => $fileKey,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw ImportExportException::fileUnavailable();
        }
        $storageKey = (string)$row['storage_key'];
        $prefix = sprintf('tenants/v1/%d/exports/', $context->tenantContext->tenantId);
        if (!str_starts_with($storageKey, $prefix)) {
            throw ImportExportException::fileUnavailable();
        }
        $path = $this->path($storageKey);
        if (!is_file($path)) {
            throw ImportExportException::fileUnavailable();
        }

        return ['path' => $path, 'filename' => (string)$row['original_name']];
    }

    private function path(string $storageKey): string
    {
        if (str_contains($storageKey, '..') || str_starts_with($storageKey, '/')) {
            throw ImportExportException::fileUnavailable();
        }
        return rtrim($this->root, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
    }
}
