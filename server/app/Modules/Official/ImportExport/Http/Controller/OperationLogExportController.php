<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Http\Controller;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\ImportExport\Application\OperationLogExportApplicationService;
use app\common\dto\authorization\AdminPrincipal;
use app\common\service\audit\OperationLogTenantContext;
use think\App;

final class OperationLogExportController extends BaseAdminController
{
    public function __construct(
        App $app,
        private readonly OperationLogExportApplicationService $exports,
    ) {
        parent::__construct($app);
    }

    public function export()
    {
        try {
            $context = OperationLogTenantContext::member();
            $operation = $this->exports->submit(
                $context,
                AdminPrincipal::fromArray($this->adminInfo),
                trim((string)$this->request->header('Idempotency-Key', '')),
            );
            return $this->data($operation->toPublicArray());
        } catch (\Throwable $exception) {
            return $this->fail($this->safeError($exception));
        }
    }

    public function exportStatus()
    {
        try {
            $context = OperationLogTenantContext::member();
            $operation = $this->exports->operation(
                $context,
                AdminPrincipal::fromArray($this->adminInfo),
                (string)$this->request->get('operation_key', ''),
            );
            return $this->data($operation->toPublicArray());
        } catch (\Throwable $exception) {
            return $this->fail($this->safeError($exception));
        }
    }

    public function exportDownload()
    {
        try {
            $context = OperationLogTenantContext::member();
            $file = $this->exports->download(
                $context,
                AdminPrincipal::fromArray($this->adminInfo),
                (string)$this->request->get('file_key', ''),
            );
            return redirect($file['url']);
        } catch (\Throwable $exception) {
            return $this->fail($this->safeError($exception));
        }
    }

    private function safeError(\Throwable $exception): string
    {
        return match ($exception->getMessage()) {
            'ASYNC_SIGNING_KEY_INVALID' => '异步任务未配置',
            'ASYNC_EXPORT_PERMISSION_DENIED', 'IMPORT_EXPORT_PERMISSION_DENIED' => '暂无访问权限',
            'IMPORT_EXPORT_NOT_FOUND', 'IMPORT_EXPORT_FILE_UNAVAILABLE' => '导出结果不存在',
            'IMPORT_EXPORT_INVALID' => '导出请求无效',
            default => '导出操作失败',
        };
    }
}
