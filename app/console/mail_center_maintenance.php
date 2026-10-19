<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__,2);define('ROOT_PATH',$root);define('APP_PATH',$root.'/app');require APP_PATH.'/helpers/mail_center.php';require APP_PATH.'/helpers/mail_pilot.php';require APP_PATH.'/services/mail/MailAccountService.php';
try{
    $config=require $root.'/env/env.production.php';$d=$config['db'];$pdo=new PDO('mysql:host='.$d['host'].';dbname='.$d['name'].';charset=utf8mb4',$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$pdo->exec("SET time_zone='+00:00'");$owner=(int)($config['mail_center']['owner_user_id']??0);
    if(mailCenterOwnerId($pdo,$config,['core_user'=>['id'=>$owner]])===null)throw new DomainException('Proprietário indisponível.');
    if(!in_array('--apply',$argv,true)){echo 'Simulação: expurgar auditoria/metadados antigos sem marcações, liberar snapshots concluídos e marcar envios interrompidos como incertos. Nenhum reenvio.'.PHP_EOL;exit;}
    $runtime=mailPilotRuntime($pdo,$config,$owner);$private=MailPilotConfig::load($config,$root);$directory=realpath($private['attachments_dir']);
    $pdo->prepare("UPDATE core_mail_outbox SET status='unknown',failure_code='interrupted',lease_until=NULL,updated_at=UTC_TIMESTAMP() WHERE owner_user_id=? AND status='sending' AND lease_until<UTC_TIMESTAMP()")->execute([$owner]);
    $s=$pdo->prepare("SELECT a.* FROM core_mail_attachments a WHERE a.owner_user_id=? AND a.draft_id IS NOT NULL AND a.expires_at<UTC_TIMESTAMP() AND NOT EXISTS(SELECT 1 FROM core_mail_outbox o WHERE o.draft_id=a.draft_id AND o.status IN ('queued','sending','unknown')) LIMIT 100");$s->execute([$owner]);
    foreach($s->fetchAll(PDO::FETCH_ASSOC)as$file){if(!preg_match('/^[a-f0-9]{32}$/D',(string)$file['file_token']))continue;$path=$directory.'/'.$file['file_token'].'.enc';if(is_link($path))continue;if(is_file($path)&&!unlink($path))continue;$pdo->prepare('DELETE FROM core_mail_attachments WHERE id=? AND owner_user_id=?')->execute([$file['id'],$owner]);}
    $pdo->prepare("UPDATE core_mail_outbox SET snapshot_envelope=NULL WHERE owner_user_id=? AND status IN ('accepted','confirmed','failed') AND updated_at<UTC_TIMESTAMP()-INTERVAL 7 DAY")->execute([$owner]);
    $pdo->prepare("DELETE FROM core_mail_outbox WHERE owner_user_id=? AND status IN ('accepted','confirmed','failed') AND updated_at<UTC_TIMESTAMP()-INTERVAL 90 DAY")->execute([$owner]);
    (new MailLocalOrganizationService($pdo,$owner))->purgeOldUnmarkedMetadata();
    $pdo->prepare('DELETE FROM core_mail_audit_events WHERE owner_user_id=? AND created_at<UTC_TIMESTAMP()-INTERVAL 90 DAY')->execute([$owner]);
    echo 'Manutenção concluída. Nenhuma mensagem remota excluída; nenhum envio executado.'.PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Manutenção não concluída; confira configuração e permissões. Detalhes omitidos.'.PHP_EOL);exit(1);}
