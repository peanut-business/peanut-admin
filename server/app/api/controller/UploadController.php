<?php
declare(strict_types=1);

namespace app\api\controller;

use app\adminapi\controller\file\UploadController as AdminUpload;

/**
 * 用户端上传（复用 admin 的上传逻辑，但无需鉴权）
 */
class UploadController extends BaseApiController
{
    public function image()
    {
        $admin = new AdminUpload($this->app);
        return $admin->image();
    }
}
