<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\api\application\RechargeApplicationService;
use app\api\validate\RechargeValidate;
use app\common\service\finance\FinanceTenantContext;
class RechargeController extends BaseApiController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly RechargeApplicationService $recharges)
    {
        parent::__construct($app, $executionContext);
    }

    public function config()
    {
        $params = ['terminal' => $this->request->get('terminal')];
        $this->validate($params, RechargeValidate::class . '.config');
        $result = $this->recharges->config(FinanceTenantContext::member(), $this->memberId, (int)$params['terminal']);
        return $result === false ? $this->fail($this->recharges->getError()) : $this->data($result);
    }

    public function create()
    {
        $params = $this->request->post();
        $this->validate($params, RechargeValidate::class . '.create');
        $result = $this->recharges->create(FinanceTenantContext::member(), $this->memberId, $params);
        return $result === false ? $this->fail($this->recharges->getError()) : $this->data($result);
    }

    public function prepay()
    {
        $params = $this->request->post();
        $this->validate($params, RechargeValidate::class . '.prepay');
        $payWay = (int)$params['pay_way'];
        $context = FinanceTenantContext::member();
        $result = $this->recharges->prepay(
            $context,
            $this->memberId,
            (int)$params['order_id'],
            $payWay,
            rtrim((string)$this->request->domain(), '/'),
            (string)$this->request->ip(),
            ''
        );
        return $result === false ? $this->fail($this->recharges->getError()) : $this->data($result);
    }

    public function detail()
    {
        $params = ['order_id' => $this->request->get('order_id')];
        $this->validate($params, RechargeValidate::class . '.detail');
        $result = $this->recharges->detail(FinanceTenantContext::member(), $this->memberId, (int)$params['order_id']);
        return $result === false ? $this->fail($this->recharges->getError()) : $this->data($result);
    }

    public function lists()
    {
        $params = [
            'page_no' => $this->request->get('page_no/d', 1),
            'page_size' => $this->request->get('page_size/d', 15),
        ];
        $this->validate($params, RechargeValidate::class . '.lists');
        $result = $this->recharges->lists(FinanceTenantContext::member(), $this->memberId, $params);
        return $this->data($result);
    }
}
