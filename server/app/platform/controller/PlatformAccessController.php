<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\common\service\JsonService;
use app\platform\service\PlatformRuntimeFactory;
use app\platform\validate\PlatformAccessValidate;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use PeanutAdmin\Kernel\Platform\PlatformOperatorStatus;

final class PlatformAccessController extends BasePlatformController
{
    public function createOperator()
    {
        return $this->mutate('createOperator', static function (array $params, object $context): array {
            return PlatformRuntimeFactory::platformAccess()->createOperator(
                $context->operatorId,
                $context->accountId,
                trim((string)$params['email']),
                trim((string)$params['display_name']),
                isset($params['initial_password']) && (string)$params['initial_password'] !== ''
                    ? (string)$params['initial_password']
                    : null,
                $context->requestId
            );
        });
    }

    public function updateOperator()
    {
        return $this->mutate('updateOperator', static function (array $params, object $context): array {
            return PlatformRuntimeFactory::platformAccess()->updateOperator(
                $context->operatorId,
                $context->accountId,
                (int)$params['operator_id'],
                (int)$params['expected_revision'],
                trim((string)$params['display_name']),
                trim((string)$params['change_reason']),
                $context->requestId
            );
        });
    }

    public function replaceOperatorRoles()
    {
        return $this->mutate('replaceOperatorRoles', static function (array $params, object $context): array {
            return PlatformRuntimeFactory::platformAccess()->replaceOperatorRoles(
                $context->operatorId,
                $context->accountId,
                (int)$params['operator_id'],
                array_values(array_map(static fn(mixed $id): int => (int)$id, $params['role_ids'])),
                (int)$params['expected_revision'],
                trim((string)$params['change_reason']),
                $context->requestId
            );
        });
    }

    public function activateOperator()
    {
        return $this->transitionOperator(PlatformOperatorStatus::Active, 'activateOperator');
    }

    public function suspendOperator()
    {
        return $this->transitionOperator(PlatformOperatorStatus::Suspended, 'suspendOperator');
    }

    public function closeOperator()
    {
        return $this->transitionOperator(PlatformOperatorStatus::Closed, 'closeOperator');
    }

    public function createRole()
    {
        return $this->mutate('createRole', static function (array $params, object $context): array {
            return PlatformRuntimeFactory::platformAccess()->createRole(
                $context->operatorId,
                $context->accountId,
                trim((string)$params['key']),
                trim((string)$params['name']),
                isset($params['description']) && trim((string)$params['description']) !== ''
                    ? trim((string)$params['description'])
                    : null,
                $context->requestId
            );
        });
    }

    public function updateRole()
    {
        return $this->mutate('updateRole', static function (array $params, object $context): array {
            return PlatformRuntimeFactory::platformAccess()->updateRole(
                $context->operatorId,
                $context->accountId,
                (int)$params['role_id'],
                (int)$params['expected_revision'],
                trim((string)$params['name']),
                isset($params['description']) && trim((string)$params['description']) !== ''
                    ? trim((string)$params['description'])
                    : null,
                trim((string)$params['change_reason']),
                $context->requestId
            );
        });
    }

    public function archiveRole()
    {
        return $this->mutate('archiveRole', static function (array $params, object $context): array {
            return PlatformRuntimeFactory::platformAccess()->archiveRole(
                $context->operatorId,
                $context->accountId,
                (int)$params['role_id'],
                (int)$params['expected_revision'],
                trim((string)$params['change_reason']),
                $context->requestId
            );
        });
    }

    public function replaceRolePermissions()
    {
        return $this->mutate('replaceRolePermissions', static function (array $params, object $context): array {
            return PlatformRuntimeFactory::platformAccess()->replaceRolePermissions(
                $context->operatorId,
                $context->accountId,
                (int)$params['role_id'],
                array_values(array_map(static fn(mixed $key): string => trim((string)$key), $params['permission_keys'])),
                (int)$params['expected_revision'],
                trim((string)$params['change_reason']),
                $context->requestId
            );
        });
    }

    private function transitionOperator(PlatformOperatorStatus $status, string $scene)
    {
        return $this->mutate($scene, static function (array $params, object $context) use ($status): array {
            return PlatformRuntimeFactory::platformAccess()->transitionOperator(
                $context->operatorId,
                $context->accountId,
                (int)$params['operator_id'],
                (int)$params['expected_revision'],
                $status,
                trim((string)$params['change_reason']),
                $context->requestId
            );
        });
    }

    private function mutate(string $scene, callable $operation)
    {
        if ($this->platformContext === null) {
            return JsonService::fail('Platform authentication is required.', null, 40100);
        }

        $params = $this->request->post();
        $this->validate($params, PlatformAccessValidate::class . '.' . $scene);
        try {
            return $this->data($operation($params, $this->platformContext->core));
        } catch (AdminAccessException $exception) {
            return JsonService::fail(
                $exception->getMessage(),
                ['error_code' => $exception->errorCode],
                $exception->httpStatus * 100
            );
        }
    }
}
