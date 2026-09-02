<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\api\application\RechargeApplicationService;
use app\api\validate\RechargeValidate;
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
        return $this->data($this->recharges->config($this->memberContext(), $this->memberId, (int)$params['terminal']));
    }

    public function create()
    {
        $params = $this->request->post();
        $this->validate($params, RechargeValidate::class . '.create');
        return $this->data($this->recharges->create($this->memberContext(), $this->memberId, $params));
    }

    public function prepay()
    {
        $params = $this->request->post();
        $this->validate($params, RechargeValidate::class . '.prepay');
        $payWay = (int)$params['pay_way'];
        $context = $this->memberContext();
        return $this->data($this->recharges->prepay(
            $context,
            $this->memberId,
            (int)$params['order_id'],
            $payWay,
            rtrim((string)$this->request->domain(), '/'),
            (string)$this->request->ip(),
            ''
        ));
    }

    public function detail()
    {
        $params = ['order_id' => $this->request->get('order_id')];
        $this->validate($params, RechargeValidate::class . '.detail');
        return $this->data($this->recharges->detail($this->memberContext(), $this->memberId, (int)$params['order_id']));
    }

    public function lists()
    {
        $params = [
            'page_no' => $this->request->get('page_no/d', 1),
            'page_size' => $this->request->get('page_size/d', 15),
        ];
        $this->validate($params, RechargeValidate::class . '.lists');
        $result = $this->recharges->lists($this->memberContext(), $this->memberId, $params);
        return $this->data($result);
    }
}
