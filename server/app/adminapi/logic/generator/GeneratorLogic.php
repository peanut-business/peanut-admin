<?php
declare(strict_types=1);

namespace app\adminapi\logic\generator;

use app\adminapi\service\generator\GeneratorArchiveService;
use app\adminapi\service\generator\GeneratorMetadataService;
use app\adminapi\service\generator\GeneratorRenderService;
use app\common\logic\BaseLogic;
use app\common\model\generator\GeneratorColumn;
use app\common\model\generator\GeneratorDownload;
use app\common\model\generator\GeneratorTable;
use think\facade\Db;

class GeneratorLogic extends BaseLogic
{
    public static function sourceTables(array $params): array
    {
        $pageNo = max(1, (int) ($params['page_no'] ?? 1));
        $pageSize = min(100, max(1, (int) ($params['page_size'] ?? 15)));
        return GeneratorMetadataService::tables(trim((string) ($params['keyword'] ?? '')), $pageNo, $pageSize);
    }

    public static function lists(int $adminId, array $params): array
    {
        $pageNo = max(1, (int) ($params['page_no'] ?? 1));
        $pageSize = min(100, max(1, (int) ($params['page_size'] ?? 15)));
        $query = GeneratorTable::where('admin_id', $adminId);
        if (!empty($params['keyword'])) {
            $keyword = trim((string) $params['keyword']);
            $query->where(function ($sub) use ($keyword): void {
                $sub->where('table_name', 'like', '%' . $keyword . '%')
                    ->whereOr('table_comment', 'like', '%' . $keyword . '%')
                    ->whereOr('entity_name', 'like', '%' . $keyword . '%');
            });
        }
        $count = (clone $query)->count();
        $lists = $query->order('id', 'desc')->page($pageNo, $pageSize)->select()->toArray();
        return compact('lists', 'count', 'pageNo', 'pageSize');
    }

