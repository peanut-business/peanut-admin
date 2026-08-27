<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\common\service\JsonService;
use app\platform\http\PlatformRequest;
use app\platform\service\ops\PlatformOpsRuntimeFactory;
use PDO;
use PeanutAdmin\OpsConsole\Application\OpsConsoleException;
use think\facade\Db;
use think\response\Json;
use Throwable;

/** Platform-only, read-only PC20 Ops Console Host. */
final class PlatformOpsController extends BasePlatformController
{
    public function status(): Json
    {
        return $this->run(fn(PDO $pdo): array => PlatformOpsRuntimeFactory::status($pdo)
            ->read($this->context())
            ->toPublicArray());
    }

    public function maintenance(): Json
    {
        return $this->run(fn(PDO $pdo): ?array => PlatformOpsRuntimeFactory::maintenance($pdo)
            ->current($this->context())
            ?->toPublicArray());
    }

    private function run(callable $operation): Json
    {
        try {
            $pdo = Db::connect()->connect();
            if (!$pdo instanceof PDO) {
                throw OpsConsoleException::statusUnavailable();
            }
            $response = JsonService::data($operation($pdo));
        } catch (OpsConsoleException $exception) {
            $response = JsonService::fail(
                'Operations status is unavailable.',
                ['error_code' => $exception->problemCode],
                $exception->status * 100
            );
        } catch (Throwable) {
            $response = JsonService::fail(
                'Operations status is unavailable.',
                ['error_code' => 'OPS_STATUS_UNAVAILABLE'],
                50300
            );
        }

        return $response->header([
            'Cache-Control' => 'no-store',
            'X-Request-Id' => PlatformRequest::requestId($this->request),
        ]);
    }

    private function context(): \PeanutAdmin\Kernel\Context\PlatformContext
    {
        if ($this->platformContext === null) {
            throw OpsConsoleException::denied();
        }
        return $this->platformContext->core;
    }
}
