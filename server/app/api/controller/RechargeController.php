<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\RechargeLogic;
use app\api\validate\RechargeValidate;
class RechargeController extends BaseApiController
{
    public function config()
    {
        $params = ['terminal' => $this->request->get('terminal')];
        $this->validate($params, RechargeValidate::class . '.config');
        $result = RechargeLogic::config($this->memberId, (int)$params['terminal']);
        return $result === false ? $this->fail(RechargeLogic::getError()) : $this->data($result);
    }

    public function create()
    {
        $params = $this->request->post();
        $this->validate($params, RechargeValidate::class . '.create');
        $result = RechargeLogic::create($this->memberId, $params);
        return $result === false ? $this->fail(RechargeLogic::getError()) : $this->data($result);
    }

    public function prepay()
    {
        $params = $this->request->post();
        $this->validate($params, RechargeValidate::class . '.prepay');
        $payWay = (int)$params['pay_way'];
        $channel = $payWay === 2 ? 'wechat' : 'alipay';
        $notifyUrl = rtrim((string)$this->request->domain(), '/')
            . '/api/payment/notify/' . $channel;
        $result = RechargeLogic::prepay(
            $this->memberId,
            (int)$params['order_id'],
            $payWay,
            $notifyUrl,
            (string)$this->request->ip(),
            ''
        );
        return $result === false ? $this->fail(RechargeLogic::getError()) : $this->data($result);
    }

    public function detail()
    {
        $params = ['order_id' => $this->request->get('order_id')];
        $this->validate($params, RechargeValidate::class . '.detail');
        $result = RechargeLogic::detail($this->memberId, (int)$params['order_id']);
        return $result === false ? $this->fail(RechargeLogic::getError()) : $this->data($result);
    }

    public function lists()
    {
        $params = [
            'page_no' => $this->request->get('page_no/d', 1),
            'page_size' => $this->request->get('page_size/d', 15),
        ];
        $this->validate($params, RechargeValidate::class . '.lists');
        $result = RechargeLogic::lists($this->memberId, $params);
        return $this->dataLists(
            $result['lists'],
            $result['count'],
            $result['page_no'],
            $result['page_size']
        );
    }
}
