<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\setting\TransactionSettingsLogic;

/**
 * 交易设置
 */
class TransactionSettingsController extends BaseAdminController
{
    public function getConfig()
    {
        return $this->data(TransactionSettingsLogic::getConfig());
    }

    public function setConfig()
    {
        $post = $this->request->post();

        // 基础校验
        if (!isset($post['cancel_unpaid_orders']) || !in_array((int) $post['cancel_unpaid_orders'], [0, 1], true)) {
            return $this->fail('请选择系统取消待付款订单方式');
        }
        if (!isset($post['verification_orders']) || !in_array((int) $post['verification_orders'], [0, 1], true)) {
            return $this->fail('请选择系统自动核销订单方式');
        }
        if ((int) $post['cancel_unpaid_orders'] === 1) {
            $t = (int) ($post['cancel_unpaid_orders_times'] ?? 0);
            if ($t <= 0) {
                return $this->fail('系统取消待付款订单时间须大于 0');
            }
        }
        if ((int) $post['verification_orders'] === 1) {
            $t = (int) ($post['verification_orders_times'] ?? 0);
            if ($t <= 0) {
                return $this->fail('系统自动核销订单时间须大于 0');
            }
        }

        TransactionSettingsLogic::setConfig($post);
        return $this->success('操作成功', [], 1, 1);
    }
}
