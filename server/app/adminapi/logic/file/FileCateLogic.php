<?php
declare(strict_types=1);

namespace app\adminapi\logic\file;

use app\common\enum\FileEnum;
use app\common\logic\BaseLogic;
use app\common\model\file\File;
use app\common\model\file\FileCate;

class FileCateLogic extends BaseLogic
{
    /** 某类型下的全部分类（按 id 升序） */
    public static function lists(int $type): array
    {
        return FileCate::where('type', $type)
            ->order(['id' => 'asc'])
            ->select()->toArray();
    }

    public static function add(array $params): bool
    {
        if (!FileEnum::isValidType((int)($params['type'] ?? 0))) {
            self::setError('文件类型无效');
            return false;
        }
        try {
            FileCate::create([
                'pid'  => (int)($params['pid'] ?? 0),
                'type' => (int)$params['type'],
                'name' => (string)$params['name'],
            ]);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function edit(array $params): bool
    {
        try {
            FileCate::update([
                'id'   => (int)$params['id'],
                'name' => (string)$params['name'],
            ]);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    /** 删除分类：分类下有文件时禁止删除 */
    public static function delete(int $id): bool
    {
        if (File::where('cid', $id)->count() > 0) {
            self::setError('该分类下存在文件，不可删除');
            return false;
        }
        FileCate::destroy($id);
        return true;
    }
}
