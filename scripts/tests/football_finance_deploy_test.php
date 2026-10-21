<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$stage=realpath($argv[1]??'');$root=dirname(__DIR__,2);
if(!$stage||!str_starts_with($stage,$root.'/_deploy/futebol-financeiro-release-'))throw new RuntimeException('Pacote invalido');
$fixture=sys_get_temp_dir().'/test_ff_release_'.bin2hex(random_bytes(5));mkdir($fixture,0700);
function releaseTest(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function releaseCheck(string $mode):array{global $stage,$fixture;$p=proc_open([PHP_BINARY,$stage.'/check.php',$stage,$fixture,$mode],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return[proc_close($p),$out,$error];}
try{
 $manifest=json_decode(file_get_contents($stage.'/manifest.json'),true,32,JSON_THROW_ON_ERROR);
 file_put_contents($stage.'/inventory.json',json_encode([['path'=>'projects/test_team']]));
 foreach(['financeiro','jogadores'] as $parent)mkdir($fixture.'/projects/test_team/modules/'.$parent,0700,true);
 foreach($manifest['files'] as $file){if($file['allow_missing'])continue;$dest=$fixture.'/'.$file['path'];if(!is_dir(dirname($dest)))mkdir(dirname($dest),0700,true);copy($stage.'/'.$file['path'],$dest);
  if(str_starts_with($file['path'],'bases/futebol/')&&$file['path']!=='bases/futebol/app/database/schema.sql'){$dest=$fixture.'/projects/test_team/'.substr($file['path'],strlen('bases/futebol/'));if(!is_dir(dirname($dest)))mkdir(dirname($dest),0700,true);copy($stage.'/'.$file['path'],$dest);}
 }
 [$code,$mapping,$error]=releaseCheck('--preflight');releaseTest($code===0&&$error==='','Preflight rejeitou pacote valido');
 releaseTest(str_contains($mapping,"projects/test_team/modules/financeiro_futebol/module.json"),'Addon nao seria instalado no projeto');
 releaseTest(!str_contains($mapping,"\tprojects/test_team/app/database/schema.sql"),'Schema completo substituiria o projeto');
 releaseTest(releaseCheck('--after')[0]!==0,'Verificacao final aceitou arquivos faltantes');
 foreach(explode("\n",trim($mapping)) as $line){[$source,$target]=explode("\t",$line);$dest=$fixture.'/'.$target;if(!is_dir(dirname($dest)))mkdir(dirname($dest),0700,true);copy($stage.'/'.$source,$dest);}
 releaseTest(releaseCheck('--after')[0]===0,'Hashes finais rejeitados');
 $target=$fixture.'/projects/test_team/web/admin/financeiro/helpers.php';$before=file_get_contents($target);file_put_contents($target,$before."\n// unexpected production change\n");releaseTest(releaseCheck('--preflight')[0]!==0,'Arquivo divergente seria sobrescrito');file_put_contents($target,$before);
 unlink($target);symlink($stage.'/modules/financeiro/web/admin/financeiro/helpers.php',$target);releaseTest(releaseCheck('--preflight')[0]!==0,'Symlink aceito');unlink($target);file_put_contents($target,$before);
 file_put_contents($stage.'/inventory.json','[]');releaseTest(releaseCheck('--preflight')[0]!==0,'Pacote sem projeto aceito');
 echo 'PASS: deploy restrito, instalacao do addon, arquivos faltantes, hashes, divergencias e symlinks; schema completo nunca substitui projeto.',PHP_EOL;
}finally{
 if(!preg_match('~^'.preg_quote(sys_get_temp_dir(), '~').'/test_ff_release_[a-f0-9]{10}$~D',$fixture))throw new RuntimeException('Fixture invalida');
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $file){if($file->isLink()||!$file->isDir())unlink($file->getPathname());else rmdir($file->getPathname());}rmdir($fixture);
}
