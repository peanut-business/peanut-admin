<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Application;

use app\common\application\BusinessException;
use app\Modules\Official\Member\Contracts\MemberTagCommands;
use app\Modules\Official\Member\Infrastructure\Persistence\MemberTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class MemberTagContractService implements MemberTagCommands
{
    public function create(TenantContext $context, string $name, string $remark): void
    {
        if (MemberTenantRepository::tags($context)->where('name', $name)->count() > 0) {
            throw BusinessException::conflict('MEMBER_TAG_NAME_EXISTS', '标签名称已存在');
        }
        MemberTenantRepository::createTag($context, ['name' => $name, 'remark' => $remark]);
    }

    public function update(TenantContext $context, int $tagId, string $name, ?string $remark): void
    {
        if (MemberTenantRepository::tags($context)->where('name', $name)->where('id', '<>', $tagId)->count() > 0) {
            throw BusinessException::conflict('MEMBER_TAG_NAME_EXISTS', '标签名称已存在');
        }
        $tag = MemberTenantRepository::tags($context)->where('id', $tagId)->findOrEmpty();
        if ($tag->isEmpty()) {
            throw BusinessException::notFound('MEMBER_TAG_NOT_FOUND', '标签不存在');
        }
        $data = ['name' => $name];
        if ($remark !== null) {
            $data['remark'] = $remark;
        }
        $tag->save($data);
    }

    public function delete(TenantContext $context, int $tagId): void
    {
        $tag = MemberTenantRepository::tags($context)->where('id', $tagId)->findOrEmpty();
        if ($tag->isEmpty()) {
            throw BusinessException::notFound('MEMBER_TAG_NOT_FOUND', '标签不存在');
        }
        MemberTenantRepository::relations($context)->where('tag_id', $tagId)->delete();
        $tag->delete();
    }
}
