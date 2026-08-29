<?php
declare(strict_types=1);

namespace app\Modules\Fixture\DeliveryRecord\Http;

use app\adminapi\controller\BaseAdminController;
use app\common\execution\CurrentExecutionContext;
use app\common\http\ApiProblem;
use PeanutAdmin\Kernel\Module\ModuleException;
use think\App;

final class DeliveryRecordController extends BaseAdminController
{
    public function __construct(
        App $app,
        CurrentExecutionContext $executionContext,
        private readonly DeliveryRecordHttpHandler $handler,
    )
    {
        parent::__construct($app, $executionContext);
    }

    public function lists()
    {
        try {
            return $this->handler->lists();
        } catch (ModuleException $exception) {
            $this->moduleFailure($exception);
        }
    }

    public function record()
    {
        try {
            return $this->handler->record((string)$this->request->post('reference', ''));
        } catch (ModuleException $exception) {
            $this->moduleFailure($exception);
        } catch (\InvalidArgumentException $exception) {
            throw new ApiProblem('DELIVERY_RECORD_INPUT_INVALID', 422, $exception->getMessage());
        }
    }

    private function moduleFailure(ModuleException $exception): never
    {
        $httpStatus = in_array($exception->errorCode, [
            'MODULE_NOT_INSTALLED',
            'MODULE_INSTALLATION_FAILED',
        ], true) ? 503 : 403;
        throw new ApiProblem(
            $exception->errorCode,
            $httpStatus,
            'Delivery record Module request was rejected.',
        );
    }
}
