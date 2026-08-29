<?php
declare(strict_types=1);

namespace app\adminapi\controller\log;

use think\App;

use app\adminapi\controller\BaseAdminController;
use app\common\http\PageResult;
use app\adminapi\application\log\OperationLogApplicationService;
use app\common\service\audit\OperationLogTenantContext;
use app\common\service\module\ModuleExecutionBoundary;

class OperationLogController extends BaseAdminController
{
    public function __construct(App $app, private readonly OperationLogApplicationService $operationLogs)
    {
        parent::__construct($app);
    }

    public function lists()
    {
        try {
            if ((int)$this->request->get('export', 0) > 0) {
                $this->assertExportModule();
            }
            $res = $this->operationLogs->lists(
                OperationLogTenantContext::member(),
                $this->request->get()
            );
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
        if (!$res instanceof PageResult) {
            return $this->data($res);
        }
        return $this->data($res);
    }

    public function clear()
    {
        $this->operationLogs->clear(
            OperationLogTenantContext::member(),
            $this->adminId,
            (string)($this->adminInfo['username'] ?? ''),
            (string)$this->request->ip()
        );
        return $this->success('操作成功');
    }

    private function assertExportModule(): void
    {
        app(ModuleExecutionBoundary::class)->assertHttp('official.import-export', 'http.admin.export');
    }

}
