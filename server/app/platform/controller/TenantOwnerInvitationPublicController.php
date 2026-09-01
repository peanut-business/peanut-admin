<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\common\controller\BaseLikeAdminController;
use app\platform\invitation\TenantOwnerInvitationPublicService;
use app\platform\validate\TenantOwnerInvitationValidate;
use think\App;

final class TenantOwnerInvitationPublicController extends BaseLikeAdminController
{
    public function __construct(
        App $app,
        private readonly TenantOwnerInvitationPublicService $invitations,
    ) {
        parent::__construct($app);
    }

    public function inspect()
    {
        $params = $this->request->get();
        $this->validate($params, TenantOwnerInvitationValidate::class . '.inspect');
        return $this->data($this->invitations->inspect(
            (string)$params['token']
        ));
    }

    public function accept()
    {
        $params = $this->request->post();
        $this->validate($params, TenantOwnerInvitationValidate::class . '.accept');
        return $this->data($this->invitations->accept(
            (string)$params['token'],
            isset($params['new_account_password']) && (string)$params['new_account_password'] !== ''
                ? (string)$params['new_account_password']
                : null
        ));
    }
}
