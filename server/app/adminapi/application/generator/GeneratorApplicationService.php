<?php
declare(strict_types=1);

namespace app\adminapi\application\generator;

use app\adminapi\service\generator\GeneratorArchiveService;
use app\adminapi\service\generator\GeneratorImportPersistence;
use app\adminapi\infrastructure\generator\ThinkPhpGeneratorMetadata;
use app\adminapi\service\generator\GeneratorRenderService;
use app\common\http\PageResult;
use app\common\model\generator\GeneratorColumn;
use app\common\model\generator\GeneratorDownload;
use app\common\model\generator\GeneratorTable;
use app\common\persistence\TransactionalExecution;
use app\common\support\PaginationInput;

class GeneratorApplicationService
{
    public function __construct(
        private readonly GeneratorImportPersistence $imports,
        private readonly TransactionalExecution $transactions,
        private readonly ThinkPhpGeneratorMetadata $metadata,
        private readonly string $databasePrefix,
    ) {}

    public function sourceTables(array $params): PageResult
    {
        $pagination = PaginationInput::from($params);
        return $this->metadata->tables(
            trim((string) ($params['keyword'] ?? '')),
            $pagination->page,
            $pagination->pageSize,
        );
    }

    public function lists(int $adminId, array $params): PageResult
    {
        $pagination = PaginationInput::from($params);
        $query = GeneratorTable::where('admin_id', $adminId);
        if (!empty($params['keyword'])) {
            $keyword = trim((string) $params['keyword']);
            $query->where(function ($sub) use ($keyword): void {
                $sub->where('table_name', 'like', '%' . $keyword . '%')
                    ->whereOr('table_comment', 'like', '%' . $keyword . '%')
                    ->whereOr('entity_name', 'like', '%' . $keyword . '%');
            });
        }
        $pageResult = $pagination->result($query->order('id', 'desc'));
        $pageResult = GeneratorImportPersistence::arrayPage($pageResult);
        $lists = $pageResult->items;
        return new PageResult($lists, $pageResult->total, $pageResult->page, $pageResult->pageSize);
    }

    public function detail(int $adminId, int $id): array
    {
        return self::ownedTable($adminId, $id, true);
    }

    public function importTables(int $adminId, array $tableNames): bool
    {
        $tableNames = array_values(array_unique(array_map('strval', $tableNames)));
        $metadata = $this->metadata->definitions($tableNames);
        $definitions = [];
        foreach ($tableNames as $tableName) {
            $entityName = $this->entityName($tableName);
            self::assertEntity($entityName);
            $definitions[] = [
                'table_name' => $tableName,
                'table_comment' => $metadata[$tableName]['table_comment'],
                'entity_name' => $entityName,
                'columns' => $metadata[$tableName]['columns'],
            ];
        }
        $this->imports->import($adminId, $definitions);
        return true;
    }

    public function sync(int $adminId, int $id): bool
    {
        $this->transactions->run(function () use ($adminId, $id): void {
                $table = self::ownedTableModel($adminId, $id, true);
                $metadata = $this->metadata->columns((string) $table->table_name);
                $existing = [];
                foreach (GeneratorColumn::where('table_id', $id)->select() as $row) {
                    $existing[(string) $row->column_name] = $row;
                }
                $seen = [];
                $persist = [];
                foreach ($metadata as $column) {
                    $name = (string) $column['column_name'];
                    $seen[] = $name;
                    if (isset($existing[$name])) {
                        $row = $existing[$name];
                        $persist[] = [
                            'id' => (int)$row->id,
                            'column_type' => $column['column_type'],
                            'php_type' => $column['php_type'],
                            'is_required' => $column['is_required'],
                            'is_pk' => $column['is_pk'],
                            'sort' => $column['sort'],
                        ];
                    } else {
                        $persist[] = ['table_id' => $id] + $column;
                    }
                }
                if ($persist !== []) {
                    (new GeneratorColumn())->saveAll($persist);
                }
                $delete = GeneratorColumn::where('table_id', $id);
                if ($seen !== []) $delete->whereNotIn('column_name', $seen);
                $delete->delete();
                $table->save(['table_comment' => (string)$this->metadata->table((string)$table->table_name)['table_comment']]);
        });
        return true;
    }