    public static function detail(int $adminId, int $id): array|false
    {
        try {
            return self::ownedTable($adminId, $id, true);
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function importTables(int $adminId, array $tableNames): bool
    {
        try {
            $tableNames = array_values(array_unique(array_map('strval', $tableNames)));
            Db::transaction(function () use ($adminId, $tableNames): void {
                foreach ($tableNames as $tableName) {
                    GeneratorMetadataService::assertIdentifier($tableName, '数据表名称');
                    if (GeneratorTable::where(['admin_id' => $adminId, 'table_name' => $tableName])->count()) {
                        throw new \RuntimeException('数据表已导入：' . $tableName);
                    }
                    $metadata = GeneratorMetadataService::table($tableName);
                    $entityName = self::entityName($tableName);
                    self::assertEntity($entityName);
                    $table = GeneratorTable::create([
                        'admin_id'      => $adminId,
                        'table_name'    => $tableName,
                        'table_comment' => (string) $metadata['table_comment'],
                        'module_name'   => 'generated',
                        'entity_name'   => $entityName,
                        'template_type' => 'crud',
                        'author'        => '',
                        'tree_config'   => [],
                        'relations'     => [],
                    ]);
                    $columns = array_map(
                        static fn(array $column): array => ['table_id' => (int) $table->id] + $column,
                        GeneratorMetadataService::columns($tableName)
                    );
                    if ($columns !== []) (new GeneratorColumn())->saveAll($columns);
                }
            });
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function sync(int $adminId, int $id): bool
    {
        try {
            Db::transaction(function () use ($adminId, $id): void {
                $table = self::ownedTableModel($adminId, $id, true);
                $metadata = GeneratorMetadataService::columns((string) $table->table_name);
                $existing = [];
                foreach (GeneratorColumn::where('table_id', $id)->select() as $row) {
                    $existing[(string) $row->column_name] = $row;
                }
                $seen = [];
                foreach ($metadata as $column) {
                    $name = (string) $column['column_name'];
                    $seen[] = $name;
                    if (isset($existing[$name])) {
                        $row = $existing[$name];
                        $row->save([
                            'column_type' => $column['column_type'],
                            'php_type' => $column['php_type'],
                            'is_required' => $column['is_required'],
                            'is_pk' => $column['is_pk'],
                            'sort' => $column['sort'],
                        ]);
                    } else {
                        GeneratorColumn::create(['table_id' => $id] + $column);
                    }
                }
                $delete = GeneratorColumn::where('table_id', $id);
                if ($seen !== []) $delete->whereNotIn('column_name', $seen);
                $delete->delete();
                $table->save(['table_comment' => (string) GeneratorMetadataService::table((string) $table->table_name)['table_comment']]);
            });
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function update(int $adminId, array $params): bool
    {
        try {
            Db::transaction(function () use ($adminId, $params): void {
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
                $relations = self::normalizeRelations(
                    $adminId,
                    $params['relations'] ?? [],
                    $columnNames
                );
                $tree = self::normalizeTree($params['tree_config'] ?? [], $columnNames, (string) $params['template_type']);

                $submittedIds = [];
                foreach ($params['columns'] as $column) {
                    $columnId = (int) ($column['id'] ?? 0);
                    if (!isset($columns[$columnId])) throw new \RuntimeException('字段不属于当前数据表');
                    $submittedIds[] = $columnId;
                    $columns[$columnId]->save([
                        'column_comment' => trim((string) ($column['column_comment'] ?? '')),
                        'is_required' => self::flag($column['is_required'] ?? 0),
                        'is_insert' => self::flag($column['is_insert'] ?? 0),
                        'is_update' => self::flag($column['is_update'] ?? 0),
                        'is_lists' => self::flag($column['is_lists'] ?? 0),
                        'is_query' => self::flag($column['is_query'] ?? 0),
                        'query_type' => self::choice((string) ($column['query_type'] ?? '='), ['=', '<>', '>', '>=', '<', '<=', 'like', 'between']),
                        'view_type' => self::choice((string) ($column['view_type'] ?? 'input'), ['input', 'textarea', 'select', 'radio', 'checkbox', 'switch', 'date', 'datetime', 'number']),
                        'dict_type' => trim((string) ($column['dict_type'] ?? '')),
                    ]);
                }
                if (count(array_unique($submittedIds)) !== count($columns)) {
                    throw new \RuntimeException('必须提交当前数据表的全部字段配置');
                }

                $table->save([
                    'table_comment' => trim((string) $params['table_comment']),
                    'module_name' => $module,
                    'entity_name' => $entity,
                    'template_type' => (string) $params['template_type'],
                    'author' => trim((string) ($params['author'] ?? '')),
                    'tree_config' => $tree,
                    'relations' => $relations,
                ]);
            });
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function delete(int $adminId, array $ids): bool
    {
        try {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            Db::transaction(function () use ($adminId, $ids): void {
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
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function preview(int $adminId, int $id): array|false
    {
        try {
            $tables = self::snapshotTables($adminId, [$id]);
            return GeneratorRenderService::render($tables[0]);
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function generate(int $adminId, array $ids): array|false
    {
        try {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            $tables = self::snapshotTables($adminId, $ids);
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
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function consumeDownload(int $adminId, string $token): array|false
    {
        try {
            return Db::transaction(function () use ($adminId, $token): array {
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
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function models(int $adminId): array
    {
        return GeneratorTable::where('admin_id', $adminId)
            ->field('id,module_name,entity_name,table_name')
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

    private static function entityName(string $tableName): string
    {
        $connection = (string) config('database.default', 'mysql');
        $prefix = (string) config('database.connections.' . $connection . '.prefix', '');
        $name = $prefix !== '' && str_starts_with($tableName, $prefix)
            ? substr($tableName, strlen($prefix)) : $tableName;
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

    private static function normalizeRelations(
        int $adminId,
        mixed $relations,
        array $columnNames
    ): array
    {
        if (!is_array($relations) || count($relations) > 20) throw new \InvalidArgumentException('关系配置格式错误');
        $result = [];
        foreach ($relations as $relation) {
            if (!is_array($relation)) throw new \InvalidArgumentException('关系配置格式错误');
            $name = (string) ($relation['name'] ?? '');
            $targetTableId = (int)($relation['target_table_id'] ?? 0);
            if ($targetTableId <= 0) throw new \InvalidArgumentException('关系目标配置无效');
            $type = self::choice((string) ($relation['type'] ?? ''), ['belongsTo', 'hasOne', 'hasMany']);
            GeneratorMetadataService::assertIdentifier($name, '关系名称');
            $local = (string) ($relation['local_key'] ?? 'id');
            $foreign = (string) ($relation['foreign_key'] ?? 'id');
            GeneratorMetadataService::assertIdentifier($local, '本地键');
            GeneratorMetadataService::assertIdentifier($foreign, '外键');
            if (!in_array($local, $columnNames, true)) {
                throw new \InvalidArgumentException('关系本地字段不存在');
            }
            $target = GeneratorTable::where(['id' => $targetTableId, 'admin_id' => $adminId])
                ->lock(true)->findOrEmpty();
            if ($target->isEmpty()) throw new \RuntimeException('关系目标配置不存在或无权访问');
            $targetColumns = GeneratorColumn::where('table_id', $targetTableId)->column('column_name');
            if (!in_array($foreign, array_map('strval', $targetColumns), true)) {
                throw new \InvalidArgumentException('关系目标字段不存在');
            }
            $result[] = [
                'target_table_id' => $targetTableId,
                'name' => $name,
                'type' => $type,
                'local_key' => $local,
                'foreign_key' => $foreign,
            ];
        }
        return $result;
    }

    /** @return array<int,array<string,mixed>> */
    private static function snapshotTables(int $adminId, array $ids): array
    {
        sort($ids);
        return Db::transaction(function () use ($adminId, $ids): array {
            $models = GeneratorTable::where('admin_id', $adminId)
                ->whereIn('id', $ids)->order('id', 'asc')->lock(true)->select();
            if ($models->count() !== count($ids)) {
                throw new \RuntimeException('生成配置不存在或无权访问');
            }
            $tables = [];
            foreach ($models as $model) {
                $table = $model->toArray();
                $table['columns'] = GeneratorColumn::where('table_id', (int)$model->id)
                    ->order('sort', 'asc')->lock(true)->select()->toArray();
                $tables[] = self::hydrateRelations($adminId, $table);
            }
            return $tables;
        });
    }

    private static function hydrateRelations(int $adminId, array $table, bool $lock = false): array
    {
        $relations = [];
        foreach ((array)($table['relations'] ?? []) as $relation) {
            $targetId = (int)($relation['target_table_id'] ?? 0);
            $query = GeneratorTable::where(['id' => $targetId, 'admin_id' => $adminId]);
            if ($lock) $query->lock(true);
            $target = $query->findOrEmpty();
            if ($target->isEmpty()) throw new \RuntimeException('关系目标配置不存在或无权访问');
            $relation['module'] = (string)$target->module_name;
            $relation['model'] = (string)$target->entity_name;
            $relations[] = $relation;
        }
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
