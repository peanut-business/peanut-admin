<?php
declare(strict_types=1);

namespace app\adminapi\controller\file;

use app\adminapi\controller\BaseAdminController;
use app\common\enum\FileEnum;
use app\common\service\UploadService;

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
        $cid = (int)$this->request->post('cid', 0);
        try {
            $res = UploadService::$method($cid, $this->adminId, FileEnum::SOURCE_ADMIN);
            return $this->success('上传成功', $res);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }
}
