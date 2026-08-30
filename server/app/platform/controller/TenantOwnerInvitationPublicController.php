<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\common\controller\BaseLikeAdminController;
use app\common\service\JsonService;
use app\platform\invitation\PlatformInvitationRuntimeFactory;
use app\platform\invitation\TenantOwnerInvitationException;
use app\platform\validate\TenantOwnerInvitationValidate;

final class TenantOwnerInvitationPublicController extends BaseLikeAdminController
{
    public function inspect()
    {
        $params = $this->request->get();
        $this->validate($params, TenantOwnerInvitationValidate::class . '.inspect');
        try {
            return $this->data(PlatformInvitationRuntimeFactory::publicInvitations()->inspect(
                (string)$params['token']
            ));
        } catch (TenantOwnerInvitationException $exception) {
            return $this->failure($exception);
        }
    }

    public function accept()
    {
        $params = $this->request->post();
        $this->validate($params, TenantOwnerInvitationValidate::class . '.accept');
        try {
            return $this->data(PlatformInvitationRuntimeFactory::publicInvitations()->accept(
                (string)$params['token'],
                isset($params['new_account_password']) && (string)$params['new_account_password'] !== ''
                    ? (string)$params['new_account_password']
                    : null
            ));
        } catch (TenantOwnerInvitationException $exception) {
            return $this->failure($exception);
        }
    }

    private function failure(TenantOwnerInvitationException $exception)
    {
        throw \app\common\http\ApiProblem::fromEnvelope(
            $exception->getMessage(),
            ['error_code' => $exception->errorCode],
            $exception->httpStatus * 100
        );
    }
}
