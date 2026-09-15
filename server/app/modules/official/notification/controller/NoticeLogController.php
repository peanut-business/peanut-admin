<?php
declare(strict_types=1);

namespace app\modules\official\notification\controller;

use app\adminapi\controller\BaseAdminController;
use app\common\execution\CurrentExecutionContext;
use app\modules\official\notification\contracts\NotificationQueries;
use think\App;

class NoticeLogController extends BaseAdminController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly NotificationQueries $queries)
    {
        parent::__construct($app, $executionContext);
    }

    public function lists()
    {
        return $this->data($this->queries->logs($this->request->get()));
    }

    public function detail()
    {
        $id = (int) $this->request->get('id', 0);
        return $this->data($this->queries->logDetail($id));
    }
}
