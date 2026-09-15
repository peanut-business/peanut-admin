<?php
declare(strict_types=1);
namespace app\modules\official\import_export\infrastructure\file;

use app\common\services\storage\StorageService;
use PeanutAdmin\ImportExport\Application\ImportExportException;
use PeanutAdmin\ImportExport\file\fileMediaGateway;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;

final readonly class AppFileMediaGateway implements FileMediaGateway
{
    public function __construct(private StorageService $storage) {}
    public function openCsvInput(AuthorizedOperationContext $context,string $fileKey){throw ImportExportException::denied();}
    public function storePrivateCsv(AuthorizedOperationContext $context,string $operationKey,string $purpose,string $filename,$stream):string
    {
        if(!is_resource($stream)||$purpose!=='result'||preg_match('/^iox_[0-9a-f]{32}$/D',$operationKey)!==1)throw ImportExportException::fileUnavailable();
        $temporary=tempnam(sys_get_temp_dir(),'pa-csv-');if($temporary===false)throw ImportExportException::fileUnavailable();
        $output=fopen($temporary,'w+b');if(!is_resource($output)){@unlink($temporary);throw ImportExportException::fileUnavailable();}
        try{$bytes=stream_copy_to_stream($stream,$output,20*1024*1024+1);fclose($output);$output=null;if(!is_int($bytes)||$bytes<1||$bytes>20*1024*1024)throw ImportExportException::limitExceeded();
            $stored=$this->storage->storePath($context->tenantContext->tenantId,$context->tenantContext->memberId,'export.csv',$temporary,$filename,'text/csv');return $stored['file_key'];
        }finally{if(is_resource($output))fclose($output);@unlink($temporary);}
    }
    public function download(AuthorizedOperationContext $context,string $fileKey):array
    {
        if(preg_match('/^file_[0-9a-f]{32}$/D',$fileKey)!==1)throw ImportExportException::fileUnavailable();
        return ['url'=>$this->storage->accessUrlForTenant($context->tenantContext->tenantId,$fileKey),'filename'=>'operation-logs.csv'];
    }
}
