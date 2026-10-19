<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__,2);$args=getopt('',['stage:']);$stage=realpath((string)($args['stage']??''));
if($stage===false||!str_starts_with($stage,$root.'/_deploy/'))throw new RuntimeException('Staging de teste inválido.');
$checker=$root.'/app/console/check_mail_release.php';$checks=0;
function releaseResult(string $checker,string $stage,bool $installed=false):int{$cmd=[PHP_BINARY,$checker,'--root='.$stage];if($installed)$cmd[]='--installed';$p=proc_open($cmd,[1=>['pipe','w'],2=>['pipe','w']],$pipes);foreach($pipes as$pipe){stream_get_contents($pipe);fclose($pipe);}return proc_close($p);}
function releaseExpect(bool $condition):void{global$checks;$checks++;if(!$condition)throw new RuntimeException('Regressão na verificação de release.');}
$lock=file_get_contents($stage.'/composer.lock');$installed=file_get_contents($stage.'/vendor/composer/installed.json');$sql=$stage.'/app/database/mail_center_004_reading.sql';
try{
    releaseExpect(releaseResult($checker,$stage)===0);
    file_put_contents($stage.'/composer.lock',$lock."\n");releaseExpect(releaseResult($checker,$stage)!==0);file_put_contents($stage.'/composer.lock',$lock);
    $data=json_decode($installed,true,512,JSON_THROW_ON_ERROR);$data['dev']=true;file_put_contents($stage.'/vendor/composer/installed.json',json_encode($data));releaseExpect(releaseResult($checker,$stage)!==0);file_put_contents($stage.'/vendor/composer/installed.json',$installed);
    $data=json_decode($installed,true,512,JSON_THROW_ON_ERROR);$data['packages'][0]['version']='wrong-version';file_put_contents($stage.'/vendor/composer/installed.json',json_encode($data));releaseExpect(releaseResult($checker,$stage)!==0);file_put_contents($stage.'/vendor/composer/installed.json',$installed);
    rename($sql,$sql.'.release-test');releaseExpect(releaseResult($checker,$stage)!==0);rename($sql.'.release-test',$sql);
    if(file_exists($stage.'/env'))throw new RuntimeException('Staging já contém ENV.');mkdir($stage.'/env',0700);releaseExpect(releaseResult($checker,$stage)!==0);releaseExpect(releaseResult($checker,$stage,true)===0);rmdir($stage.'/env');
    releaseExpect(releaseResult($checker,$stage)===0);
    echo 'PASS: '.$checks.' verificações de release; lock adulterado, dev, versão divergente, migração ausente e ENV no pacote recusados. Apenas staging local exercitado.'.PHP_EOL;
}finally{
    file_put_contents($stage.'/composer.lock',$lock);file_put_contents($stage.'/vendor/composer/installed.json',$installed);
    if(is_file($sql.'.release-test'))rename($sql.'.release-test',$sql);
    if(is_dir($stage.'/env'))rmdir($stage.'/env');
}
