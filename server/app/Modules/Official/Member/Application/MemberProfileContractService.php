<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Application;

use app\common\application\BusinessException;
use app\Modules\Official\Member\Contracts\MemberProfileCommands;
use app\Modules\Official\Member\Model\Member;
use app\common\service\member\AuthenticatedMemberContext;
use app\common\service\member\MemberTenantRepository;
use app\common\support\PositiveIds;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class MemberProfileContractService implements MemberProfileCommands
{
    public function createAdminMember(TenantContext $context, array $profile, array $tagIds): void
    {
        $member = MemberTenantRepository::createMember($context, [
            'sn' => Member::generateSn($context),
            'nickname' => (string)$profile['nickname'],
            'avatar' => (string)($profile['avatar'] ?? ''),
            'mobile' => (string)($profile['mobile'] ?? ''),
            'email' => (string)($profile['email'] ?? ''),
            'sex' => (int)($profile['sex'] ?? 0),
            'birthday' => $profile['birthday'] ?? null,
            'status' => (int)($profile['status'] ?? 1),
        ]);
        $this->replaceTags($context, (int)$member->id, $tagIds);
    }

    public function updateAdminMember(TenantContext $context, int $memberId, array $profile, ?array $tagIds): void
    {
        $member = $this->member($context, $memberId);
        $data = [];
        foreach (['nickname', 'avatar', 'mobile', 'email', 'birthday'] as $field) {
            if (array_key_exists($field, $profile)) {
                $data[$field] = $profile[$field];
            }
        }
        foreach (['sex', 'status'] as $field) {
            if (array_key_exists($field, $profile)) {
                $data[$field] = (int)$profile[$field];
            }
        }
        if ($data !== []) {
            $member->save($data);
        }
        if ($tagIds !== null) {
            $this->replaceTags($context, $memberId, $tagIds);
        }
    }

    public function updateAdminField(TenantContext $context, int $memberId, string $field, mixed $value): void
    {
        if (MemberTenantRepository::members($context)->where('id', $memberId)->update([$field => $value]) !== 1) {
            throw BusinessException::notFound('MEMBER_NOT_FOUND', '用户不存在');
        }
    }

    public function updateStatus(TenantContext $context, int $memberId, int $status): void
    {
        if (MemberTenantRepository::members($context)->where('id', $memberId)->update(['status' => $status]) !== 1) {
            throw BusinessException::notFound('MEMBER_NOT_FOUND', '用户不存在');
        }
    }

    public function updateSelfField(AuthenticatedMemberContext|TenantContext $context, int $memberId, string $field, mixed $value): void
    {
        if (MemberTenantRepository::members($context)->where('id', $memberId)->update([$field => $value]) !== 1) {
            throw BusinessException::notFound('MEMBER_NOT_FOUND', '用户不存在');
        }
    }

    public function completeOAuthProfile(
        TenantContext|TenantSystemContext $context,
        int $memberId,
        ?string $nickname,
        ?string $avatar,
        int $loginTime,
        string $loginIp,
    ): void {
        $member = $this->member($context, $memberId);
        if ($nickname !== null) {
            $member->nickname = $nickname;
        }
        if ($avatar !== null) {
            $member->avatar = $avatar;
        }
        $member->is_new_user = 0;
        $member->login_time = $loginTime;
        $member->login_ip = $loginIp;
        $member->save();
    }

    public function fillOAuthProfile(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context, int $memberId, string $nickname, string $avatar): void
    {
        $member = $this->member($context, $memberId);
        $data = [];
        if ($nickname !== '' && trim((string)$member->nickname) === '') {
            $data['nickname'] = mb_substr($nickname, 0, 50);
        }
        if ($avatar !== '' && trim((string)$member->avatar) === '') {
            $data['avatar'] = $avatar;
        }
        if ($data !== []) {
            $member->save($data);
        }
    }

    private function replaceTags(TenantContext $context, int $memberId, array $tagIds): void
    {
        $member = $this->member($context, $memberId);
        $tagIds = PositiveIds::normalize($tagIds, [PositiveIds::REJECT_INVALID], '包含不存在的会员标签');
        if ($tagIds !== [] && MemberTenantRepository::tags($context)->whereIn('id', $tagIds)->count() !== count($tagIds)) {
            throw BusinessException::invalid('MEMBER_TAG_SELECTION_INVALID', '包含不存在的会员标签');
        }
        MemberTenantRepository::relations($context)->where('member_id', $memberId)->delete();
        if ($tagIds !== []) {
            MemberTenantRepository::createTagRelations($context, (int)$member->id, $tagIds);
        }
    }

    private function member(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context, int $memberId): Member
    {
        $member = MemberTenantRepository::members($context)->where('id', $memberId)->findOrEmpty();
        if ($member->isEmpty()) {
            throw BusinessException::notFound('MEMBER_NOT_FOUND', '用户不存在');
        }
        return $member;
    }
}
