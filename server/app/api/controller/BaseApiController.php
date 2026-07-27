<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\controller\BaseLikeAdminController;

class BaseApiController extends BaseLikeAdminController
{
    protected int   $memberId   = 0;
    protected array $memberInfo = [];

    public function initialize(): void
    {
        if (!empty($this->request->memberInfo)) {
            $this->memberInfo = $this->request->memberInfo;
            $this->memberId   = (int) ($this->request->memberInfo['id'] ?? 0);
        }
    }
}
