<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__,2);$args=getopt('',['stage:']);$stage=realpath((string)($args['stage']??''));
if(!$stage||!str_starts_with($stage,$root.'/_deploy/mail-core-overlay-'))exit(1);
$checker=$root.'/app/console/check_mail_overlay.php';$manifest=file_get_contents($stage.'/mail-overlay.json');$data=json_decode($manifest,true,512,JSON_THROW_ON_ERROR);$checks=0;
$target=sys_get_temp_dir().'/mail-overlay-target-'.bin2hex(random_bytes(6));mkdir($target,0700);
function overlayStatus(string $checker,string $stage,?string $target=null):int{$cmd=[PHP_BINARY,$checker,'--root='.$stage];if($target)$cmd[]='--target='.$target;$p=proc_open($cmd,[1=>['pipe','w'],2=>['pipe','w']],$pipes);foreach($pipes as$pipe){stream_get_contents($pipe);fclose($pipe);}return proc_close($p);}
function overlayCheck(bool $ok):void{global $checks;$checks++;if(!$ok)throw new RuntimeException('Verificação do overlay falhou.');}
try{
    overlayCheck(overlayStatus($checker,$stage)===0);
    overlayCheck(overlayStatus($checker,$stage,$target)===0);
    file_put_contents($target.'/.htaccess','production-divergence');overlayCheck(overlayStatus($checker,$stage,$target)===2);unlink($target.'/.htaccess');
    $changed=$data;$changed['files'][0]['sha256']=str_repeat('0',64);file_put_contents($stage.'/mail-overlay.json',json_encode($changed));overlayCheck(overlayStatus($checker,$stage)!==0);
    $changed=$data;$changed['files'][0]['path']='../outside';file_put_contents($stage.'/mail-overlay.json',json_encode($changed));overlayCheck(overlayStatus($checker,$stage)!==0);
    file_put_contents($stage.'/mail-overlay.json',$manifest);file_put_contents($stage.'/unexpected.txt','synthetic');overlayCheck(overlayStatus($checker,$stage)!==0);unlink($stage.'/unexpected.txt');
    overlayCheck(overlayStatus($checker,$stage)===0);
    echo 'PASS: '.$checks.' verificações de overlay; integridade, caminhos, arquivos extras e divergência de produção; apenas staging sintético.'.PHP_EOL;
}finally{file_put_contents($stage.'/mail-overlay.json',$manifest);if(is_file($stage.'/unexpected.txt'))unlink($stage.'/unexpected.txt');if(is_file($target.'/.htaccess'))unlink($target.'/.htaccess');rmdir($target);}
