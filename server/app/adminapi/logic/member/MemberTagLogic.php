<?php
declare(strict_types=1);

namespace app\adminapi\logic\member;

use app\common\logic\BaseLogic;
use app\common\model\member\MemberTag;

class MemberTagLogic extends BaseLogic
{
    public static function lists(): array
    {
        return MemberTag::order('id', 'desc')->select()->toArray();
    }

    public static function add(array $params): bool
    {
        if (MemberTag::where('name', $params['name'])->count() > 0) {
            self::setError('标签名称已存在');
            return false;
        }
        MemberTag::create(['name' => $params['name'], 'remark' => $params['remark'] ?? '']);
        return true;
    }

    public static function edit(array $params): bool
    {
        if (MemberTag::where('name', $params['name'])->where('id', '<>', $params['id'])->count() > 0) {
            self::setError('标签名称已存在');
            return false;
        }
        $data = ['id' => $params['id']];
        if (isset($params['name']))   $data['name']   = $params['name'];
        if (isset($params['remark'])) $data['remark'] = $params['remark'];
        MemberTag::update($data);
        return true;
    }

    public static function delete(int $id): bool
    {
        MemberTag::destroy($id);
        return true;
    }
}
