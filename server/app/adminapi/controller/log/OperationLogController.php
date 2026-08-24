<?php
declare(strict_types=1);

namespace app\adminapi\controller\log;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\log\OperationLogLogic;
use app\common\dto\authorization\AdminPrincipal;
use app\common\service\authorization\AdminAuthorizationService;
use app\common\service\async\TaskImportExportRuntime;
use app\common\service\audit\OperationLogTenantContext;
use app\common\service\module\ModuleExecutionContext;
use app\platform\service\module\PdoModuleGovernanceProvider;
use PDO;
use think\facade\Db;

class OperationLogController extends BaseAdminController
{
    public function lists()
    {
        try {
            if ((int)$this->request->get('export', 0) > 0) {
                $this->assertExportModule();
            }
            $res = OperationLogLogic::lists(
                OperationLogTenantContext::member($this->request),
                $this->request->get()
            );
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
        if (isset($res['url']) || isset($res['sum_page'])) {
            return $this->data($res);
        }
        return $this->dataLists($res['lists'], $res['count'], $res['pageNo'], $res['pageSize']);
    }

    public function clear()
    {
        OperationLogLogic::clear(
            OperationLogTenantContext::member($this->request),
            $this->adminId,
            (string)($this->adminInfo['username'] ?? ''),
            (string)$this->request->ip()
        );
        return $this->success('操作成功');
    }

    public function export()
    {
        try {
            $context = OperationLogTenantContext::member($this->request);
            $authorized = (new AdminAuthorizationService())->authorizedAsyncExport(
                $context,
                AdminPrincipal::fromArray($this->adminInfo),
            );
            $idempotencyKey = trim((string)$this->request->header('Idempotency-Key', ''));
            $operation = $this->runtime()->submitOperationLogExport($authorized, $idempotencyKey);
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
            $operation = $this->runtime()->operation(
                $authorized,
                (string)$this->request->get('operation_key', '')
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
                (string)$this->request->get('file_key', '')
            );
            return download($file['path'], $file['filename']);
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
        return TaskImportExportRuntime::fromConfig($pdo);
    }

    private function assertExportModule(): void
    {
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('ASYNC_DATABASE_UNAVAILABLE');
        }
        PdoModuleGovernanceProvider::forExecution($pdo)
            ->executionGuard('official.import-export')
            ->assertEnabled(
            ModuleExecutionContext::admin(
                'official.import-export',
                OperationLogTenantContext::member($this->request),
                'http.admin.export',
            ),
        );
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
