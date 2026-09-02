<?php
declare(strict_types=1);

namespace app\api\application;

use app\Modules\Official\Notification\Contracts\VerificationCodeCommands;
use app\Modules\Official\Article\Contracts\PublicArticleQueries;
use app\Modules\Official\Member\Contracts\MemberIdentityCommands;
use app\Modules\Official\Member\Contracts\MemberProfileCommands;
use app\Modules\Official\Member\Contracts\MemberQueries;
use app\common\application\BusinessException;
use app\common\enum\notice\NoticeSceneEnum;
use PeanutAdmin\Kernel\Context\AuthenticatedMemberContext;
use app\common\service\FileService;
use PeanutAdmin\Kernel\Module\ModuleException;

class UserApplicationService
{
    public function __construct(
        private readonly MemberQueries $members,
        private readonly MemberIdentityCommands $memberIdentities,
        private readonly MemberProfileCommands $memberProfiles,
        private readonly VerificationCodeCommands $verificationCodes,
        private readonly PublicArticleQueries $articleCollections,
        private readonly FileService $files,
    ) {
    }

    /** 用户中心（首屏数据） */
    public function center(AuthenticatedMemberContext $context, int $memberId): array
    {
        $data = $this->members->memberFields(
            $context,
            $memberId,
            ['id', 'sn', 'nickname', 'avatar', 'mobile', 'user_money', 'points', 'create_time'],
        );
        if ($data === []) {
            return [];
        }
        $data['balance']    = $data['user_money'];
        unset($data['user_money']);
        $data['avatar']     = $this->files->getFileUrl((string) $data['avatar']);
        try {
            $data['collect_num'] = $this->articleCollections->countForMember($context, $memberId);
        } catch (ModuleException) {
            $data['collect_num'] = 0;
        }

        return $data;
    }

    /** 个人信息 */
    public function info(AuthenticatedMemberContext $context, int $memberId): array
    {
        $data = $this->members->memberFields(
            $context,
            $memberId,
            ['id', 'sn', 'account', 'nickname', 'avatar', 'sex', 'birthday', 'mobile', 'email', 'user_money', 'points', 'create_time', 'password'],
        );
        if ($data === []) {
            return [];
        }
        $data['balance'] = $data['user_money'];
        unset($data['user_money']);
        $data['avatar'] = $this->files->getFileUrl((string) $data['avatar']);
        $data['has_password'] = $data['password'] !== '' && $data['password'] !== null;
        unset($data['password']);

        return $data;
    }

    /**
     * 更新用户信息
     * params: field(nickname|avatar|sex|birthday|email), value
     */
    public function setInfo(AuthenticatedMemberContext $context, int $memberId, array $params): bool
    {
        $allowed = ['nickname', 'avatar', 'sex', 'birthday', 'email'];
            $field   = $params['field'] ?? '';

            if (!in_array($field, $allowed, true)) {
                throw BusinessException::invalid('MEMBER_PROFILE_FIELD_UNSUPPORTED', '不支持修改该字段');
            }

            $value = $params['value'];
            if ($field === 'avatar') {
                $value = $this->files->setTenantFileUrl($context, (string) $value);
            }

            $this->memberProfiles->updateSelfField($context, $memberId, $field, $value);
        return true;
    }

    /** 修改密码（需要旧密码） */
    public function changePassword(AuthenticatedMemberContext $context, int $memberId, array $params): bool
    {
        $this->memberIdentities->changePassword(
                $context,
                $memberId,
                (string)$params['old_password'],
                (string)$params['password'],
            );
        return true;
    }

    /** 绑定手机号 */
    public function bindMobile(AuthenticatedMemberContext $context, int $memberId, array $params): bool
    {
        $mobile = $params['mobile'] ?? '';
            if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
                throw BusinessException::invalid('MEMBER_MOBILE_INVALID', '手机号格式错误');
            }
            $member = $this->members->memberFields(
                $context,
                $memberId,
                ['id', 'mobile'],
            );
            if ($member === []) {
                throw BusinessException::notFound('MEMBER_NOT_FOUND', '用户不存在');
            }
            $this->memberIdentities->assertMobileAvailable($context, $memberId, $mobile);
            $scene = empty($member['mobile'])
                ? NoticeSceneEnum::BIND_MOBILE
                : NoticeSceneEnum::CHANGE_MOBILE;
            $result = $this->verificationCodes->verifyCode(
                $context,
                $scene,
                $mobile,
                (string) ($params['code'] ?? ''),
            );
            if (!$result->accepted) {
                throw BusinessException::invalid('MEMBER_VERIFICATION_REJECTED', $result->error);
            }
            $this->memberIdentities->bindVerifiedMobile($context, $memberId, $mobile);
        return true;
    }
}