    public function update(int $adminId, array $params): bool
    {
        $this->transactions->run(function () use ($adminId, $params): void {
                $id = (int) $params['id'];
                $table = self::ownedTableModel($adminId, $id, true);
                $module = trim((string) $params['module_name']);
                $entity = trim((string) $params['entity_name']);
                self::assertModule($module);
                self::assertEntity($entity);

                $columns = [];
                foreach (GeneratorColumn::where('table_id', $id)->select() as $column) {
                    $columns[(int) $column->id] = $column;
                }
                $columnNames = array_map('strval', array_column(array_map(
                    static fn($column): array => $column->toArray(),
                    array_values($columns)
                ), 'column_name'));
                $relations = $this->normalizeRelations(
                    $adminId,
                    $params['relations'] ?? [],
                    $columnNames
                );
                $tree = self::normalizeTree($params['tree_config'] ?? [], $columnNames, (string) $params['template_type']);

                $submittedIds = [];
                $persist = [];
                foreach ($params['columns'] as $column) {
                    $columnId = (int) ($column['id'] ?? 0);
                    if (!isset($columns[$columnId])) throw new \RuntimeException('字段不属于当前数据表');
                    $submittedIds[] = $columnId;
                    $persist[] = [
                        'id' => $columnId,
                        'column_comment' => trim((string) ($column['column_comment'] ?? '')),
                        'is_required' => self::flag($column['is_required'] ?? 0),
                        'is_insert' => self::flag($column['is_insert'] ?? 0),
                        'is_update' => self::flag($column['is_update'] ?? 0),
                        'is_lists' => self::flag($column['is_lists'] ?? 0),
                        'is_query' => self::flag($column['is_query'] ?? 0),
                        'query_type' => self::choice((string) ($column['query_type'] ?? '='), ['=', '<>', '>', '>=', '<', '<=', 'like', 'between']),
                        'view_type' => self::choice((string) ($column['view_type'] ?? 'input'), ['input', 'textarea', 'select', 'radio', 'checkbox', 'switch', 'date', 'datetime', 'number']),
                        'dict_type' => trim((string) ($column['dict_type'] ?? '')),
                    ];
                }
                if (count(array_unique($submittedIds)) !== count($columns)) {
                    throw new \RuntimeException('必须提交当前数据表的全部字段配置');
                }
                if ($persist !== []) {
                    (new GeneratorColumn())->saveAll($persist);
                }

                $table->save([
                    'table_comment' => trim((string) $params['table_comment']),
                    'module_name' => $module,
                    'entity_name' => $entity,
                    'template_type' => (string) $params['template_type'],
                    'data_owner' => (string) $params['data_owner'],
                    'target_edition' => (string) $params['target_edition'],
                    'author' => trim((string) ($params['author'] ?? '')),
                    'tree_config' => $tree,
                    'relations' => $relations,
                ]);
        });
        return true;
    }

