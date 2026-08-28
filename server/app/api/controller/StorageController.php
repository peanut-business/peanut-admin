<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\service\storage\StorageService;

final class StorageController extends BaseApiController
{
    public function delivery()
    {
        try {
            $file = StorageService::fromDefaultConnection()->authorizedDownload(
                (int)$this->request->get('tenant_id', 0),
                (string)$this->request->get('file_key', ''),
                (int)$this->request->get('expires', 0),
                (string)$this->request->get('signature', ''),
            );
            $contents = file_get_contents($file['path']);
            if (!is_string($contents)) {
                throw new \RuntimeException('文件不存在或不可用');
            }
            if ($file['temporary'] && is_file($file['path'])) {
                unlink($file['path']);
            }
            $filename = rawurlencode($file['filename']);
            return response($contents, 200, [
                'Cache-Control' => 'no-store, private',
                'Content-Disposition' => $file['disposition'] . "; filename*=UTF-8''" . $filename,
                'Content-Length' => (string)strlen($contents),
                'Content-Type' => $file['media_type'],
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable) {
            return response('', 404, [
                'Cache-Control' => 'no-store, private',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }
    }
}
