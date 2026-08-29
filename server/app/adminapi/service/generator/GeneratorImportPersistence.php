<?php
declare(strict_types=1);

namespace app\adminapi\service\generator;

use PDO;

/** Instance-owned batch persistence for imported generator metadata. */
final readonly class GeneratorImportPersistence
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param list<array{table_name:string,table_comment:string,entity_name:string,columns:list<array<string,mixed>>}> $definitions
     */
    public function import(int $adminId, array $definitions): void
    {
        if ($adminId < 1 || $definitions === []) {
            throw new \InvalidArgumentException('生成器导入参数无效');
        }

        $started = !$this->pdo->inTransaction();
        if ($started) {
            $this->pdo->beginTransaction();
        }
        try {
            $tableNames = array_column($definitions, 'table_name');
            $existing = $this->selectTableIds($adminId, $tableNames);
            if ($existing !== []) {
                throw new \RuntimeException('数据表已导入：' . (string)array_key_first($existing));
            }

            $now = time();
            $tableRows = array_map(static fn(array $definition): array => [
                $adminId,
                $definition['table_name'],
                $definition['table_comment'],
                'generated',
                $definition['entity_name'],
                'crud',
                null,
                null,
                '',
                '[]',
                '[]',
                $now,
                $now,
            ], $definitions);
            $this->insertRows('pa_generator_table', [
                'admin_id', 'table_name', 'table_comment', 'module_name', 'entity_name',
                'template_type', 'data_owner', 'target_edition', 'author', 'tree_config',
                'relations', 'create_time', 'update_time',
            ], $tableRows);

            $tableIds = $this->selectTableIds($adminId, $tableNames);
            if (count($tableIds) !== count($definitions)) {
                throw new \RuntimeException('生成器父表写入不完整');
            }

            $columnRows = [];
            foreach ($definitions as $definition) {
                $tableId = $tableIds[$definition['table_name']] ?? null;
                if (!is_int($tableId) || $tableId < 1) {
                    throw new \RuntimeException('生成器父表身份无效');
                }
                foreach ($definition['columns'] as $column) {
                    $columnRows[] = [
                        $tableId,
                        $column['column_name'],
                        $column['column_comment'],
                        $column['column_type'],
                        $column['php_type'],
                        $column['is_required'],
                        $column['is_pk'],
                        $column['is_insert'],
                        $column['is_update'],
                        $column['is_lists'],
                        $column['is_query'],
                        $column['query_type'],
                        $column['view_type'],
                        $column['dict_type'],
                        $column['sort'],
                        $now,
                        $now,
                    ];
                }
            }
            $this->insertRows('pa_generator_column', [
                'table_id', 'column_name', 'column_comment', 'column_type', 'php_type',
                'is_required', 'is_pk', 'is_insert', 'is_update', 'is_lists', 'is_query',
                'query_type', 'view_type', 'dict_type', 'sort', 'create_time', 'update_time',
            ], $columnRows);

            if ($started) {
                $this->pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param list<string> $tableNames @return array<string,int> */
    private function selectTableIds(int $adminId, array $tableNames): array
    {
        $placeholders = implode(',', array_fill(0, count($tableNames), '?'));
        $statement = $this->pdo->prepare(
            "SELECT id, table_name FROM pa_generator_table WHERE admin_id = ? AND table_name IN ({$placeholders})"
        );
        $statement->execute([$adminId, ...$tableNames]);
        $result = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(string)$row['table_name']] = (int)$row['id'];
        }
        return $result;
    }

    /** @param list<string> $columns @param list<list<mixed>> $rows */
    private function insertRows(string $table, array $columns, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $row = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES %s',
            $table,
            implode('`,`', $columns),
            implode(',', array_fill(0, count($rows), $row)),
        );
        $bindings = [];
        foreach ($rows as $values) {
            array_push($bindings, ...$values);
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);
        if ($statement->rowCount() !== count($rows)) {
            throw new \RuntimeException('生成器批量写入不完整');
        }
    }
}
