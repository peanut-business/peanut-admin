<?php
declare(strict_types=1);

namespace app\adminapi\service\generator;

use app\common\model\generator\GeneratorColumn;
use app\common\model\generator\GeneratorTable;
use app\common\persistence\TransactionalExecution;
use app\common\persistence\ConvertsModelPage;

/** Instance-owned batch persistence for imported generator metadata. */
final readonly class GeneratorImportPersistence
{
    use ConvertsModelPage;

    public function __construct(private TransactionalExecution $transactions)
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

        $this->transactions->run(function () use ($adminId, $definitions): void {
            $tableNames = array_column($definitions, 'table_name');
            $existing = GeneratorTable::where('admin_id', $adminId)
                ->whereIn('table_name', $tableNames)
                ->value('table_name');
            if (is_string($existing) && $existing !== '') {
                throw new \RuntimeException('数据表已导入：' . $existing);
            }

            $tables = (new GeneratorTable())->saveAll(array_map(
                static fn(array $definition): array => [
                    'admin_id' => $adminId,
                    'table_name' => $definition['table_name'],
                    'table_comment' => $definition['table_comment'],
                    'module_name' => 'generated',
                    'entity_name' => $definition['entity_name'],
                    'template_type' => 'crud',
                    'data_owner' => null,
                    'target_edition' => null,
                    'author' => '',
                    'tree_config' => [],
                    'relations' => [],
                ],
                $definitions,
            ));
            if ($tables->count() !== count($definitions)) {
                throw new \RuntimeException('生成器父表写入不完整');
            }

            $columnRows = [];
            foreach ($tables as $index => $table) {
                $tableId = (int)$table->id;
                if ($tableId < 1) {
                    throw new \RuntimeException('生成器父表身份无效');
                }
                foreach ($definitions[$index]['columns'] as $column) {
                    $columnRows[] = ['table_id' => $tableId] + $column;
                }
            }
            if ($columnRows !== []) {
                (new GeneratorColumn())->saveAll($columnRows);
            }
        });
    }
}
