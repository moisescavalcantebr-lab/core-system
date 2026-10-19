<?php
declare(strict_types=1);
// Included only by the isolated pilot suite; no production test hooks.
if(PHP_SAPI!=='cli'||!isset($database,$private,$root,$key,$failedId,$message,$unknown))exit(1);
$viewRoot=$private.'/views-fixture';
foreach(['app/bootstrap','app/helpers','app/views/partials','web/admin/central_contas']as$dir)mkdir($viewRoot.'/'.$dir,0700,true);
foreach(glob($root.'/web/admin/central_contas/*.php')as$file)copy($file,$viewRoot.'/web/admin/central_contas/'.basename($file));
copy($root.'/app/views/layout_admin.php',$viewRoot.'/app/views/layout_admin.php');foreach(glob($root.'/app/views/partials/*.php')as$file)copy($file,$viewRoot.'/app/views/partials/'.basename($file));
copy($root.'/app/helpers/quick_links.php',$viewRoot.'/app/helpers/quick_links.php');
$source=(string)file_get_contents(__DIR__.'/mail_center_pilot_test.php');$classes=substr($source,strpos($source,'final class PilotHttp'),strpos($source,'$config=require')-strpos($source,'final class PilotHttp'));
file_put_contents($viewRoot.'/mocks.php','<?php '.$classes.' class ViewOAuth extends MailOAuthService { public function token(int $account): string { return "synthetic-access"; } }');
$stub= <<<'PHP'
<?php
function mailPilotLoadCode():void{}
function mailPilotReady(PDO $pdo,array $config):bool{return $GLOBALS['fixtureReady']??true;}
function mailPilotRuntime(PDO $pdo,array $config,int $owner):array{return $GLOBALS['fixtureRuntime'];}
function mailPilotStatus(string $state):string{return match($state){'confirmed'=>'Registrado nos enviados','accepted'=>'Aceito pelo Gmail','unknown'=>'Resultado incerto — confira antes de reenviar',default=>'Falha no envio'};}
function mailPilotError(Throwable $error,string $location=''):never{if($error instanceof OutOfBoundsException){http_response_code(404);exit('Registro indisponível.');}http_response_code(500);exit('Fixture render error: '.$error->getMessage());}
PHP;
file_put_contents($viewRoot.'/app/helpers/mail_pilot.php',$stub);
$bootstrap='<?php $source='.var_export($root,true).';require $source."/vendor/autoload.php";define("ROOT_PATH",$source);define("APP_PATH",'.var_export($viewRoot.'/app',true).');define("PUBLIC_PATH",$source."/web");define("STORAGE_PATH",'.var_export($private.'/empty-storage',true).');foreach(["mail_center","csrf","flash","http","ui_helper"]as$helper)require_once $source."/app/helpers/".$helper.".php";require $source."/app/services/mail/MailAccountService.php";foreach(["MailProviderException","MailTokenCipher","MailPilotConfig","MailHttpClient","MailProviderInterface","MailMessageService","MailLocalOrganizationService","MailOAuthService","MailSyncService","MailHtmlRenderer","MailAttachmentService","MailSendService"]as$file)require_once $source."/app/services/mail/".$file.".php";require '.var_export($viewRoot.'/mocks.php',true).';$real=require $source."/env/env.production.php";$d=$real["db"];$pdo=new PDO("mysql:host=".$d["host"].";dbname='.$database.';charset=utf8mb4",$d["user"],$d["pass"],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$config=["mail_center"=>["enabled"=>true,"owner_user_id"=>1]];$_SESSION=["core_user"=>["id"=>1,"name"=>"Dono","email"=>"owner@example.test","role"=>"SUPER_ADMIN"],"_csrf"=>"synthetic-csrf","core_authenticated_at"=>time()];$cipher=new MailTokenCipher(["v1"=>'.var_export($key,true).'],"v1");$store=new MailMessageService($pdo,1,$cipher);$api=new PilotApi();$oauth=new ViewOAuth($store,$api,[],new PilotHttp());$attachments=new MailAttachmentService($store,$oauth,$api,'.var_export($private.'/attachments',true).',$source);$GLOBALS["fixtureRuntime"]=["store"=>$store,"api"=>$api,"oauth"=>$oauth,"attachments"=>$attachments,"send"=>new MailSendService($store,$oauth,$api,$attachments)];$coreSettings=["app_name"=>"Core","app_logo"=>"","app_favicon"=>""];$theme="light";';
$bootstrap.='$config["app_url"]="https://core.example.test";$config["mail_center"]["secrets_file"]='.var_export($private.'/secrets.json',true).';require $source."/app/helpers/session.php";coreConfigureSession($config);$_SERVER["HTTPS"]="on";$_SERVER["HTTP_HOST"]="core.example.test";';
file_put_contents($viewRoot.'/app/bootstrap/bootstrap.php',$bootstrap);
$pdo->prepare("UPDATE core_mail_outbox SET status='unknown' WHERE id=?")->execute([$unknown['id']]);
$cases=['inbox'=>['messages.php',['account'=>$account]],'message'=>['message.php',['id'=>$message['id']]],'compose'=>['compose.php',['account'=>$account]],'review'=>['compose.php',['draft'=>$failedId]],'unknown'=>['compose.php',['outbox'=>$unknown['id']]],'setup'=>['messages.php',[],false]];
$cases['unified']=['unified.php',[]];
$cases['preferences']=['accounts.php',[]];
$cases['message-next']=['message.php',['id'=>(int)$navRows[0]['id']]];
$cases['preview']=['message.php',['id'=>$message['id'],'preview'=>'1']];
$cases['preview-unified']=['message.php',['id'=>$message['id'],'preview'=>'1','source'=>'unified']];
$cases['inbox-full']=['messages.php',['account'=>$account]];
$viewReadId=(int)$navRows[1]['id'];$viewOriginalUnread=(int)$store->message($viewReadId)['is_unread'];$pdo->prepare('UPDATE core_mail_messages SET is_unread=0 WHERE id=? AND owner_user_id=1')->execute([$viewReadId]);
foreach($cases as$name=>$case){
    if(in_array($name,['unified','inbox-full'],true))(new MailLocalOrganizationService($pdo,1))->savePreferences('#f59e0b','full');
    $code='$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["REQUEST_URI"]="/web/admin/central_contas/messages.php";$_GET='.var_export($case[1],true).';$GLOBALS["fixtureReady"]='.var_export($case[2]??true,true).';require '.var_export($viewRoot.'/web/admin/central_contas/'.$case[0],true).';';
    $process=proc_open([PHP_BINARY,'-d','display_errors=1','-d','error_reporting=32767','-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$html=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    pilotExpect(proc_close($process)===0&&!preg_match('/Warning:|Fatal error:|Fixture render error/',$html.$errors),'Falha em view '.$name.': '.$errors);
    pilotExpect(!str_contains($html,'cdn.jsdelivr.net')&&!str_contains($html,'synthetic-access')&&!str_contains($html,$key),'View expõe segredo/script remoto');
    if($name==='preview')pilotExpect(str_contains($html,'data-mail-message-preview')&&!str_contains($html,'<!DOCTYPE')&&!str_contains($html,'c-sidebar'),'Prévia não retornou apenas a mensagem protegida');
    if($name==='unified'){pilotExpect(str_contains($html,'data-mail-reading-mode="panel"'),'Visão unificada herdou leitura completa');(new MailLocalOrganizationService($pdo,1))->savePreferences('#f59e0b','panel');}
    if($name==='inbox-full'){pilotExpect(str_contains($html,'data-mail-reading-mode="full"'),'Conta individual perdeu preferência completa');(new MailLocalOrganizationService($pdo,1))->savePreferences('#f59e0b','panel');}
    if($name==='preview-unified')pilotExpect(!str_contains($html,'Abrir mensagem completa'),'Unificada oferece página completa');
    if($name==='message')pilotExpect(!str_contains($html,'class="mail-nav"')&&!str_contains($html,'Abrir mensagem completa')&&str_contains($html,'Próxima mensagem'),'Leitura individual com menu repetido ou sem próxima');
    if($name==='message-next')pilotExpect(str_contains($html,'href="message.php?id='.(int)$navRows[1]['id'].'&amp;folder=0&amp;mark=all"')&&!str_contains($html,'class="mail-nav"'),'Botão próxima não aponta à próxima mensagem da conta');
    if($name==='preferences')pilotExpect(str_contains($html,'Voltar para Central de E-Mail')||str_contains($html,'Voltar para Central de E-mail'),'Configurações sem retorno à Central');
    if(!is_dir($root.'/_deploy'))mkdir($root.'/_deploy',0700,true);file_put_contents($root.'/_deploy/mail-pilot-'.$name.'.html',$html);
}
$pdo->prepare('UPDATE core_mail_messages SET is_unread=? WHERE id=? AND owner_user_id=1')->execute([$viewOriginalUnread,$viewReadId]);


