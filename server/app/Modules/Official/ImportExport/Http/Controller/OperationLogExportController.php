<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Http\Controller;

use app\adminapi\controller\BaseAdminController;
use app\common\dto\authorization\AdminPrincipal;
use app\common\service\audit\OperationLogTenantContext;
use app\common\service\authorization\AdminAuthorizationService;
use app\common\service\async\TaskImportExportRuntimeFactory;
use app\Modules\Official\ImportExport\Application\TaskImportExportRuntime;
use app\Modules\Official\ImportExport\Contracts\Dto\CsvExportOperation;
use PDO;
use think\facade\Db;

final class OperationLogExportController extends BaseAdminController
{
    public function export()
    {
        try {
            $context = OperationLogTenantContext::member($this->request);
            $authorized = (new AdminAuthorizationService())->authorizedAsyncExport(
                $context,
                AdminPrincipal::fromArray($this->adminInfo),
            );
            $idempotencyKey = trim((string)$this->request->header('Idempotency-Key', ''));
            $operation = $this->runtime()->commands()->submitCsvExport(
                $authorized,
                CsvExportOperation::operationLog($idempotencyKey),
            );
            return $this->data($operation->toPublicArray());
        } catch (\Throwable $exception) {
            return $this->fail($this->safeError($exception));
        }
    }

    public function exportStatus()
    {
        try {
            $context = OperationLogTenantContext::member($this->request);
            $authorized = (new AdminAuthorizationService())->authorizedAsyncExport(
                $context,
                AdminPrincipal::fromArray($this->adminInfo),
            );
            $operation = $this->runtime()->queries()->operation(
                $authorized,
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
            $context = OperationLogTenantContext::member($this->request);
            $authorized = (new AdminAuthorizationService())->authorizedAsyncExport(
                $context,
                AdminPrincipal::fromArray($this->adminInfo),
            );
            $file = $this->runtime()->download(
                $authorized,
                (string)$this->request->get('file_key', ''),
            );
            return redirect($file['url']);
        } catch (\Throwable $exception) {
            return $this->fail($this->safeError($exception));
        }
    }

    private function runtime(): TaskImportExportRuntime
    {
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('ASYNC_DATABASE_UNAVAILABLE');
        }
        return TaskImportExportRuntimeFactory::fromConfig($pdo);
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
