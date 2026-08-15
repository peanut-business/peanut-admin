<?php
declare(strict_types=1);

use app\common\service\scaffold\ScaffoldUpgradeRunner;

$root=dirname(__DIR__,3);
require $root.'/scripts/scaffold-runtime/ScaffoldPathGuard.php';
require $root.'/scripts/scaffold-runtime/ScaffoldManifest.php';
require $root.'/scripts/scaffold-runtime/ScaffoldUpgradeLedger.php';
require $root.'/scripts/scaffold-runtime/ScaffoldUpgradeRunner.php';

const SCAFFOLD_FROM_COMMIT='14412607ba36f1816e39f7117f77eea4a9e7419e';
const SCAFFOLD_V1_1_2_CREATE_COMMIT='2cdb5763621e320c60cdbb834dcc0160e7bb7636';

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
function scaffoldOwnedTree(string $root,array $manifest,string $classification): string
{
    $rows=[];foreach($manifest['files'] as$file){if(($file['classification']??null)!==$classification)continue;$path=$root.'/'.$file['path'];$rows[]=$file['path']."\0".hash_file('sha256',$path)."\0".(fileperms($path)&0777);}sort($rows,SORT_STRING);return hash('sha256',implode("\n",$rows));
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
function scaffoldFreshAdopted(string $source,string $releasePath,string $target): void
{
    $code=<<<'PHP'
require $argv[1].'/server/app/common/service/scaffold/ScaffoldPathGuard.php';
require $argv[1].'/server/app/common/service/scaffold/ScaffoldManifest.php';
require $argv[1].'/server/app/common/service/scaffold/ApplicationCreator.php';
$release=json_decode((string)file_get_contents($argv[2]),true,512,JSON_THROW_ON_ERROR)['release'];
$creator=new app\common\service\scaffold\ApplicationCreator(
    $argv[1],
    $argv[1].'/scaffold/application-template-inventory.json',
    ['commit'=>$release['source_commit'],'tree'=>$release['source_tree']],
    $argv[2]
);
$creator->create('Acme Console','acme-console','acme/acme-console',$argv[3]);
PHP;
    scaffoldRun(['php','-r',$code,$source,$releasePath,$target]);
}
function scaffoldCopyRelease(string $source,string $target): void { scaffoldCopy(dirname($source),$target); }

$temporaryRoot=realpath(sys_get_temp_dir());if($temporaryRoot===false)throw new RuntimeException('temp root unavailable');
$temporary=$temporaryRoot.'/peanut-scaffold-e2e-'.bin2hex(random_bytes(8));mkdir($temporary,0700,true);
$fromRelease=$root.'/scaffold/releases/v1.0.0/scaffold-manifest.json';$toRelease=$root.'/scaffold/releases/v1.1.0/scaffold-manifest.json';$patchRelease=$root.'/scaffold/releases/v1.1.1/scaffold-manifest.json';$latestRelease=$root.'/scaffold/releases/v1.1.2/scaffold-manifest.json';$nextRelease=$root.'/scaffold/releases/v1.1.3/scaffold-manifest.json';$currentRelease=$root.'/scaffold/releases/v1.1.4/scaffold-manifest.json';$runtimeRelease=$root.'/scaffold/releases/v1.1.5/scaffold-manifest.json';$releaseCandidate=$root.'/scaffold/releases/v1.1.6/scaffold-manifest.json';$productRelease=$root.'/scaffold/releases/v1.1.7/scaffold-manifest.json';
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
    $toIdentity=json_decode((string)file_get_contents($toRelease),true,512,JSON_THROW_ON_ERROR)['release'];
    $toSource=$temporary.'/to-source';scaffoldRun(['git','clone','--quiet','--no-local','--no-checkout',$root,$toSource]);scaffoldRun(['git','checkout','--quiet','--detach',$toIdentity['source_commit']],$toSource);
    $toApplication=$temporary.'/to-app';scaffoldFresh($toSource,$toApplication);$toApplicationManifest=json_decode((string)file_get_contents($toApplication.'/.peanut/application-manifest.json'),true,512,JSON_THROW_ON_ERROR);
    scaffoldExpect($toApplicationManifest['template']['source_tree']===$toIdentity['source_tree'],'target create-app tree must match the release source tree');
    foreach($toApplicationManifest['files'] as $file){if(!in_array($file['classification'],['managed','generated-managed'],true))continue;$upgraded=$from.'/'.$file['path'];$generated=$toApplication.'/'.$file['path'];scaffoldExpect(is_file($upgraded)&&hash_equals((string)hash_file('sha256',$generated),(string)hash_file('sha256',$upgraded))&&((fileperms($generated)&0777)===(fileperms($upgraded)&0777)),'upgraded managed tree must exactly equal target create-app: '.$file['path']);}
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

    $tampered=$temporary.'/tampered';scaffoldFresh($source,$tampered);$tamperedPlan=$runner->preflight($tampered,$fromRelease,$toRelease);$tamperedPath=scaffoldPlanPath($tampered,$tamperedPlan);
    $tamperedData=json_decode((string)file_get_contents($tamperedPath),true,512,JSON_THROW_ON_ERROR);$tamperedData['actions'][0]['mode']=0600;file_put_contents($tamperedPath,json_encode($tamperedData,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    scaffoldFails(fn()=>$runner->apply($tampered,$tamperedPath),'SCAFFOLD_PLAN_CHECKSUM_DRIFT');

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

    $patchSource=$temporary.'/patch-from-source';scaffoldRun(['git','clone','--quiet','--no-local','--no-checkout',$root,$patchSource]);scaffoldRun(['git','checkout','--quiet','--detach',$toIdentity['source_commit']],$patchSource);
    $patchApp=$temporary.'/patch-app';scaffoldFresh($patchSource,$patchApp);$patchBefore=scaffoldFileTree($patchApp);
    $patchPlan=$runner->preflight($patchApp,$toRelease,$patchRelease);
    scaffoldExpect($patchPlan['status']==='ready'&&$patchPlan['summary']['conflicts']===0,'v1.1.0 to patch plan must be ready');
    $patchActions=[];foreach($patchPlan['actions'] as $action)$patchActions[$action['path']]=$action['action'];
    scaffoldExpect(($patchActions['plugins.lock']??null)==='replace','patch must replace the broken Plugin lock');
    scaffoldExpect(($patchActions['server/fixtures/plugin-module-lifecycle/run.php']??null)==='delete','patch must remove the source-only lifecycle runner');
    $patchApply=$runner->apply($patchApp,scaffoldPlanPath($patchApp,$patchPlan));$patchVerify=$runner->verify($patchApp,scaffoldPlanPath($patchApp,$patchPlan));
    scaffoldExpect($patchApply['status']==='applied'&&$patchVerify['status']==='verified','v1.1.0 to patch apply/verify must complete');
    $patchLock=json_decode((string)file_get_contents($patchApp.'/plugins.lock'),true,64,JSON_THROW_ON_ERROR);
    scaffoldExpect($patchLock===['schema_version'=>1,'plugins'=>[]],'patch must install an explicitly empty Plugin lock');
    scaffoldExpect(!file_exists($patchApp.'/server/fixtures/plugin-module-lifecycle/run.php'),'patch must not retain the demo lifecycle runner');
    $patchRecover=$runner->recover($patchApp,scaffoldPlanPath($patchApp,$patchPlan));
    scaffoldExpect($patchRecover['status']==='recovered'&&hash_equals($patchBefore,scaffoldFileTree($patchApp)),'patch recovery must restore the exact v1.1.0 tree');

    $patchIdentity=json_decode((string)file_get_contents($patchRelease),true,512,JSON_THROW_ON_ERROR)['release'];
    $latestFromSource=$temporary.'/latest-from-source';scaffoldRun(['git','clone','--quiet','--no-local','--no-checkout',$root,$latestFromSource]);scaffoldRun(['git','checkout','--quiet','--detach',$patchIdentity['source_commit']],$latestFromSource);
    $latestApp=$temporary.'/latest-app';scaffoldFresh($latestFromSource,$latestApp);
    $latestAppOwnedPath='server/config/peanut.php';file_put_contents($latestApp.'/'.$latestAppOwnedPath,(string)file_get_contents($latestApp.'/'.$latestAppOwnedPath)."\n// v1.1.2 preservation proof\n");$latestAppOwnedDigest=hash_file('sha256',$latestApp.'/'.$latestAppOwnedPath);
    $latestApplicationManifestPath=$latestApp.'/.peanut/application-manifest.json';$latestApplicationManifest=json_decode((string)file_get_contents($latestApplicationManifestPath),true,512,JSON_THROW_ON_ERROR);
    $generationSource=['commit'=>str_repeat('c',40),'tree'=>str_repeat('d',40),'inventory_sha256'=>str_repeat('e',64)];$latestApplicationManifest['generation_source']=$generationSource;
    file_put_contents($latestApplicationManifestPath,json_encode($latestApplicationManifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");
    $latestBefore=scaffoldFileTree($latestApp);$latestPlan=$runner->preflight($latestApp,$patchRelease,$latestRelease);
    scaffoldExpect($latestPlan['status']==='ready'&&$latestPlan['summary']['conflicts']===0,'v1.1.1 to v1.1.2 plan must be ready');
    $latestAutomatic=array_values(array_filter($latestPlan['actions'],static fn(array $action):bool=>in_array($action['action'],['create','delete','replace','regenerate'],true)));
    scaffoldExpect(count($latestAutomatic)===1&&$latestAutomatic[0]['path']==='scripts/project-resource-registry'&&$latestAutomatic[0]['action']==='replace','v1.1.2 must replace only the generic resource selector managed file');
    $latestApply=$runner->apply($latestApp,scaffoldPlanPath($latestApp,$latestPlan));$latestVerify=$runner->verify($latestApp,scaffoldPlanPath($latestApp,$latestPlan));
    scaffoldExpect($latestApply['status']==='applied'&&$latestVerify['status']==='verified','v1.1.1 to v1.1.2 apply/verify must complete');
    $latestAppliedManifest=json_decode((string)file_get_contents($latestApplicationManifestPath),true,512,JSON_THROW_ON_ERROR);
    scaffoldExpect(($latestAppliedManifest['generation_source']??null)===$generationSource,'scaffold upgrade must preserve generation_source');
    scaffoldExpect(($latestAppliedManifest['last_scaffold_upgrade']['from']??null)==='1.1.1'&&($latestAppliedManifest['last_scaffold_upgrade']['to']??null)==='1.1.2','scaffold upgrade must record the v1.1.2 transition');
    scaffoldExpect(hash_equals($latestAppOwnedDigest,(string)hash_file('sha256',$latestApp.'/'.$latestAppOwnedPath)),'v1.1.2 upgrade must preserve app-owned bytes');
    $latestRecover=$runner->recover($latestApp,scaffoldPlanPath($latestApp,$latestPlan));
    scaffoldExpect($latestRecover['status']==='recovered'&&hash_equals($latestBefore,scaffoldFileTree($latestApp)),'v1.1.2 recovery must restore the exact v1.1.1 tree');

    $nextFromSource=$temporary.'/next-from-source';scaffoldRun(['git','clone','--quiet','--no-local','--no-checkout',$root,$nextFromSource]);scaffoldRun(['git','checkout','--quiet','--detach',SCAFFOLD_V1_1_2_CREATE_COMMIT],$nextFromSource);
    $nextApp=$temporary.'/next-app';scaffoldFresh($nextFromSource,$nextApp);
    $nextAppOwnedPath='server/config/peanut.php';file_put_contents($nextApp.'/'.$nextAppOwnedPath,(string)file_get_contents($nextApp.'/'.$nextAppOwnedPath)."\n// v1.1.3 preservation proof\n");$nextAppOwnedDigest=hash_file('sha256',$nextApp.'/'.$nextAppOwnedPath);
    $nextApplicationManifestPath=$nextApp.'/.peanut/application-manifest.json';$nextApplicationManifest=json_decode((string)file_get_contents($nextApplicationManifestPath),true,512,JSON_THROW_ON_ERROR);
    $nextGenerationSource=['commit'=>str_repeat('f',40),'tree'=>str_repeat('1',40),'inventory_sha256'=>str_repeat('2',64)];$nextApplicationManifest['generation_source']=$nextGenerationSource;
    file_put_contents($nextApplicationManifestPath,json_encode($nextApplicationManifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");
    $nextBefore=scaffoldFileTree($nextApp);$nextPlan=$runner->preflight($nextApp,$latestRelease,$nextRelease);
    scaffoldExpect($nextPlan['status']==='ready'&&$nextPlan['summary']['conflicts']===0,'v1.1.2 to v1.1.3 plan must be ready');
    $nextAutomatic=array_values(array_filter($nextPlan['actions'],static fn(array $action):bool=>in_array($action['action'],['create','delete','replace','regenerate'],true)));
    scaffoldExpect(count($nextAutomatic)===1&&$nextAutomatic[0]['path']==='deploy/docker/production.Dockerfile'&&$nextAutomatic[0]['action']==='replace','v1.1.3 must replace only the production Dockerfile managed file');
    $nextApply=$runner->apply($nextApp,scaffoldPlanPath($nextApp,$nextPlan));$nextVerify=$runner->verify($nextApp,scaffoldPlanPath($nextApp,$nextPlan));
    scaffoldExpect($nextApply['status']==='applied'&&$nextVerify['status']==='verified','v1.1.2 to v1.1.3 apply/verify must complete');
    $nextAppliedManifest=json_decode((string)file_get_contents($nextApplicationManifestPath),true,512,JSON_THROW_ON_ERROR);
    scaffoldExpect(($nextAppliedManifest['generation_source']??null)===$nextGenerationSource,'v1.1.3 scaffold upgrade must preserve generation_source');
    scaffoldExpect(($nextAppliedManifest['last_scaffold_upgrade']['from']??null)==='1.1.2'&&($nextAppliedManifest['last_scaffold_upgrade']['to']??null)==='1.1.3','scaffold upgrade must record the v1.1.3 transition');
    scaffoldExpect(hash_equals($nextAppOwnedDigest,(string)hash_file('sha256',$nextApp.'/'.$nextAppOwnedPath)),'v1.1.3 upgrade must preserve app-owned bytes');
    scaffoldExpect(str_contains((string)file_get_contents($nextApp.'/deploy/docker/production.Dockerfile'),'COPY plugins.lock /build/plugins.lock'),'v1.1.3 upgrade must install the production Plugin lock copy');
    scaffoldExpect(str_contains((string)file_get_contents($nextApp.'/deploy/docker/production.Dockerfile'),'COPY resources/project-resources.json resources/project-resources.json'),'v1.1.3 upgrade must install the production resource registry copy');
    $nextRecover=$runner->recover($nextApp,scaffoldPlanPath($nextApp,$nextPlan));
    scaffoldExpect($nextRecover['status']==='recovered'&&hash_equals($nextBefore,scaffoldFileTree($nextApp)),'v1.1.3 recovery must restore the exact v1.1.2 tree');

    $legacyIdentity=json_decode((string)file_get_contents($nextRelease),true,512,JSON_THROW_ON_ERROR)['release'];
    $legacySource=$temporary.'/legacy-version-source';scaffoldRun(['git','clone','--quiet','--no-local','--no-checkout',$root,$legacySource]);scaffoldRun(['git','checkout','--quiet','--detach',$legacyIdentity['source_commit']],$legacySource);
    $legacyApp=$temporary.'/legacy-version-app';scaffoldFreshAdopted($legacySource,$nextRelease,$legacyApp);
    $legacyManifestPath=$legacyApp.'/.peanut/application-manifest.json';$legacyManifest=json_decode((string)file_get_contents($legacyManifestPath),true,512,JSON_THROW_ON_ERROR);
    scaffoldExpect(($legacyManifest['protocol']??null)==='peanut.application-scaffold.v1'&&!isset($legacyManifest['application']['version']),'v1.1.3 fixture must exercise the legacy manifest path');
    $legacyMetadataPath=$legacyApp.'/RELEASE_METADATA.json';$legacyMetadata=json_decode((string)file_get_contents($legacyMetadataPath),true,512,JSON_THROW_ON_ERROR);$legacyMetadata['version']='2.4.6';file_put_contents($legacyMetadataPath,json_encode($legacyMetadata,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");
    $legacyAppOwnedDigest=scaffoldOwnedTree($legacyApp,$legacyManifest,'app-owned');$legacyUniappDigest=hash_file('sha256',$legacyApp.'/uniapp/src/manifest.json');
    $legacyPlan=$runner->preflight($legacyApp,$nextRelease,$currentRelease);
    scaffoldExpect($legacyPlan['status']==='ready'&&($legacyPlan['identity']['application_version']??null)==='2.4.6','legacy manifest must uniquely adopt RELEASE_METADATA application version');
    $legacyApply=$runner->apply($legacyApp,scaffoldPlanPath($legacyApp,$legacyPlan));$legacyVerify=$runner->verify($legacyApp,scaffoldPlanPath($legacyApp,$legacyPlan));
    scaffoldExpect($legacyApply['status']==='applied'&&$legacyVerify['status']==='verified','v1.1.3 to v1.1.4 apply/verify must complete');
    $legacyApplied=json_decode((string)file_get_contents($legacyManifestPath),true,512,JSON_THROW_ON_ERROR);
    scaffoldExpect(($legacyApplied['schema_version']??null)===2&&($legacyApplied['protocol']??null)==='peanut.application-scaffold.v2'&&($legacyApplied['application']['version']??null)==='2.4.6','upgrade must normalize the application manifest without changing application.version');
    scaffoldExpect(hash_equals($legacyAppOwnedDigest,scaffoldOwnedTree($legacyApp,$legacyApplied,'app-owned')),'v1.1.4 upgrade must preserve all app-owned bytes');
    scaffoldExpect(hash_equals((string)$legacyUniappDigest,(string)hash_file('sha256',$legacyApp.'/uniapp/src/manifest.json')),'upgrade must preserve existing UniApp versionName/versionCode bytes');
    foreach(['web/package.json','pc/package.json','uniapp/package.json','server/config/project.php']as$versionPath)scaffoldExpect(str_contains((string)file_get_contents($legacyApp.'/'.$versionPath),'2.4.6'),'managed application version surface was not preserved: '.$versionPath);

    $ambiguousApp=$temporary.'/legacy-version-ambiguous';scaffoldFreshAdopted($legacySource,$nextRelease,$ambiguousApp);$ambiguousMetadataPath=$ambiguousApp.'/RELEASE_METADATA.json';$ambiguousMetadata=json_decode((string)file_get_contents($ambiguousMetadataPath),true,512,JSON_THROW_ON_ERROR);$ambiguousMetadata['application']=['version'=>'9.9.9'];file_put_contents($ambiguousMetadataPath,json_encode($ambiguousMetadata,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");
    scaffoldFails(fn()=>$runner->preflight($ambiguousApp,$nextRelease,$currentRelease),'SCAFFOLD_LEGACY_APPLICATION_VERSION_AMBIGUOUS');

    $currentIdentity=json_decode((string)file_get_contents($currentRelease),true,512,JSON_THROW_ON_ERROR)['release'];
    $runtimeSource=$temporary.'/runtime-source';scaffoldRun(['git','clone','--quiet','--no-local','--no-checkout',$root,$runtimeSource]);scaffoldRun(['git','checkout','--quiet','--detach',$currentIdentity['source_commit']],$runtimeSource);
    $runtimeApp=$temporary.'/runtime-app';scaffoldFreshAdopted($runtimeSource,$currentRelease,$runtimeApp);
    $runtimeAppOwnedPath='server/config/peanut.php';file_put_contents($runtimeApp.'/'.$runtimeAppOwnedPath,(string)file_get_contents($runtimeApp.'/'.$runtimeAppOwnedPath)."\n// v1.1.5 preservation proof\n");$runtimeAppOwnedDigest=hash_file('sha256',$runtimeApp.'/'.$runtimeAppOwnedPath);
    $runtimeBefore=scaffoldFileTree($runtimeApp);
    $runtimePlan=$runner->preflight($runtimeApp,$currentRelease,$runtimeRelease);scaffoldExpect($runtimePlan['status']==='ready'&&$runtimePlan['summary']['conflicts']===0,'v1.1.4 to v1.1.5 plan must be ready');
    $runtimeAutomatic=[];foreach($runtimePlan['actions']as$action)if(in_array($action['action'],['create','delete','replace','regenerate'],true))$runtimeAutomatic[$action['path']]=$action['action'];ksort($runtimeAutomatic,SORT_STRING);
    scaffoldExpect($runtimeAutomatic===['deploy/docker-compose.prod.yml'=>'replace','deploy/docker/nginx-select-admin.sh'=>'create','deploy/docker/production.Dockerfile'=>'replace','scripts/check-local-runtime-contract'=>'replace'],'v1.1.5 must contain only the production admin runtime compatibility actions');
    $runtimeApply=$runner->apply($runtimeApp,scaffoldPlanPath($runtimeApp,$runtimePlan));$runtimeVerify=$runner->verify($runtimeApp,scaffoldPlanPath($runtimeApp,$runtimePlan));
    scaffoldExpect($runtimeApply['status']==='applied'&&$runtimeVerify['status']==='verified','v1.1.4 to v1.1.5 apply/verify must complete');
    scaffoldExpect(hash_equals($runtimeAppOwnedDigest,(string)hash_file('sha256',$runtimeApp.'/'.$runtimeAppOwnedPath)),'v1.1.5 upgrade must preserve app-owned bytes');
    $runtimeDockerfile=(string)file_get_contents($runtimeApp.'/deploy/docker/production.Dockerfile');
    scaffoldExpect(str_contains($runtimeDockerfile,'VITE_DEPLOYMENT_MODE=standalone')&&str_contains($runtimeDockerfile,'VITE_DEPLOYMENT_MODE=multi-tenant')&&is_executable($runtimeApp.'/deploy/docker/nginx-select-admin.sh'),'v1.1.5 upgrade must install both admin bundles and the executable runtime selector');
    $runtimeRecover=$runner->recover($runtimeApp,scaffoldPlanPath($runtimeApp,$runtimePlan));scaffoldExpect($runtimeRecover['status']==='recovered'&&hash_equals($runtimeBefore,scaffoldFileTree($runtimeApp)),'v1.1.5 recovery must restore the exact v1.1.4 tree');

    $runtimeIdentity=json_decode((string)file_get_contents($runtimeRelease),true,512,JSON_THROW_ON_ERROR)['release'];
    $releaseCandidateSource=$temporary.'/release-candidate-source';scaffoldRun(['git','clone','--quiet','--no-local','--no-checkout',$root,$releaseCandidateSource]);scaffoldRun(['git','checkout','--quiet','--detach',$runtimeIdentity['source_commit']],$releaseCandidateSource);
    $releaseCandidateApp=$temporary.'/release-candidate-app';scaffoldFreshAdopted($releaseCandidateSource,$runtimeRelease,$releaseCandidateApp);
    $releaseCandidateAppOwnedPath='server/config/peanut.php';file_put_contents($releaseCandidateApp.'/'.$releaseCandidateAppOwnedPath,(string)file_get_contents($releaseCandidateApp.'/'.$releaseCandidateAppOwnedPath)."\n// v1.1.6 preservation proof\n");$releaseCandidateAppOwnedDigest=hash_file('sha256',$releaseCandidateApp.'/'.$releaseCandidateAppOwnedPath);
    $releaseCandidateBefore=scaffoldFileTree($releaseCandidateApp);$releaseCandidatePlan=$runner->preflight($releaseCandidateApp,$runtimeRelease,$releaseCandidate);
    scaffoldExpect($releaseCandidatePlan['status']==='ready'&&$releaseCandidatePlan['summary']['conflicts']===0,'v1.1.5 to v1.1.6 plan must be ready');
    $releaseCandidateAutomatic=[];foreach($releaseCandidatePlan['actions']as$action)if(in_array($action['action'],['create','delete','replace','regenerate'],true))$releaseCandidateAutomatic[$action['path']]=$action['action'];ksort($releaseCandidateAutomatic,SORT_STRING);
    scaffoldExpect($releaseCandidateAutomatic===['RELEASE_SBOM.spdx.json'=>'regenerate'],'v1.1.6 must only regenerate the lock-aligned SBOM');
    $releaseCandidateApply=$runner->apply($releaseCandidateApp,scaffoldPlanPath($releaseCandidateApp,$releaseCandidatePlan));$releaseCandidateVerify=$runner->verify($releaseCandidateApp,scaffoldPlanPath($releaseCandidateApp,$releaseCandidatePlan));
    scaffoldExpect($releaseCandidateApply['status']==='applied'&&$releaseCandidateVerify['status']==='verified','v1.1.5 to v1.1.6 apply/verify must complete');
    scaffoldExpect(hash_equals($releaseCandidateAppOwnedDigest,(string)hash_file('sha256',$releaseCandidateApp.'/'.$releaseCandidateAppOwnedPath)),'v1.1.6 upgrade must preserve app-owned bytes');
    $releaseCandidateRecover=$runner->recover($releaseCandidateApp,scaffoldPlanPath($releaseCandidateApp,$releaseCandidatePlan));scaffoldExpect($releaseCandidateRecover['status']==='recovered'&&hash_equals($releaseCandidateBefore,scaffoldFileTree($releaseCandidateApp)),'v1.1.6 recovery must restore the exact v1.1.5 tree');

    $releaseCandidateIdentity=json_decode((string)file_get_contents($releaseCandidate),true,512,JSON_THROW_ON_ERROR)['release'];
    $productReleaseSource=$temporary.'/product-release-source';scaffoldRun(['git','clone','--quiet','--no-local','--no-checkout',$root,$productReleaseSource]);scaffoldRun(['git','checkout','--quiet','--detach',$releaseCandidateIdentity['source_commit']],$productReleaseSource);
    $productReleaseApp=$temporary.'/product-release-app';scaffoldFreshAdopted($productReleaseSource,$releaseCandidate,$productReleaseApp);
    $productReleaseAppOwnedPath='server/config/peanut.php';file_put_contents($productReleaseApp.'/'.$productReleaseAppOwnedPath,(string)file_get_contents($productReleaseApp.'/'.$productReleaseAppOwnedPath)."\n// v1.1.7 preservation proof\n");$productReleaseAppOwnedDigest=hash_file('sha256',$productReleaseApp.'/'.$productReleaseAppOwnedPath);
    $productReleasePlan=$runner->preflight($productReleaseApp,$releaseCandidate,$productRelease);scaffoldExpect($productReleasePlan['status']==='ready'&&$productReleasePlan['summary']['conflicts']===0,'v1.1.6 to v1.1.7 plan must be ready');
    $productReleaseApply=$runner->apply($productReleaseApp,scaffoldPlanPath($productReleaseApp,$productReleasePlan));$productReleaseVerify=$runner->verify($productReleaseApp,scaffoldPlanPath($productReleaseApp,$productReleasePlan));
    scaffoldExpect($productReleaseApply['status']==='applied'&&$productReleaseVerify['status']==='verified','v1.1.6 to v1.1.7 apply/verify must complete');
    scaffoldExpect(hash_equals($productReleaseAppOwnedDigest,(string)hash_file('sha256',$productReleaseApp.'/'.$productReleaseAppOwnedPath)),'v1.1.7 upgrade must preserve app-owned bytes');

    $fromCheck=scaffoldRun(['php',$root.'/scripts/build-scaffold-release','--version=1.0.0','--source-commit='.SCAFFOLD_FROM_COMMIT,'--output='.$root.'/scaffold/releases/v1.0.0','--check']);
    $toManifest=json_decode((string)file_get_contents($toRelease),true,512,JSON_THROW_ON_ERROR);$toCheck=scaffoldRun(['php',$root.'/scripts/build-scaffold-release','--version=1.1.0','--source-commit='.$toManifest['release']['source_commit'],'--output='.$root.'/scaffold/releases/v1.1.0','--check']);
    $patchManifest=json_decode((string)file_get_contents($patchRelease),true,512,JSON_THROW_ON_ERROR);$patchCheck=scaffoldRun(['php',$root.'/scripts/build-scaffold-release','--version=1.1.1','--source-commit='.$patchManifest['release']['source_commit'],'--output='.$root.'/scaffold/releases/v1.1.1','--check']);
    $latestManifest=json_decode((string)file_get_contents($latestRelease),true,512,JSON_THROW_ON_ERROR);$latestCheck=scaffoldRun(['php',$root.'/scripts/build-scaffold-release','--version=1.1.2','--source-commit='.$latestManifest['release']['source_commit'],'--output='.$root.'/scaffold/releases/v1.1.2','--check']);
    $nextManifest=json_decode((string)file_get_contents($nextRelease),true,512,JSON_THROW_ON_ERROR);$nextCheck=scaffoldRun(['php',$root.'/scripts/build-scaffold-release','--version=1.1.3','--source-commit='.$nextManifest['release']['source_commit'],'--output='.$root.'/scaffold/releases/v1.1.3','--check']);
    $currentManifest=json_decode((string)file_get_contents($currentRelease),true,512,JSON_THROW_ON_ERROR);$currentCheck=scaffoldRun(['php',$root.'/scripts/build-scaffold-release','--version=1.1.4','--source-commit='.$currentManifest['release']['source_commit'],'--output='.$root.'/scaffold/releases/v1.1.4','--check']);
    $runtimeManifest=json_decode((string)file_get_contents($runtimeRelease),true,512,JSON_THROW_ON_ERROR);$runtimeCheck=scaffoldRun(['php',$root.'/scripts/build-scaffold-release','--version=1.1.5','--source-commit='.$runtimeManifest['release']['source_commit'],'--output='.$root.'/scaffold/releases/v1.1.5','--check']);
    $releaseCandidateManifest=json_decode((string)file_get_contents($releaseCandidate),true,512,JSON_THROW_ON_ERROR);$releaseCandidateCheck=scaffoldRun(['php',$root.'/scripts/build-scaffold-release','--version=1.1.6','--source-commit='.$releaseCandidateManifest['release']['source_commit'],'--output='.$root.'/scaffold/releases/v1.1.6','--check']);
    $productReleaseManifest=json_decode((string)file_get_contents($productRelease),true,512,JSON_THROW_ON_ERROR);$productReleaseCheck=scaffoldRun(['php',$root.'/scripts/build-scaffold-release','--version=1.1.7','--source-commit='.$productReleaseManifest['release']['source_commit'],'--output='.$root.'/scaffold/releases/v1.1.7','--check']);
    scaffoldExpect(str_contains($fromCheck,'verified')&&str_contains($toCheck,'verified')&&str_contains($patchCheck,'verified')&&str_contains($latestCheck,'verified')&&str_contains($nextCheck,'verified')&&str_contains($currentCheck,'verified')&&str_contains($runtimeCheck,'verified')&&str_contains($releaseCandidateCheck,'verified')&&str_contains($productReleaseCheck,'verified'),'all immutable release trees must exactly regenerate');
}finally{putenv('PEANUT_SCAFFOLD_FAIL_AFTER_REPLACEMENTS');scaffoldDelete($temporary);}

echo "SCAFFOLD-UPGRADE-E2E-001 passed\n";
