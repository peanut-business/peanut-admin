<?php
declare(strict_types=1);
namespace app\common\service\export;

use app\common\service\storage\StorageRepository;
use app\common\service\storage\StorageService;
use PDO;
use PeanutAdmin\ImportExport\Application\ImportExportException;
use PeanutAdmin\ImportExport\File\FileMediaGateway;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;

final readonly class AppFileMediaGateway implements FileMediaGateway
{
    public function __construct(private PDO $pdo) {}
    public function openCsvInput(AuthorizedOperationContext $context,string $fileKey){throw ImportExportException::denied();}
    public function storePrivateCsv(AuthorizedOperationContext $context,string $operationKey,string $purpose,string $filename,$stream):string
    {
        if(!is_resource($stream)||$purpose!=='result'||preg_match('/^iox_[0-9a-f]{32}$/D',$operationKey)!==1)throw ImportExportException::fileUnavailable();
        $temporary=tempnam(sys_get_temp_dir(),'pa-csv-');if($temporary===false)throw ImportExportException::fileUnavailable();
        $output=fopen($temporary,'w+b');if(!is_resource($output)){@unlink($temporary);throw ImportExportException::fileUnavailable();}
        try{$bytes=stream_copy_to_stream($stream,$output,20*1024*1024+1);fclose($output);$output=null;if(!is_int($bytes)||$bytes<1||$bytes>20*1024*1024)throw ImportExportException::limitExceeded();
            $stored=$this->storage()->storePath($context->tenantContext->tenantId,$context->tenantContext->memberId,'export.csv',$temporary,$filename,'text/csv');return $stored['file_key'];
        }finally{if(is_resource($output))fclose($output);@unlink($temporary);}
    }
    public function authorizedDownload(AuthorizedOperationContext $context,string $fileKey):array
    {
        if(preg_match('/^file_[0-9a-f]{32}$/D',$fileKey)!==1)throw ImportExportException::fileUnavailable();
        $s=$this->pdo->prepare("SELECT 1 FROM pa_file_object f JOIN pa_import_export_operation o ON o.tenant_id=f.tenant_id AND o.result_file_key=f.file_key AND o.status='succeeded' AND o.retention_until>UTC_TIMESTAMP(3) WHERE f.tenant_id=:tenant_id AND f.file_key=:file_key AND f.status='ready' LIMIT 1");
        $s->execute(['tenant_id'=>$context->tenantContext->tenantId,'file_key'=>$fileKey]);if($s->fetchColumn()===false)throw ImportExportException::fileUnavailable();
        return ['url'=>$this->storage()->accessUrlForTenant($context->tenantContext->tenantId,$fileKey),'filename'=>'operation-logs.csv'];
    }
    private function storage():StorageService{return new StorageService(new StorageRepository($this->pdo));}
}
