<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\common\service\JsonService;
use app\platform\invitation\PlatformInvitationRuntimeFactory;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use PeanutAdmin\Kernel\Authorization\Application\PageRequest;

final class PlatformControlPlaneQueryController extends BasePlatformController
{
    public function operators()
    {
        return $this->listQuery('operators');
    }

    public function roles()
    {
        return $this->listQuery('roles');
    }

    public function permissions()
    {
        return $this->listQuery('permissions');
    }

    public function audit()
    {
        return $this->listQuery('audit');
    }

    public function moduleStates()
    {
        return $this->listQuery('moduleStates');
    }

    public function owner()
    {
        if ($this->platformContext === null) {
            return JsonService::fail('Platform authentication is required.', null, 40100);
        }
        try {
            return $this->data(PlatformInvitationRuntimeFactory::queries()->owner(
                $this->platformContext,
                $this->positiveInteger($this->request->get('tenant_id'))
            ));
        } catch (AdminAccessException $exception) {
            return $this->failure($exception);
        }
    }

    private function listQuery(string $method)
    {
        if ($this->platformContext === null) {
            return JsonService::fail('Platform authentication is required.', null, 40100);
        }
        try {
            $page = $this->positiveInteger($this->request->get('page', 1));
            $pageSize = $this->positiveInteger($this->request->get('page_size', 20));
            if ($pageSize > 100) {
                throw AdminAccessException::invalid('PAGE_SIZE_INVALID', 'Page size must be at most 100.');
            }
            $request = new PageRequest($page, $pageSize);
            $query = PlatformInvitationRuntimeFactory::queries();
            $result = $method === 'moduleStates'
                ? $query->moduleStates($this->platformContext, $this->positiveInteger($this->request->get('tenant_id')), $request)
                : $query->{$method}($this->platformContext, $request);
            return $this->dataLists($result['items'], $result['total'], $page, $pageSize);
        } catch (AdminAccessException $exception) {
            return $this->failure($exception);
        }
    }

    private function positiveInteger(mixed $value): int
    {
        $candidate = is_int($value) ? (string)$value : trim((string)$value);
        if (preg_match('/^[1-9][0-9]*$/D', $candidate) !== 1 || filter_var($candidate, FILTER_VALIDATE_INT) === false) {
            throw AdminAccessException::invalid('ID_INVALID', 'A positive integer is required.');
        }
        return (int)$candidate;
    }

    private function failure(AdminAccessException $exception)
    {
        return JsonService::fail(
            $exception->getMessage(),
            ['error_code' => $exception->errorCode],
            $exception->httpStatus * 100
        );
    }
}
