<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Http\Controller;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\ImportExport\Application\OperationLogExportApplicationService;
use app\common\dto\authorization\AdminPrincipal;
use app\common\execution\CurrentExecutionContext;
use app\common\service\audit\OperationLogTenantContext;
use think\App;

final class OperationLogExportController extends BaseAdminController
{
    public function __construct(
        App $app,
        CurrentExecutionContext $executionContext,
        private readonly OperationLogExportApplicationService $exports,
    ) {
        parent::__construct($app, $executionContext);
    }

    public function export()
    {
        $context = OperationLogTenantContext::member();
            $operation = $this->exports->submit(
                $context,
                AdminPrincipal::fromArray($this->adminInfo),
                trim((string)$this->request->header('Idempotency-Key', '')),
            );
        return $this->data($operation->toPublicArray());
    }

    public function exportStatus()
    {
        $context = OperationLogTenantContext::member();
            $operation = $this->exports->operation(
                $context,
                AdminPrincipal::fromArray($this->adminInfo),
                (string)$this->request->get('operation_key', ''),
            );
        return $this->data($operation->toPublicArray());
    }

    public function exportDownload()
    {
        $context = OperationLogTenantContext::member();
            $file = $this->exports->download(
                $context,
                AdminPrincipal::fromArray($this->adminInfo),
                (string)$this->request->get('file_key', ''),
            );
        return redirect($file['url']);
    }
}