    public function delete(int $adminId, array $ids): bool
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $this->transactions->run(function () use ($adminId, $ids): void {
                foreach (GeneratorTable::where('admin_id', $adminId)->whereNotIn('id', $ids)->select() as $table) {
                    foreach ((array)$table->relations as $relation) {
                        if (in_array((int)($relation['target_table_id'] ?? 0), $ids, true)) {
                            throw new \RuntimeException('生成配置仍被其他关系引用，不能删除');
                        }
                    }
                }
                $owned = GeneratorTable::where('admin_id', $adminId)->whereIn('id', $ids)->column('id');
                if (count($owned) !== count($ids)) throw new \RuntimeException('生成配置不存在或无权访问');
                GeneratorColumn::whereIn('table_id', $ids)->delete();
                GeneratorTable::where('admin_id', $adminId)->whereIn('id', $ids)->delete();
        });
        return true;
    }

    public function preview(int $adminId, int $id): array
    {
        $tables = $this->snapshotTables($adminId, [$id]);
        return GeneratorRenderService::render($tables[0]);
    }

    public function generate(int $adminId, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $tables = $this->snapshotTables($adminId, $ids);
        $files = [];
        foreach ($tables as $table) {
            foreach (GeneratorRenderService::render($table) as $file) {
                $path = (string) $file['path'];
                if (isset($files[$path])) throw new \RuntimeException('生成文件路径冲突：' . $path);
                $files[$path] = $file;
            }
        }

        $archive = GeneratorArchiveService::create(
            array_values($files),
            $adminId,
            'peanut-code-' . date('YmdHis') . '.zip'
        );
        $token = bin2hex(random_bytes(32));
        try {
            GeneratorDownload::create([
                'admin_id' => $adminId,
                'token_hash' => hash('sha256', $token),
                'archive_path' => $archive['archive_path'],
                'download_name' => $archive['download_name'],
                'expire_time' => time() + 600,
                'used_time' => 0,
            ]);
        } catch (\Throwable $e) {
            GeneratorArchiveService::cleanup($archive['archive_path'], $adminId);
            throw $e;
        }
        return ['download_token' => $token, 'file_name' => $archive['download_name'], 'expires_in' => 600];
    }

    public function consumeDownload(int $adminId, string $token): array
    {
        return $this->transactions->run(function () use ($adminId, $token): array {
            $row = GeneratorDownload::where([
                'admin_id' => $adminId,
                'token_hash' => hash('sha256', $token),
                'used_time' => 0,
            ])->where('expire_time', '>', time())->lock(true)->findOrEmpty();
            if ($row->isEmpty()) throw new \RuntimeException('下载令牌无效或已过期');
            $path = GeneratorArchiveService::resolve((string) $row->archive_path, $adminId);
            $row->save(['used_time' => time()]);
            return [
                'path' => $path,
                'file_name' => (string)$row->download_name,
                'archive_path' => (string)$row->archive_path,
            ];
        });
    }

    public function models(int $adminId): array
    {
        return GeneratorTable::where('admin_id', $adminId)
            ->field('id,module_name,entity_name,table_name,data_owner,target_edition')
            ->order('entity_name', 'asc')->select()->toArray();
    }

    private static function ownedTable(int $adminId, int $id, bool $withColumns): array
    {
        $query = GeneratorTable::where(['id' => $id, 'admin_id' => $adminId]);
        if ($withColumns) $query->with('columns');
        $table = $query->findOrEmpty();
        if ($table->isEmpty()) throw new \RuntimeException('生成配置不存在或无权访问');
        return self::hydrateRelations($adminId, $table->toArray());
    }

    private static function ownedTableModel(int $adminId, int $id, bool $lock = false): GeneratorTable
    {
        $query = GeneratorTable::where(['id' => $id, 'admin_id' => $adminId]);
        if ($lock) $query->lock(true);
        $table = $query->findOrEmpty();
        if ($table->isEmpty()) throw new \RuntimeException('生成配置不存在或无权访问');
        return $table;
    }

    private function entityName(string $tableName): string
    {
        $name = $this->databasePrefix !== '' && str_starts_with($tableName, $this->databasePrefix)
            ? substr($tableName, strlen($this->databasePrefix)) : $tableName;
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    private static function assertModule(string $module): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $module)) throw new \InvalidArgumentException('模块名称格式错误');
    }

    private static function assertEntity(string $entity): void
    {
        if (!preg_match('/^[A-Z][A-Za-z0-9]{0,63}$/D', $entity)) throw new \InvalidArgumentException('实体名称格式错误');
    }

    private function normalizeRelations(
        int $adminId,
        mixed $relations,
        array $columnNames
    ): array
    {
        if (!is_array($relations) || count($relations) > 20) throw new \InvalidArgumentException('关系配置格式错误');
        $normalized = [];
        foreach ($relations as $relation) {
            if (!is_array($relation)) throw new \InvalidArgumentException('关系配置格式错误');
            $name = (string) ($relation['name'] ?? '');
            $targetTableId = (int)($relation['target_table_id'] ?? 0);
            if ($targetTableId <= 0) throw new \InvalidArgumentException('关系目标配置无效');
            $type = self::choice((string) ($relation['type'] ?? ''), ['belongsTo', 'hasOne', 'hasMany']);
            $this->metadata->assertIdentifier($name, '关系名称');
            $local = (string) ($relation['local_key'] ?? 'id');
            $foreign = (string) ($relation['foreign_key'] ?? 'id');
            $this->metadata->assertIdentifier($local, '本地键');
            $this->metadata->assertIdentifier($foreign, '外键');
            if (!in_array($local, $columnNames, true)) {
                throw new \InvalidArgumentException('关系本地字段不存在');
            }
            $normalized[] = [
                'target_table_id' => $targetTableId,
                'name' => $name,
                'type' => $type,
                'local_key' => $local,
                'foreign_key' => $foreign,
            ];
        }
        if ($normalized === []) {
            return [];
        }

        $targetIds = array_values(array_unique(array_column($normalized, 'target_table_id')));
        sort($targetIds);
        $targets = GeneratorTable::where('admin_id', $adminId)->whereIn('id', $targetIds)
            ->order('id', 'asc')->lock(true)->column('id');
        if (count($targets) !== count($targetIds)) {
            throw new \RuntimeException('关系目标配置不存在或无权访问');
        }
        $targetColumns = [];
        foreach (GeneratorColumn::whereIn('table_id', $targetIds)
            ->order(['table_id' => 'asc', 'sort' => 'asc'])->select()->toArray() as $column) {
            $targetColumns[(int)$column['table_id']][] = (string)$column['column_name'];
        }
        foreach ($normalized as $relation) {
            if (!in_array(
                $relation['foreign_key'],
                $targetColumns[(int)$relation['target_table_id']] ?? [],
                true,
            )) {
                throw new \InvalidArgumentException('关系目标字段不存在');
            }
        }
        return $normalized;
    }

    /** @return array<int,array<string,mixed>> */
    private function snapshotTables(int $adminId, array $ids): array
    {
        sort($ids);
        return $this->transactions->run(function () use ($adminId, $ids): array {
            $models = GeneratorTable::where('admin_id', $adminId)
                ->whereIn('id', $ids)->order('id', 'asc')->lock(true)->select();
            if ($models->count() !== count($ids)) {
                throw new \RuntimeException('生成配置不存在或无权访问');
            }
            $columnsByTable = [];
            foreach (GeneratorColumn::whereIn('table_id', $ids)
                ->order(['table_id' => 'asc', 'sort' => 'asc'])->lock(true)->select()->toArray() as $column) {
                $columnsByTable[(int)$column['table_id']][] = $column;
            }
            $tables = [];
            foreach ($models as $model) {
                $table = $model->toArray();
                $table['columns'] = $columnsByTable[(int)$model->id] ?? [];
                $tables[] = $table;
            }
            $targets = self::relationTargets($adminId, $tables, true);
            foreach ($tables as &$table) {
                $table = self::hydrateRelationsFromTargets($table, $targets);
            }
            unset($table);
            return $tables;
        });
    }

    private static function hydrateRelations(int $adminId, array $table, bool $lock = false): array
    {
        $targets = self::relationTargets($adminId, [$table], $lock);
        return self::hydrateRelationsFromTargets($table, $targets);
    }

    /**
     * @param array<int,array<string,mixed>> $tables
     * @return array<int,GeneratorTable>
     */
    private static function relationTargets(int $adminId, array $tables, bool $lock): array
    {
        $targetIds = [];
        foreach ($tables as $table) {
            foreach (array_values((array)($table['relations'] ?? [])) as $relation) {
                $targetIds[] = (int)($relation['target_table_id'] ?? 0);
            }
        }
        $targetIds = array_values(array_unique(array_filter($targetIds, static fn(int $id): bool => $id > 0)));
        sort($targetIds);
        if ($targetIds === []) {
            return [];
        }

        $query = GeneratorTable::where('admin_id', $adminId)
            ->whereIn('id', $targetIds)
            ->order('id', 'asc');
        if ($lock) {
            $query->lock(true);
        }
        $targets = [];
        foreach ($query->select() as $target) {
            $targets[(int)$target->id] = $target;
        }
        if (count($targets) !== count($targetIds)) {
            throw new \RuntimeException('关系目标配置不存在或无权访问');
        }
        return $targets;
    }

    /** @param array<int,GeneratorTable> $targets */
    private static function hydrateRelationsFromTargets(array $table, array $targets): array
    {
        $relations = array_values((array)($table['relations'] ?? []));
        if ($relations === []) {
            $table['relations'] = [];
            return $table;
        }
        foreach ($relations as &$relation) {
            $target = $targets[(int)$relation['target_table_id']] ?? null;
            if (!$target instanceof GeneratorTable) {
                throw new \RuntimeException('关系目标配置不存在或无权访问');
            }
            $relation['module'] = (string)$target->module_name;
            $relation['model'] = (string)$target->entity_name;
            $relation['data_owner'] = (string)$target->data_owner;
            $relation['target_edition'] = (string)$target->target_edition;
        }
        unset($relation);
        $table['relations'] = $relations;
        return $table;
    }

    private static function normalizeTree($tree, array $columnNames, string $templateType): array
    {
        if ($templateType === 'crud') return [];
        if (!is_array($tree)) throw new \InvalidArgumentException('树配置格式错误');
        $result = [
            'id_field' => (string) ($tree['id_field'] ?? ''),
            'parent_field' => (string) ($tree['parent_field'] ?? ''),
            'name_field' => (string) ($tree['name_field'] ?? ''),
        ];
        foreach ($result as $field) {
            if (!in_array($field, $columnNames, true)) throw new \InvalidArgumentException('树配置字段不存在');
        }
        if ($result['id_field'] === $result['parent_field']) throw new \InvalidArgumentException('树主键和父级字段不能相同');
        return $result;
    }

    private static function flag($value): int
    {
        if (!in_array($value, [0, 1, '0', '1'], true)) throw new \InvalidArgumentException('字段开关值错误');
        return (int) $value;
    }

    private static function choice(string $value, array $allowed): string
    {
        if (!in_array($value, $allowed, true)) throw new \InvalidArgumentException('配置枚举值错误');
        return $value;
    }
}
