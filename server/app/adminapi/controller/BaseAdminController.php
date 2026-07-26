<?php
declare(strict_types=1);

namespace app\adminapi\controller;

use app\common\controller\BaseLikeAdminController;

class BaseAdminController extends BaseLikeAdminController
{
    protected int   $adminId   = 0;
    protected array $adminInfo = [];

    public function initialize(): void
    {
        if (!empty($this->request->adminInfo)) {
            $this->adminInfo = $this->request->adminInfo;
            $this->adminId   = (int) ($this->request->adminInfo['id'] ?? 0);
        }
    }
}
