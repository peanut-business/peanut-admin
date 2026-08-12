<?php
declare(strict_types=1);

namespace app\adminapi\controller\log;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\log\OperationLogLogic;
use app\common\service\audit\OperationLogTenantContext;

class OperationLogController extends BaseAdminController
{
    public function lists()
    {
        try {
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
}
