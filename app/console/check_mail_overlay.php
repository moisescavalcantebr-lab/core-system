<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
try {
    $args=getopt('',['root:','target:']);$root=realpath((string)($args['root']??''));$target=isset($args['target'])?realpath((string)$args['target']):false;
    if(!$root)throw new RuntimeException('root');
    $manifest=json_decode((string)file_get_contents($root.'/mail-overlay.json'),true,512,JSON_THROW_ON_ERROR);
    if(($manifest['schema']??null)!==1||($manifest['mode']??'')!=='mail-center-only-disabled'||!is_array($manifest['files']??null))throw new RuntimeException('manifest');
    $shared=['.htaccess','app/bootstrap/bootstrap.php','app/services/auth/AuthService.php','app/views/layout_admin.php','app/views/partials/header.php','app/views/partials/sidebar.php'];
    $seen=[];$conflicts=0;
    foreach($manifest['files'] as $file){
        $path=$file['path']??'';$hash=$file['sha256']??'';
        if(!is_string($path)||str_contains($path,'\\')||preg_match('#(^|/)\.\.?(/|$)#',$path)||!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new RuntimeException('path');
        $allowed=in_array($path,$shared,true)||in_array($path,['app/helpers/mail_center.php','app/helpers/mail_pilot.php','app/helpers/session.php','app/views/partials/mail_folders.php','composer.json','composer.lock','mail-release.json','web/assets/css/admin/central_contas.css','web/assets/js/central_contas.js','web/assets/js/Sortable-1.15.0.min.js'],true)
            ||preg_match('#^(vendor/|app/actions/central_contas/|app/services/mail/|web/admin/central_contas/|web/central-email/)#',$path)
            ||preg_match('#^app/console/(?:check_mail_|inspect_mail_|mail_center_|migrate_mail_center|repair_mail_headers)[a-z_]*\.php$#D',$path)
            ||preg_match('#^app/database/mail_center_00[1-4]_[a-z_]+\.sql$#D',$path);
        if(!$allowed||isset($seen[$path])||is_link($root.'/'.$path)||!is_file($root.'/'.$path)||!hash_equals($hash,hash_file('sha256',$root.'/'.$path)))throw new RuntimeException('integrity');
        $seen[$path]=true;
        if($target&&is_file($target.'/'.$path)&&!str_starts_with($path,'vendor/')){
            $current=(string)file_get_contents($target.'/'.$path);$next=(string)file_get_contents($root.'/'.$path);
            $normalized=hash('sha256',str_replace("\r\n","\n",$current));
            $nextHash=hash('sha256',str_replace("\r\n","\n",$next));
            $baseline=$manifest['shared_baselines'][$path]??null;
            if($normalized!==$nextHash&&(!in_array($path,$shared,true)||!is_string($baseline)||$normalized!==$baseline)){
                echo 'overlay_conflict='.$path.PHP_EOL;$conflicts++;
            }
        }
    }
    foreach($shared as $path)if(!isset($seen[$path])||!preg_match('/^[a-f0-9]{64}$/D',(string)($manifest['shared_baselines'][$path]??'')))throw new RuntimeException('baseline');
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS))as$entry){
        if($entry->isLink())throw new RuntimeException('symlink');
        if($entry->isFile()){$path=substr($entry->getPathname(),strlen($root)+1);if($path!=='mail-overlay.json'&&!isset($seen[$path]))throw new RuntimeException('extra');}
    }
    echo 'overlay_files_verified='.count($seen).PHP_EOL;
    echo 'overlay_unrelated_modules=excluded'.PHP_EOL;
    echo 'overlay_production_conflicts='.$conflicts.PHP_EOL;
    if($conflicts)exit(2);
    echo 'overlay_integrity=PASS'.PHP_EOL;
}catch(Throwable $error){fwrite(STDERR,"Overlay inválido; detalhes e conteúdo omitidos.\n");exit(1);}
