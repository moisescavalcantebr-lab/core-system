<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!isset($viewRoot,$orgLatest,$root))exit(1);
$actionDir=$viewRoot.'/app/actions/central_contas';mkdir($actionDir,0700,true);foreach(['message_mark','preferences_save']as$action)copy($root.'/app/actions/central_contas/'.$action.'.php',$actionDir.'/'.$action.'.php');
function organizationRoute(string $file,array $post,string $method='POST'):array{
    $code='$_SERVER["REQUEST_METHOD"]='.var_export($method,true).';$_SERVER["HTTP_ACCEPT"]="application/json";$_SERVER["REQUEST_URI"]="/app/actions/central_contas/message_mark.php";$_POST='.var_export($post,true).';register_shutdown_function(function(){fwrite(STDERR,"LOCAL_RESULT:".(http_response_code()?:200));});require '.var_export($file,true).';';
    $process=proc_open([PHP_BINARY,'-d','display_errors=1','-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$body=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);pilotExpect(proc_close($process)===0&&!preg_match('/Warning:|Fatal error:/',$body.$error),'Falha em ação local isolada');preg_match('/LOCAL_RESULT:(\d+)/',$error,$m);return ['status'=>(int)($m[1]??0),'body'=>$body];
}
$payloadOrg=['_csrf'=>'synthetic-csrf','message_id'=>$orgLatest['id'],'mark'=>'favorite:1','return_account'=>0,'return_folder'=>'all','return_mark'=>'all'];
pilotExpect(organizationRoute($actionDir.'/message_mark.php',$payloadOrg,'GET')['status']===405,'Marcação permitiu GET');
pilotExpect(organizationRoute($actionDir.'/message_mark.php',[...$payloadOrg,'_csrf'=>'bad'])['status']===403,'Marcação ignorou CSRF');
$result=organizationRoute($actionDir.'/message_mark.php',$payloadOrg);pilotExpect($result['status']===200&&(int)json_decode($result['body'],true)['marks']['is_favorite']===1,'Ação local não salvou favorito');
pilotExpect(organizationRoute($actionDir.'/message_mark.php',[...$payloadOrg,'mark'=>'evil:1'])['status']===422,'Marcação inválida passou pela rota');
pilotExpect(organizationRoute($actionDir.'/message_mark.php',[...$payloadOrg,'return_account'=>$other])['status']===404,'Contexto cruzado de caixa aceito');
pilotExpect(organizationRoute($actionDir.'/preferences_save.php',['_csrf'=>'bad','highlight_color'=>'#f59e0b'])['status']===403,'Preferências ignoraram CSRF');
pilotExpect(organizationRoute($actionDir.'/preferences_save.php',['_csrf'=>'synthetic-csrf','highlight_color'=>'#f59e0b'])['status']===303,'Preferência não retornou às configurações');
$result=organizationRoute($actionDir.'/preferences_save.php',['_csrf'=>'synthetic-csrf','highlight_color'=>'#f59e0b','reading_mode'=>'full']);
pilotExpect($result['status']===303&&$org->preferences()['reading_mode']==='full','Rota não persistiu leitura completa');
$org->savePreferences('#f59e0b','panel');
