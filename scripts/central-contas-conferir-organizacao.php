<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||count($argv)!==4||!in_array($argv[3],['--preflight','--after'],true))exit(1);
try{
    [$script,$stage,$root,$mode]=$argv;
    $manifest=json_decode(file_get_contents($stage.'/organization-manifest.json'),true,16,JSON_THROW_ON_ERROR);
    if(($manifest['schema']??0)!==1||count($manifest['files']??[])!==20)throw new RuntimeException();
    $seen=[];
    foreach($manifest['files']as$file){
        $path=$file['path'];
        if(!preg_match('~^(?:app/services/mail/(?:providers/)?[A-Za-z]+\.php|app/helpers/mail_pilot\.php|app/actions/central_contas/message_update\.php|web/admin/central_contas/_[a-z_]+\.php|web/assets/(?:css/admin/central_contas\.css|js/central_contas\.js)|web/central-email/(?:privacidade|termos)\.html)$~D',$path)||isset($seen[$path]))throw new RuntimeException();
        $seen[$path]=true;
        foreach([$stage,$root]as$base){$part=$base;foreach(explode('/',$path)as$component){$part.='/'.$component;if(is_link($part))throw new RuntimeException();}}
        $hash=static fn(string $p):string=>hash('sha256',str_replace("\r\n","\n",file_get_contents($p)));
        if($hash($stage.'/'.$path)!==$file['sha256'])throw new RuntimeException();
        if(!is_file($root.'/'.$path)){
            if($mode==='--after'||!$file['allow_missing'])throw new RuntimeException();
        }elseif(!in_array($hash($root.'/'.$path),$mode==='--after'?[$file['sha256']]:$file['allowed'],true)){
            fwrite(STDERR,'production_file_conflict='.$path.PHP_EOL);exit(1);
        }
    }
    foreach(array_keys($seen)as$path)echo $path,PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,"Conferência de arquivos interrompida.\n");exit(1);}
