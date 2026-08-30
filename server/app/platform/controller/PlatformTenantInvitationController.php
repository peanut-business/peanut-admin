<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\common\http\PageResult;
use app\common\service\JsonService;
use app\platform\http\PlatformRequest;
use app\platform\invitation\PlatformInvitationRuntimeFactory;
use app\platform\invitation\TenantOwnerInvitationException;
use app\platform\validate\TenantOwnerInvitationValidate;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use PeanutAdmin\Kernel\Authorization\Application\PageRequest;

final class PlatformTenantInvitationController extends BasePlatformController
{
    public function provision()
    {
        return $this->mutate('provision', function (array $params): array {
            return PlatformInvitationRuntimeFactory::invitations()->provision(
                $this->platformContext,
                trim((string)$params['tenant_code']),
                trim((string)$params['tenant_name']),
                trim((string)$params['owner_email']),
                trim((string)$params['owner_display_name']),
                (int)($params['expires_in_hours'] ?? 72)
            );
        });
    }

    public function invite()
    {
        return $this->mutate('invite', function (array $params): array {
            return PlatformInvitationRuntimeFactory::invitations()->invite(
                $this->platformContext,
                (int)$params['tenant_id'],
                trim((string)$params['owner_email']),
                trim((string)$params['owner_display_name']),
                (int)($params['expires_in_hours'] ?? 72)
            );
        });
    }

    public function lists()
    {
        if ($this->platformContext === null) {
            throw \app\common\http\ApiProblem::fromEnvelope('Platform authentication is required.', null, 40100);
        }
        $params = $this->request->get();
        $this->validate($params, TenantOwnerInvitationValidate::class . '.lists');
        try {
            $page = (int)($params['page'] ?? 1);
            $pageSize = (int)($params['page_size'] ?? 20);
            $result = PlatformInvitationRuntimeFactory::invitations()->invitations(
                $this->platformContext,
                (int)$params['tenant_id'],
                new PageRequest($page, $pageSize)
            );
            return $this->dataLists(new PageResult($result['items'], $result['total'], $page, $pageSize));
        } catch (AdminAccessException|TenantOwnerInvitationException $exception) {
            return $this->failure($exception);
        }
    }

    public function resend()
    {
        return $this->mutate('resend', function (array $params): array {
            return PlatformInvitationRuntimeFactory::invitations()->resend(
                $this->platformContext,
                (int)$params['invitation_id'],
                (int)($params['expires_in_hours'] ?? 72)
            );
        });
    }

    public function revoke()
    {
        return $this->mutate('revoke', function (array $params): array {
            return PlatformInvitationRuntimeFactory::invitations()->revoke(
                $this->platformContext,
                (int)$params['invitation_id']
            );
        });
    }

    private function mutate(string $scene, callable $operation)
    {
        if ($this->platformContext === null) {
            throw \app\common\http\ApiProblem::fromEnvelope('Platform authentication is required.', null, 40100);
        }
        $params = $this->request->post();
        $this->validate($params, TenantOwnerInvitationValidate::class . '.' . $scene);
        try {
            return $this->data($operation($params));
        } catch (AdminAccessException|TenantOwnerInvitationException $exception) {
            return $this->failure($exception);
        } catch (\InvalidArgumentException $exception) {
            throw \app\common\http\ApiProblem::fromEnvelope($exception->getMessage(), ['error_code' => 'INVITATION_INPUT_INVALID'], 42200);
        }
    }

    private function failure(AdminAccessException|TenantOwnerInvitationException $exception)
    {
        throw \app\common\http\ApiProblem::fromEnvelope(
            $exception->getMessage(),
            ['error_code' => $exception->errorCode],
            $exception->httpStatus * 100
        );
    }
}
