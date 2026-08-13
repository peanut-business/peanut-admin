<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\common\service\JsonService;
use app\platform\service\PlatformRuntimeFactory;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use PeanutAdmin\Kernel\Authorization\Application\PageRequest;

final class PlatformTenantController extends BasePlatformController
{
    public function lists()
    {
        if ($this->platformContext === null) {
            return JsonService::fail('Platform authentication is required.', null, 40100);
        }

        try {
            $page = $this->positiveInteger($this->request->get('page', 1), 'PAGE_INVALID');
            $pageSize = $this->positiveInteger($this->request->get('page_size', 20), 'PAGE_SIZE_INVALID');
            $result = PlatformRuntimeFactory::tenantQueries()->tenants(
                $this->platformContext,
                new PageRequest($page, $pageSize)
            );
        } catch (AdminAccessException $exception) {
            return $this->accessFailure($exception);
        }

        return $this->dataLists($result['items'], $result['total'], $page, $pageSize);
    }

    public function detail()
    {
        if ($this->platformContext === null) {
            return JsonService::fail('Platform authentication is required.', null, 40100);
        }

        try {
            $tenantId = $this->positiveInteger($this->request->get('id'), 'TENANT_ID_INVALID');
            return $this->data(PlatformRuntimeFactory::tenantQueries()->tenant(
                $this->platformContext,
                $tenantId
            ));
        } catch (AdminAccessException $exception) {
            return $this->accessFailure($exception);
        }
    }

    private function positiveInteger(mixed $value, string $errorCode): int
    {
        $candidate = is_int($value) ? (string)$value : trim((string)$value);
        if (preg_match('/^[1-9][0-9]*$/D', $candidate) !== 1) {
            throw AdminAccessException::invalid($errorCode, 'A positive integer is required.');
        }
        if (filter_var($candidate, FILTER_VALIDATE_INT) === false) {
            throw AdminAccessException::invalid($errorCode, 'A positive integer is required.');
        }
        return (int)$candidate;
    }

    private function accessFailure(AdminAccessException $exception)
    {
        return JsonService::fail(
            $exception->getMessage(),
            ['error_code' => $exception->errorCode],
            $exception->httpStatus * 100
        );
    }
}
