<?php
declare(strict_types=1);

use app\common\service\scaffold\ScaffoldUpgradeRunner;

$root=dirname(__DIR__,3);
require $root.'/scripts/scaffold-runtime/ScaffoldPathGuard.php';
require $root.'/scripts/scaffold-runtime/ScaffoldManifest.php';
require $root.'/scripts/scaffold-runtime/ScaffoldUpgradeLedger.php';
require $root.'/scripts/scaffold-runtime/ScaffoldUpgradeRunner.php';

const SCAFFOLD_FROM_COMMIT='14412607ba36f1816e39f7117f77eea4a9e7419e';

function scaffoldExpect(bool $condition,string $message): void { if(!$condition)throw new RuntimeException($message); }
function scaffoldRun(array $command,?string $cwd=null,array $environment=[]): string
{
    $pipes=[];$process=proc_open($command,[1=>['pipe','w'],2=>['pipe','w']],$pipes,$cwd,$environment+$_ENV);
    if(!is_resource($process))throw new RuntimeException('unable to start command');
    $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);
    if($code!==0)throw new RuntimeException('command failed('.$code.'): '.trim((string)$stderr));
    return (string)$stdout;
}
function scaffoldDelete(string $path): void
{
    if(is_dir($path)&&!is_link($path)){foreach(array_diff(scandir($path)?:[],['.','..'])as$entry)scaffoldDelete($path.'/'.$entry);rmdir($path);return;}
    if(file_exists($path)||is_link($path))unlink($path);
}
function scaffoldCopy(string $source,string $target): void
{
    mkdir($target,0775,true);$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
    foreach($iterator as$file){$relative=substr($file->getPathname(),strlen($source)+1);$destination=$target.'/'.$relative;if($file->isDir())mkdir($destination,$file->getPerms()&0777,true);else{copy($file->getPathname(),$destination);chmod($destination,$file->getPerms()&0777);}}
}
function scaffoldFileTree(string $root,bool $includeUpgradeState=false): string
{
    $rows=[];$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
    foreach($iterator as$file){$relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));if(!$includeUpgradeState&&($relative==='.peanut/upgrades'||str_starts_with($relative,'.peanut/upgrades/')))continue;$rows[]=($file->isDir()?'d':'f')."\0".$relative."\0".($file->isFile()?hash_file('sha256',$file->getPathname()):'-')."\0".($file->getPerms()&0777);}
    sort($rows,SORT_STRING);return hash('sha256',implode("\n",$rows));
}
function scaffoldPlanPath(string $project,array $plan): string{return $project.'/'.$plan['plan_path'];}
function scaffoldFails(callable $callback,string $message): void
{
    try{$callback();throw new RuntimeException('expected failure: '.$message);}catch(RuntimeException $exception){scaffoldExpect(str_contains($exception->getMessage(),$message),'unexpected failure: '.$exception->getMessage());}
}
function scaffoldFresh(string $source,string $target): void
{
    scaffoldRun(['php',$source.'/scripts/create-app','--name=Acme Console','--slug=acme-console','--package=acme/acme-console','--target='.$target]);
}
function scaffoldCopyRelease(string $source,string $target): void { scaffoldCopy(dirname($source),$target); }

