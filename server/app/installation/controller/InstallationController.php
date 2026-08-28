<?php
declare(strict_types=1);

namespace app\installation\controller;

use app\common\controller\BaseLikeAdminController;
use app\common\service\installation\InstallationExecutionException;
use app\common\service\installation\InstallationExecutionHost;
use app\common\service\JsonService;

final class InstallationController extends BaseLikeAdminController
{
    public function status()
    {
        try {
            return $this->data($this->host()->status());
        } catch (\Throwable) {
            return JsonService::fail(
                '安装状态不可用。',
                ['error_code' => 'INSTALL_STATUS_UNAVAILABLE'],
                50300,
            );
        }
    }

    public function execute()
    {
        if (!$this->sameOriginRequest()) {
            return JsonService::fail(
                '安装请求来源无效。',
                ['error_code' => 'INSTALL_REQUEST_ORIGIN_INVALID'],
                40300,
            );
        }
        $authorization = trim((string)$this->request->header('Authorization', ''));
        $token = str_starts_with($authorization, 'Bearer ')
            ? trim(substr($authorization, strlen('Bearer ')))
            : '';
        try {
            return $this->data($this->host()->executeGuided($token, $this->request->post()));
        } catch (InstallationExecutionException $exception) {
            return JsonService::fail(
                $exception->getMessage(),
                ['error_code' => $exception->errorCode],
                $exception->httpStatus * 100,
            );
        } catch (\Throwable) {
            return JsonService::fail(
                '安装执行失败。',
                ['error_code' => 'INSTALL_EXECUTION_FAILED'],
                40900,
            );
        }
    }

    private function host(): InstallationExecutionHost
    {
        return new InstallationExecutionHost(dirname(__DIR__, 3));
    }

    private function sameOriginRequest(): bool
    {
        $fetchSite = strtolower(trim((string)$this->request->header('Sec-Fetch-Site', '')));
        if ($fetchSite === 'cross-site') {
            return false;
        }
        $origin = trim((string)$this->request->header('Origin', ''));
        if ($origin === '') {
            return true;
        }
        $originHost = parse_url($origin, PHP_URL_HOST);
        $requestHost = explode(':', strtolower((string)$this->request->host()))[0];
        return is_string($originHost) && hash_equals($requestHost, strtolower($originHost));
    }
}
