<?php
declare(strict_types=1);
namespace app\api\controller;
use app\common\service\storage\StorageService;
final class StorageController extends BaseApiController
{
    public function privateFile()
    {
        try{$file=StorageService::fromDefaultConnection()->authorizedLocalDownload((int)$this->request->get('tenant_id',0),(string)$this->request->get('file_key',''),(int)$this->request->get('expires',0),(string)$this->request->get('signature',''));return download($file['path'],$file['filename']);}
        catch(\Throwable $e){return $this->fail($e->getMessage());}
    }
}