$temporaryRoot=realpath(sys_get_temp_dir());if($temporaryRoot===false)throw new RuntimeException('temp root unavailable');
$temporary=$temporaryRoot.'/peanut-scaffold-e2e-'.bin2hex(random_bytes(8));mkdir($temporary,0700,true);
$fromRelease=$root.'/scaffold/releases/v1.0.0/scaffold-manifest.json';$toRelease=$root.'/scaffold/releases/v1.1.0/scaffold-manifest.json';
try{
    $source=$temporary.'/from-source';
    scaffoldRun(['git','clone','--quiet','--no-local','--no-checkout',$root,$source]);
    scaffoldRun(['git','checkout','--quiet','--detach',SCAFFOLD_FROM_COMMIT],$source);
    $from=$temporary.'/from-app';scaffoldFresh($source,$from);
    $fromManifest=json_decode((string)file_get_contents($from.'/.peanut/application-manifest.json'),true,512,JSON_THROW_ON_ERROR);
    scaffoldExpect($fromManifest['template']['source_commit']===SCAFFOLD_FROM_COMMIT,'from app must use the formal create-app commit');
    $appOwnedPath='server/config/peanut.php';file_put_contents($from.'/'.$appOwnedPath,(string)file_get_contents($from.'/'.$appOwnedPath)."\n// application customization\n");
    $appOwnedDigest=hash_file('sha256',$from.'/'.$appOwnedPath);

    $runner=new ScaffoldUpgradeRunner();$plan=$runner->preflight($from,$fromRelease,$toRelease);
    scaffoldExpect($plan['status']==='ready'&&$plan['summary']['conflicts']===0,'pristine managed tree must plan successfully');
    $apply=$runner->apply($from,scaffoldPlanPath($from,$plan));$verify=$runner->verify($from,scaffoldPlanPath($from,$plan));
    scaffoldExpect($apply['status']==='applied'&&$verify['status']==='verified','apply and verify must complete');
    scaffoldExpect(hash_equals($appOwnedDigest,(string)hash_file('sha256',$from.'/'.$appOwnedPath)),'app-owned customization must remain byte-identical');
    $again=$runner->apply($from,scaffoldPlanPath($from,$plan));scaffoldExpect($again['idempotent']===true,'successful candidate apply must be idempotent');

    $blocked=$temporary.'/blocked';scaffoldCopy($temporary.'/from-app',$blocked);
    // Restore a real v1 project for independent scenarios.
    scaffoldDelete($blocked);scaffoldCopy($temporary.'/from-app',$blocked);
    $blockedManifest=json_decode((string)file_get_contents($blocked.'/.peanut/application-manifest.json'),true,512,JSON_THROW_ON_ERROR);
    // The copied app is already upgraded, so create fresh scenario roots from the formal create-app tree.
    scaffoldDelete($blocked);scaffoldFresh($source,$blocked);
    $changed='scripts/scaffold-upgrade';file_put_contents($blocked.'/'.$changed,(string)file_get_contents($blocked.'/'.$changed)."\n# local managed edit\n");$beforeBlocked=scaffoldFileTree($blocked);
    $blockedPlan=$runner->preflight($blocked,$fromRelease,$toRelease);scaffoldExpect($blockedPlan['status']==='blocked','both-sides managed change must block');
    scaffoldFails(fn()=>$runner->apply($blocked,scaffoldPlanPath($blocked,$blockedPlan)),'SCAFFOLD_PLAN_BLOCKED');
    scaffoldExpect(hash_equals($beforeBlocked,scaffoldFileTree($blocked)),'blocked apply must perform zero product-tree writes');

    $stale=$temporary.'/stale';scaffoldFresh($source,$stale);
    $stalePlan=$runner->preflight($stale,$fromRelease,$toRelease);file_put_contents($stale.'/README.md',(string)file_get_contents($stale.'/README.md')."\nchanged after plan\n");
    scaffoldFails(fn()=>$runner->apply($stale,scaffoldPlanPath($stale,$stalePlan)),'SCAFFOLD_PLAN_PROJECT_CHANGED');

    $fault=$temporary.'/fault';scaffoldFresh($source,$fault);
    $faultBefore=scaffoldFileTree($fault);$faultPlan=$runner->preflight($fault,$fromRelease,$toRelease);
    putenv('PEANUT_SCAFFOLD_FAIL_AFTER_REPLACEMENTS=1');scaffoldFails(fn()=>$runner->apply($fault,scaffoldPlanPath($fault,$faultPlan)),'SCAFFOLD_FAULT_INJECTED');putenv('PEANUT_SCAFFOLD_FAIL_AFTER_REPLACEMENTS');
    scaffoldExpect(!hash_equals($faultBefore,scaffoldFileTree($fault)),'fault injection must happen after a real replacement');
    $recover=$runner->recover($fault,scaffoldPlanPath($fault,$faultPlan));scaffoldExpect(hash_equals($faultBefore,scaffoldFileTree($fault)),'recovery must restore the exact pre-apply tree and modes');
    $recoverAgain=$runner->recover($fault,scaffoldPlanPath($fault,$faultPlan));scaffoldExpect($recoverAgain['idempotent']===true&&$recover['status']==='recovered','recovery must be idempotent');

    $securityRelease=$temporary.'/security-release';scaffoldCopyRelease($toRelease,$securityRelease);
    $securityManifest=$securityRelease.'/scaffold-manifest.json';$securityData=json_decode((string)file_get_contents($securityManifest),true,512,JSON_THROW_ON_ERROR);
    $securityData['files'][0]['path']='../escape';file_put_contents($securityManifest,json_encode($securityData,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    scaffoldFails(fn()=>$runner->preflight($fault,$fromRelease,$securityManifest),'SCAFFOLD_PATH_OUTSIDE_PROJECT');
    scaffoldDelete($securityRelease);scaffoldCopyRelease($toRelease,$securityRelease);$securityManifest=$securityRelease.'/scaffold-manifest.json';$securityData=json_decode((string)file_get_contents($securityManifest),true,512,JSON_THROW_ON_ERROR);
    $securityData['files'][0]['policy']='unknown';file_put_contents($securityManifest,json_encode($securityData,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    scaffoldFails(fn()=>$runner->preflight($fault,$fromRelease,$securityManifest),'SCAFFOLD_MANIFEST_POLICY_INVALID');
    scaffoldDelete($securityRelease);scaffoldCopyRelease($toRelease,$securityRelease);$securityManifest=$securityRelease.'/scaffold-manifest.json';$securityData=json_decode((string)file_get_contents($securityManifest),true,512,JSON_THROW_ON_ERROR);
    $securityData['files'][0]['transform']='unknown';file_put_contents($securityManifest,json_encode($securityData,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    scaffoldFails(fn()=>$runner->preflight($fault,$fromRelease,$securityManifest),'SCAFFOLD_MANIFEST_FILE_INVALID');

    $symlink=$temporary.'/symlink';scaffoldFresh($source,$symlink);$outside=$temporary.'/outside';file_put_contents($outside,'outside');unlink($symlink.'/scripts/scaffold-upgrade');symlink($outside,$symlink.'/scripts/scaffold-upgrade');
    scaffoldFails(fn()=>$runner->preflight($symlink,$fromRelease,$toRelease),'SCAFFOLD_PATH_SYMLINK_REJECTED');
    $hardlink=$temporary.'/hardlink';scaffoldFresh($source,$hardlink);link($hardlink.'/scripts/scaffold-upgrade',$hardlink.'/hardlink-alias');
    scaffoldFails(fn()=>$runner->preflight($hardlink,$fromRelease,$toRelease),'SCAFFOLD_PATH_HARDLINK_REJECTED');

    $drift=$temporary.'/drift';scaffoldFresh($source,$drift);$driftRelease=$temporary.'/drift-release';scaffoldCopyRelease($toRelease,$driftRelease);$driftManifest=$driftRelease.'/scaffold-manifest.json';$driftPlan=$runner->preflight($drift,$fromRelease,$driftManifest);
    file_put_contents($driftManifest,(string)file_get_contents($driftManifest)."\n");
    scaffoldFails(fn()=>$runner->apply($drift,scaffoldPlanPath($drift,$driftPlan)),'SCAFFOLD_MANIFEST_CHECKSUM_DRIFT');

    $fromCheck=scaffoldRun(['php',$root.'/scripts/build-scaffold-release','--version=1.0.0','--source-commit='.SCAFFOLD_FROM_COMMIT,'--output='.$root.'/scaffold/releases/v1.0.0','--check']);
    $toManifest=json_decode((string)file_get_contents($toRelease),true,512,JSON_THROW_ON_ERROR);$toCheck=scaffoldRun(['php',$root.'/scripts/build-scaffold-release','--version=1.1.0','--source-commit='.$toManifest['release']['source_commit'],'--output='.$root.'/scaffold/releases/v1.1.0','--check']);
    scaffoldExpect(str_contains($fromCheck,'verified')&&str_contains($toCheck,'verified'),'both immutable release trees must exactly regenerate');
}finally{putenv('PEANUT_SCAFFOLD_FAIL_AFTER_REPLACEMENTS');scaffoldDelete($temporary);}

echo "SCAFFOLD-UPGRADE-E2E-001 passed\n";
