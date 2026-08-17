<?php
declare(strict_types=1);

namespace app\Modules\Fixture\DeliveryRecord\Http;

use app\Modules\Fixture\DeliveryRecord\ModuleProvider;
use app\adminapi\controller\BaseAdminController;
use app\common\service\JsonService;
use PDO;
use PeanutAdmin\Kernel\Module\ModuleException;
use think\facade\Db;

final class DeliveryRecordController extends BaseAdminController
{
    public function lists()
    {
        try {
            return $this->data($this->handler()->lists(
                $this->request->tenantContext ?? null,
                $this->adminInfo
            ));
        } catch (ModuleException $exception) {
            return $this->moduleFailure($exception);
        }
    }

    public function record()
    {
        try {
            return $this->data($this->handler()->record(
                $this->request->tenantContext ?? null,
                $this->adminInfo,
                (string)$this->request->post('reference', '')
            ));
        } catch (ModuleException $exception) {
            return $this->moduleFailure($exception);
        } catch (\InvalidArgumentException $exception) {
            return JsonService::fail($exception->getMessage(), null, 42200);
        }
    }

    private function handler(): DeliveryRecordHttpHandler
    {
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('FIXTURE_DELIVERY_RECORD_DATABASE_UNAVAILABLE');
        }
        return new DeliveryRecordHttpHandler((new ModuleProvider())->commands($pdo));
    }

    private function moduleFailure(ModuleException $exception)
    {
        $status = in_array($exception->errorCode, [
            'MODULE_NOT_INSTALLED',
            'MODULE_INSTALLATION_FAILED',
        ], true) ? 50300 : 40300;
        return JsonService::fail(
            'Delivery record Module request was rejected.',
            ['error_code' => $exception->errorCode],
            $status
        );
    }
}
