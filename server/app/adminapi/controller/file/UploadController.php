<?php
declare(strict_types=1);

namespace app\adminapi\controller\file;

use app\adminapi\controller\BaseAdminController;
use app\common\enum\FileEnum;
use app\common\service\UploadService;
use app\common\service\file\FileTenantContext;

class UploadController extends BaseAdminController
{
    public function image()
    {
        return $this->upload('image');
    }

    public function video()
    {
        return $this->upload('video');
    }

    public function file()
    {
        return $this->upload('file');
    }

    /** @param string $method image|video|file */
    protected function upload(string $method)
    {
        try {
            $cidValue = $this->request->post('cid', 0);
            if (!is_int($cidValue) && !(is_string($cidValue) && preg_match('/^-?\d+$/D', $cidValue) === 1)) {
                throw new \InvalidArgumentException('目标分类无效');
            }
            $cid = (int)$cidValue;
            $res = UploadService::$method(
                FileTenantContext::member($this->request),
                $cid,
                $this->adminId,
                FileEnum::SOURCE_ADMIN
            );
            return $this->success('上传成功', $res);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }
}
