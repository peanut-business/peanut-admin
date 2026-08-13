<?php
declare(strict_types=1);

namespace app\adminapi\logic\member;

use app\common\logic\BaseLogic;
use app\common\model\member\MemberTag;
use app\common\service\member\MemberTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;

class MemberTagLogic extends BaseLogic
{
    public static function lists(TenantContext $context): array
    {
        return MemberTenantRepository::tags($context)->order('id', 'desc')->select()->toArray();
    }

    public static function add(TenantContext $context, array $params): bool
    {
        if (MemberTenantRepository::tags($context)->where('name', $params['name'])->count() > 0) {
            self::setError('标签名称已存在');
            return false;
        }
        MemberTenantRepository::createTag($context, ['name' => $params['name'], 'remark' => $params['remark'] ?? '']);
        return true;
    }

    public static function edit(TenantContext $context, array $params): bool
    {
        if (MemberTenantRepository::tags($context)->where('name', $params['name'])->where('id', '<>', $params['id'])->count() > 0) {
            self::setError('标签名称已存在');
            return false;
        }
        $tag = MemberTenantRepository::tags($context)->where('id', (int)$params['id'])->findOrEmpty();
        if ($tag->isEmpty()) {
            self::setError('标签不存在');
            return false;
        }
        $data = [];
        if (isset($params['name']))   $data['name']   = $params['name'];
        if (isset($params['remark'])) $data['remark'] = $params['remark'];
        $tag->save($data);
        return true;
    }

    public static function delete(TenantContext $context, int $id): bool
    {
        $tag = MemberTenantRepository::tags($context)->where('id', $id)->findOrEmpty();
        if ($tag->isEmpty()) {
            self::setError('标签不存在');
            return false;
        }
        MemberTenantRepository::relations($context)->where('tag_id', $id)->delete();
        $tag->delete();
        return true;
    }
}
