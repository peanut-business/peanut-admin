<?php
declare(strict_types=1);

namespace app\common\service\export;

use PDO;
use PeanutAdmin\ImportExport\Application\ImportExportException;
use PeanutAdmin\ImportExport\Contract\ColumnDefinition;
use PeanutAdmin\ImportExport\Contract\DataProvider;
use PeanutAdmin\ImportExport\Contract\ExportBatch;
use PeanutAdmin\ImportExport\Contract\RowIssue;
use PeanutAdmin\ImportExport\Contract\SchemaDefinition;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;

final readonly class OperationLogExportProvider implements DataProvider
{
    public const KEY = 'app.operation-log';

    public function __construct(private PDO $pdo)
    {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function schema(): SchemaDefinition
    {
        return new SchemaDefinition('1', [
            new ColumnDefinition('id', 'ID', false, true, false, 20),
            new ColumnDefinition('username', '管理员', false, true, false, 255),
            new ColumnDefinition('ip', 'IP', false, true, false, 64),
            new ColumnDefinition('uri', '请求地址', false, true, false, 512),
            new ColumnDefinition('method', '方法', false, true, false, 16),
            new ColumnDefinition('params', '参数', false, true, false, 65535),
            new ColumnDefinition('create_time', '操作时间', false, true, false, 32),
        ]);
    }

    /** @return list<RowIssue> */
    public function validateImport(AuthorizedOperationContext $context, array $row): array
    {
        throw ImportExportException::denied();
    }

    public function importRow(AuthorizedOperationContext $context, array $row, string $idempotencyKey): void
    {
        throw ImportExportException::denied();
    }

    public function exportBatch(AuthorizedOperationContext $context, ?string $cursor, int $limit): ExportBatch
    {
        if ($context->tenantContext->tenantId < 1 || $limit < 1 || $limit > 500) {
            throw ImportExportException::denied();
        }
        $afterId = 0;
        if ($cursor !== null) {
            if (preg_match('/^[1-9][0-9]*$/D', $cursor) !== 1) {
                throw ImportExportException::invalid();
            }
            $afterId = (int)$cursor;
        }

        $statement = $this->pdo->prepare(<<<'SQL'
SELECT id, username, ip, uri, method, params, create_time
FROM pa_operation_log
WHERE tenant_id = :tenant_id AND id > :after_id
ORDER BY id ASC
LIMIT :limit
SQL);
        $statement->bindValue('tenant_id', $context->tenantContext->tenantId, PDO::PARAM_INT);
        $statement->bindValue('after_id', $afterId, PDO::PARAM_INT);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $rows = [];
        $lastId = null;
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $lastId = (int)$row['id'];
            $rows[] = [
                'id' => $lastId,
                'username' => (string)$row['username'],
                'ip' => (string)$row['ip'],
                'uri' => (string)$row['uri'],
                'method' => (string)$row['method'],
                'params' => (string)$row['params'],
                'create_time' => empty($row['create_time']) ? '' : date('Y-m-d H:i:s', (int)$row['create_time']),
            ];
        }

        return new ExportBatch($rows, count($rows) === $limit && $lastId !== null ? (string)$lastId : null);
    }
}
