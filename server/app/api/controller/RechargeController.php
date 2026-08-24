<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\RechargeLogic;
use app\api\validate\RechargeValidate;
use app\common\service\finance\FinanceTenantContext;
class RechargeController extends BaseApiController
{
    public function config()
    {
        $params = ['terminal' => $this->request->get('terminal')];
        $this->validate($params, RechargeValidate::class . '.config');
        $result = RechargeLogic::config(FinanceTenantContext::member($this->request), $this->memberId, (int)$params['terminal']);
        return $result === false ? $this->fail(RechargeLogic::getError()) : $this->data($result);
    }

    public function create()
    {
        $params = $this->request->post();
        $this->validate($params, RechargeValidate::class . '.create');
        $result = RechargeLogic::create(FinanceTenantContext::member($this->request), $this->memberId, $params);
        return $result === false ? $this->fail(RechargeLogic::getError()) : $this->data($result);
    }

    public function prepay()
    {
        $params = $this->request->post();
        $this->validate($params, RechargeValidate::class . '.prepay');
        $payWay = (int)$params['pay_way'];
        $context = FinanceTenantContext::member($this->request);
        $result = RechargeLogic::prepay(
            $context,
            $this->memberId,
            (int)$params['order_id'],
            $payWay,
            rtrim((string)$this->request->domain(), '/'),
            (string)$this->request->ip(),
            ''
        );
        return $result === false ? $this->fail(RechargeLogic::getError()) : $this->data($result);
    }

    public function detail()
    {
        $params = ['order_id' => $this->request->get('order_id')];
        $this->validate($params, RechargeValidate::class . '.detail');
        $result = RechargeLogic::detail(FinanceTenantContext::member($this->request), $this->memberId, (int)$params['order_id']);
        return $result === false ? $this->fail(RechargeLogic::getError()) : $this->data($result);
    }

    public function lists()
    {
        $params = [
            'page_no' => $this->request->get('page_no/d', 1),
            'page_size' => $this->request->get('page_size/d', 15),
        ];
        $this->validate($params, RechargeValidate::class . '.lists');
        $result = RechargeLogic::lists(FinanceTenantContext::member($this->request), $this->memberId, $params);
        return $this->dataLists(
            $result['lists'],
            $result['count'],
            $result['page_no'],
            $result['page_size']
        );
    }
}
