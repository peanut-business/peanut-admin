<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\application\setting\TransactionSettingsApplicationService;
use app\common\application\BusinessException;

/**
 * 交易设置
 */
class TransactionSettingsController extends BaseAdminController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly TransactionSettingsApplicationService $transactionSettings)
    {
        parent::__construct($app, $executionContext);
    }

    public function getConfig()
    {
        return $this->data($this->transactionSettings->getConfig(
            $this->tenantAdminContext()
        ));
    }

    public function setConfig()
    {
        $post = $this->request->post();

        // 基础校验
        if (!isset($post['cancel_unpaid_orders']) || !in_array((int) $post['cancel_unpaid_orders'], [0, 1], true)) {
            throw BusinessException::invalid('TRANSACTION_CANCEL_MODE_INVALID', '请选择系统取消待付款订单方式');
        }
        if (!isset($post['verification_orders']) || !in_array((int) $post['verification_orders'], [0, 1], true)) {
            throw BusinessException::invalid('TRANSACTION_VERIFY_MODE_INVALID', '请选择系统自动核销订单方式');
        }
        if ((int) $post['cancel_unpaid_orders'] === 1) {
            $t = (int) ($post['cancel_unpaid_orders_times'] ?? 0);
            if ($t <= 0) {
                throw BusinessException::invalid('TRANSACTION_CANCEL_DELAY_INVALID', '系统取消待付款订单时间须大于 0');
            }
        }
        if ((int) $post['verification_orders'] === 1) {
            $t = (int) ($post['verification_orders_times'] ?? 0);
            if ($t <= 0) {
                throw BusinessException::invalid('TRANSACTION_VERIFY_DELAY_INVALID', '系统自动核销订单时间须大于 0');
            }
        }

        $this->transactionSettings->setConfig(
            $this->tenantAdminContext(),
            $post
        );
        return $this->success('操作成功');
    }
}
