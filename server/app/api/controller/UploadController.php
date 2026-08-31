<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\FileEnum;
use app\common\service\UploadService;
use app\common\service\member\MemberTenantContext;
use think\file\UploadedFile;

/**
 * 用户端上传
 */
class UploadController extends BaseApiController
{
    public function image()
    {
        try {
            $cidValue = $this->request->post('cid', 0);
            if (!is_int($cidValue) && !(is_string($cidValue) && preg_match('/^-?\d+$/D', $cidValue) === 1)) {
                throw new \InvalidArgumentException('目标分类无效');
            }
            $uploaded = $this->request->file('file');
            if (!$uploaded instanceof UploadedFile) {
                throw new \Exception('未接收到上传文件');
            }
            $result = UploadService::image(
                MemberTenantContext::member(),
                $uploaded,
                (int)$cidValue,
                $this->memberId,
                FileEnum::SOURCE_USER
            );
            return $this->success('上传成功', $result);
        } catch (\Throwable $exception) {
            return $this->fail($exception->getMessage());
        }
    }
}
