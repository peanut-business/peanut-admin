<?php
declare(strict_types=1);

namespace app\adminapi\controller;

use app\common\controller\BaseLikeAdminController;
use app\common\service\JsonService;
use think\exception\ValidateException;
use think\Validate;

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

    /**
     * 校验参数：失败直接抛出统一 JSON 错误（40000），成功返回校验数据。
     *
     * @param class-string<Validate> $validate 验证器类名
     * @param string                 $scene    场景（add/edit...）
     */
    protected function checkParams(string $validate, string $scene = '', ?array $data = null): array
    {
        $data      = $data ?? $this->request->param();
        $validator = new $validate();
        if ($scene !== '') {
            $validator = $validator->scene($scene);
        }
        try {
            $validator->failException(true)->check($data);
        } catch (ValidateException $e) {
            JsonService::throw($e->getError());
        }
        return $data;
    }
}
