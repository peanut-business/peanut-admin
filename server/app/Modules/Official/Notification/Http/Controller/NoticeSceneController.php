<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Http\Controller;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Notification\Contracts\NotificationCommands;
use app\Modules\Official\Notification\Contracts\NotificationQueries;
use app\Modules\Official\Notification\Validation\NoticeSceneValidate;
use think\App;

class NoticeSceneController extends BaseAdminController
{
    public function __construct(
        App $app,
        private readonly NotificationQueries $queries,
        private readonly NotificationCommands $commands,
    ) {
        parent::__construct($app);
    }

    public function lists()
    {
        return $this->data($this->queries->scenes());
    }

    public function detail()
    {
        $params = $this->request->get();
        $this->validate($params, NoticeSceneValidate::class . '.detail');
        return $this->data($this->queries->sceneDetail((int) $params['id']));
    }

    public function save()
    {
        $params = $this->request->post();
        $this->validate($params, NoticeSceneValidate::class . '.save');
        $this->commands->saveScene($params);
        return $this->success('保存成功');
    }

}
