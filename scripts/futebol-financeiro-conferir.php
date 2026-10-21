<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);ini_set('display_errors','0');
try{
 [$script,$stage,$root,$mode]=$argv;if(!in_array($mode,['--preflight','--after'],true))throw new RuntimeException('mode');
 $manifest=json_decode(file_get_contents($stage.'/manifest.json'),true,32,JSON_THROW_ON_ERROR);$inventory=json_decode(file_get_contents($stage.'/inventory.json'),true,32,JSON_THROW_ON_ERROR);
 if(!$inventory||count($manifest['files'])<30)throw new RuntimeException('empty_release');$targets=[];
 foreach($manifest['files'] as $file){
  $path=$file['path'];
  if(!preg_match('~^(?:modules/(?:financeiro|financeiro_futebol)/(?:[a-zA-Z0-9_/-]+\.(?:php|json|sql)|README\.md)|bases/(?:futebol|financeiro)/(?:modules/(?:financeiro|financeiro_futebol)/(?:[a-zA-Z0-9_/-]+\.(?:php|json|sql)|README\.md)|web/admin/(?:financeiro|player)/[a-z_]+\.php)|bases/futebol/app/database/schema\.sql)$~D',$path))throw new RuntimeException('source_path:'.$path);
  $destinations=[$path];
  if(str_starts_with($path,'bases/futebol/')){
   $relative=substr($path,strlen('bases/futebol/'));
   foreach($inventory as $project){$project=$project['path'];if(!preg_match('~^projects/[a-zA-Z0-9_-]+$~D',$project)||!is_dir($root.'/'.$project.'/modules/financeiro')||!is_dir($root.'/'.$project.'/modules/jogadores'))throw new RuntimeException('project_parent');
    // The complete base schema is a template, never a project replacement.
    if($relative==='app/database/schema.sql')continue;
    $destinations[]=$project.'/'.$relative;
   }
  }
  $hash=static fn($p)=>hash('sha256',str_replace("\r\n","\n",file_get_contents($p)));
  if($hash($stage.'/'.$path)!==$file['sha256'])throw new RuntimeException('package_hash');
  foreach($destinations as $target){
   foreach([$root.'/'.$target,$stage.'/'.$path] as $candidate){$part='';foreach(explode('/',$candidate) as $component){$part.='/'.$component;if(is_link($part))throw new RuntimeException('symlink');}}
   if(!is_file($root.'/'.$target)){if($mode==='--after'||!$file['allow_missing'])throw new RuntimeException('missing:'.$target);}
   elseif(!in_array($hash($root.'/'.$target),$mode==='--after'?[$file['sha256']]:$file['allowed'],true))throw new RuntimeException('file_conflict:'.$target);
   $targets[$target]=$path;
  }
 }
 foreach($targets as $target=>$source)echo $source,"\t",$target,PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'finance_check_failed='.($e instanceof RuntimeException?$e->getMessage():'private_details_omitted').PHP_EOL);exit(1);}
