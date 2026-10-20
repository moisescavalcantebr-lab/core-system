<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__,2);$config=require $root.'/env/env.production.php';$db=$config['db'];
$server=new PDO('mysql:host='.$db['host'].';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$database='test_football_finance_'.bin2hex(random_bytes(5));
$fixture=sys_get_temp_dir().'/'.$database;
function ffCheck(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
function ffRoute(string $route,array $post=[],array $get=[],int $actor=991):string{
    global $fixture;
    $code='$actor='.(int)$actor.';$_POST='.var_export($post,true).';$_GET='.var_export($get,true).';$_FILES=[];$_SERVER["REQUEST_METHOD"]='.var_export($post?'POST':'GET',true).';require '.var_export($fixture.'/web/admin/financeiro/'.$route,true).';';
    $process=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);
    if($status!==0||$errors!=='')throw new RuntimeException('Falha na rota '.$route.': '.$errors);
    return $output;
}
$server->exec('CREATE DATABASE `'.$database.'`');
try{
    $pdo=new PDO('mysql:host='.$db['host'].';dbname='.$database.';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec(file_get_contents($root.'/bases/futebol/app/database/schema.sql'));
    $pdo->exec(file_get_contents($root.'/modules/financeiro/database/schema.sql'));
    $pdo->exec("INSERT INTO project_users(id,name,email,username,password,role,status) VALUES(991,'Gestor Teste','gestor@example.test','gestor','test','ADMIN','active'),(992,'Cliente Teste','cliente@example.test','cliente','test','CLIENT','active')");
    $pdo->exec("INSERT INTO players(id,name,status) VALUES(1,'Jogador sem login','active'),(2,'Jogador <dois>','inactive')");
    foreach(['app/bootstrap','app/helpers','app/views','web/admin/financeiro','modules/financeiro_futebol/database']as$dir)mkdir($fixture.'/'.$dir,0700,true);
    foreach(glob($root.'/modules/financeiro/web/admin/financeiro/*.php')as$file)copy($file,$fixture.'/web/admin/financeiro/'.basename($file));
    foreach(glob($root.'/modules/financeiro_futebol/web/admin/financeiro/*.php')as$file)copy($file,$fixture.'/web/admin/financeiro/'.basename($file));
    copy($root.'/modules/financeiro_futebol/module.json',$fixture.'/modules/financeiro_futebol/module.json');
    copy($root.'/modules/financeiro_futebol/database/schema.sql',$fixture.'/modules/financeiro_futebol/database/schema.sql');
    foreach(['project_auth.php','csrf.php','settings.php']as$file)copy($root.'/bases/futebol/app/helpers/'.$file,$fixture.'/app/helpers/'.$file);
    $connection='$pdo=new PDO('.var_export('mysql:host='.$db['host'].';dbname='.$database.';charset=utf8mb4',true).','.var_export($db['user'],true).','.var_export($db['pass'],true).',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);';
    file_put_contents($fixture.'/app/bootstrap/project_bootstrap.php','<?php ob_start();define("PROJECT_PATH",'.var_export($fixture,true).');define("APP_PATH",PROJECT_PATH."/app");define("PROJECT_URL","/projects/finance-test/web");$_SESSION=["project_user_id"=>$actor,"_csrf"=>"test"];'.$connection.'require APP_PATH."/helpers/project_auth.php";require APP_PATH."/helpers/csrf.php";require APP_PATH."/helpers/settings.php";function flash($type,$message){echo $message;}function flash_show(){}function redirect($url){echo "REDIRECT:".$url;exit;}');
    $css='';foreach(['core_base.css','admin/layout.css','admin/components.css','themes/dark.css']as$file)$css.=file_get_contents($root.'/bases/futebol/web/assets/css/'.$file);
    file_put_contents($fixture.'/app/views/layout_admin.php','<?php echo "<!doctype html><html><head><meta charset=utf-8><meta name=viewport content=\"width=device-width,initial-scale=1\"><style>".'.var_export($css,true).'."body{padding:12px;background:var(--bg-body);color:var(--text-primary);font-family:Arial,sans-serif}.c-content{margin:0;max-width:1100px}</style></head><body><main class=c-content>".$content."</main></body></html>";');
    file_put_contents($fixture.'/project.json',json_encode(['plan_name'=>'Plano Gratis','billing_cycle'=>'free']));
    $count=fn()=>(int)$pdo->query('SELECT COUNT(*) FROM finance_entries')->fetchColumn();
    $entry=fn(int $id)=>$pdo->query('SELECT * FROM finance_entries WHERE id='.$id)->fetch(PDO::FETCH_ASSOC);
    $history=fn(int $id)=>(int)$pdo->query('SELECT COUNT(*) FROM finance_team_history WHERE entry_id='.$id)->fetchColumn();
    $input=['_csrf'=>'test','type'=>'income','status'=>'pending','title'=>'Contribuicao do time','amount'=>'50.00','due_date'=>'2026-10-25','team_party_kind'=>'player','team_player_id'=>1];
    ffCheck(str_contains(ffRoute('store.php',[...$input,'_csrf'=>'wrong']),'CSRF'),'CSRF ignorado');
    ffCheck(str_contains(ffRoute('store.php',$input,[],992),'Acesso negado'),'Cliente gravou no caixa');
    ffCheck(str_contains(ffRoute('store.php',[...$input,'team_party_kind'=>'']),'Identifique'),'Origem em branco aceita');
    ffCheck(str_contains(ffRoute('store.php',[...$input,'team_player_id'=>999]),'nao encontrado'),'Jogador inventado aceito');
    ffCheck(str_contains(ffRoute('store.php',[...$input,'due_date'=>'2026-02-30']),'data'),'Data invalida aceita');
    ffCheck(str_contains(ffRoute('store.php',[...$input,'type'=>'balance_deposit']),'Selecione'),'Modo antigo burlou validacao');
    ffCheck(str_contains(ffRoute('store.php',[...$input,'status'=>'paid','paid_at'=>'']),'data'),'Confirmado sem data aceito');
    ffCheck($count()===0,'Tentativa invalida criou lancamento');
    ffRoute('store.php',$input);$id=(int)$pdo->lastInsertId();$id=(int)$pdo->query('SELECT MAX(id) FROM finance_entries')->fetchColumn();
    ffCheck($entry($id)['party_module']==='jogadores'&&(int)$entry($id)['party_id']===1&&$entry($id)['party_name']==='Jogador sem login','Vinculo ao jogador sem login incorreto');
    ffCheck($history($id)===1&&$entry($id)['status']==='pending','Historico inicial ausente');
    ffCheck((float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM finance_entries WHERE status='paid'")->fetchColumn()===0.0,'Pendente entrou no caixa');
    $pdo->exec("UPDATE players SET name='Jogador renomeado',status='inactive' WHERE id=1");
    ffRoute('update.php',[...$input,'title'=>'Contribuicao corrigida'],['id'=>$id]);
    ffCheck($entry($id)['party_name']==='Jogador sem login','Mudanca de nome alterou historico');
    ffCheck(str_contains(ffRoute('complete.php',['_csrf'=>'test','id'=>$id]),'edit.php'),'Confirmacao rapida ignorou data');
    ffCheck($entry($id)['status']==='pending','Confirmacao sem data alterou caixa');
    ffRoute('update.php',[...$input,'status'=>'paid','paid_at'=>'2026-10-20'],['id'=>$id]);
    ffCheck($entry($id)['status']==='paid'&&$history($id)===3,'Confirmacao sem historico');
    $before=$entry($id);ffRoute('update.php',[...$input,'amount'=>'100.00'],['id'=>$id]);ffRoute('delete.php',['_csrf'=>'test'],['id'=>$id]);
    ffCheck($entry($id)===$before,'Confirmado foi reescrito ou excluido');
    ffRoute('time_cancel.php',['_csrf'=>'test','id'=>$id,'reason'=>'']);ffCheck($entry($id)===$before,'Cancelamento sem motivo');
    ffRoute('time_cancel.php',['_csrf'=>'test','id'=>$id,'reason'=>'Registro duplicado']);
    ffCheck($entry($id)['status']==='canceled'&&$history($id)===4,'Cancelamento sem trilha');
    ffRoute('time_cancel.php',['_csrf'=>'test','id'=>$id,'reason'=>'Repetido']);ffCheck($history($id)===4,'Cancelamento repetido duplicou evento');
    $external=[...$input,'type'=>'expense','status'=>'paid','paid_at'=>'2026-10-20','team_party_kind'=>'external','team_party_name'=>'Loja <Teste>'];
    ffCheck(str_contains(ffRoute('store.php',[...$external,'team_party_name'=>'']),'nome'),'Destino em branco aceito');
    ffRoute('store.php',$external);$externalId=(int)$pdo->query('SELECT MAX(id) FROM finance_entries')->fetchColumn();
    ffCheck($entry($externalId)['party_name']==='Loja <Teste>'&&$entry($externalId)['party_id']===null,'Destino ou responsavel incorreto');
    ffCheck((int)$entry($externalId)['created_by_user_id']===991,'Responsavel nao registrado');
    $pdo->exec("INSERT INTO finance_entries(type,title,amount,status) VALUES('income','Legado sem origem',12,'paid')");$legacyId=(int)$pdo->lastInsertId();$legacy=$entry($legacyId);
    $view=ffRoute('time_elenco.php');ffCheck(str_contains($view,'Loja &lt;Teste&gt;')&&!str_contains($view,'Legado sem origem'),'Historico mistura legado ou permite HTML');
    ffCheck($entry($legacyId)===$legacy&&$history($legacyId)===0,'Legado reescrito');
    file_put_contents($root.'/_deploy/football-finance-form-preview.html',ffRoute('create.php'));
    file_put_contents($root.'/_deploy/football-finance-history-preview.html',$view);
    $pdo->exec("INSERT INTO finance_wallet_requests(user_id,amount,status) VALUES(992,25,'pending')");$requestId=(int)$pdo->lastInsertId();
    ffRoute('wallet_request_review.php',['_csrf'=>'test','action'=>'approve'],['id'=>$requestId]);
    ffCheck($pdo->query('SELECT status FROM finance_wallet_requests WHERE id='.$requestId)->fetchColumn()==='pending','Credito sem jogador identificado aprovado');
    $pdo->exec('UPDATE players SET user_id=992 WHERE id=2');
    ffRoute('wallet_request_review.php',['_csrf'=>'test','action'=>'approve'],['id'=>$requestId]);
    $walletId=(int)$pdo->query('SELECT MAX(id) FROM finance_entries')->fetchColumn();
    ffCheck($entry($walletId)['party_module']==='jogadores'&&(int)$entry($walletId)['party_id']===2&&$history($walletId)===1,'Credito aprovado sem origem ou trilha');
    $afterWalletCount=$count();ffRoute('wallet_request_review.php',['_csrf'=>'test','action'=>'approve'],['id'=>$requestId]);ffCheck($count()===$afterWalletCount,'Credito aprovado em duplicidade');
    file_put_contents($fixture.'/project.json',json_encode(['plan_name'=>'Plano Start','billing_cycle'=>'monthly']));
    $pdo->exec("INSERT INTO finance_categories(name,type,form_model) VALUES('Parcelamento teste','income','installment'),('Recorrencia teste','income','recurring')");
    foreach(['installment','recurring']as$model){
        $category=(int)$pdo->query("SELECT id FROM finance_categories WHERE form_model='$model' ORDER BY id DESC LIMIT 1")->fetchColumn();
        $beforeSeries=$count();ffRoute('store.php',[...$input,'category_id'=>$category,'installments_total'=>3,'recurrence_count'=>3]);
        ffCheck($count()===$beforeSeries+3,'Serie financeira perdeu parcelas');
        $series=$pdo->query('SELECT id FROM finance_entries ORDER BY id DESC LIMIT 3')->fetchAll(PDO::FETCH_COLUMN);
        foreach($series as$seriesId)ffCheck($history((int)$seriesId)===1,'Serie sem trilha completa');
    }
    $category=(int)$pdo->query("SELECT id FROM finance_categories WHERE form_model='installment' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $beforeSmall=$count();ffRoute('store.php',[...$input,'category_id'=>$category,'amount'=>'0.01','installments_total'=>3]);ffCheck($count()===$beforeSmall,'Criou parcelas zeradas');
    $beforeCount=$count();$pdo->exec("CREATE TRIGGER deny_team_audit BEFORE INSERT ON finance_team_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test rollback'");
    ffRoute('store.php',$input);ffCheck($count()===$beforeCount,'Falha de historico deixou gravacao parcial');
    $pendingId=(int)$pdo->query("SELECT id FROM finance_entries WHERE status='pending' ORDER BY id DESC LIMIT 1")->fetchColumn();$beforePending=$entry($pendingId);
    ffRoute('update.php',[...$input,'status'=>'paid','paid_at'=>'2026-10-20'],['id'=>$pendingId]);ffCheck($entry($pendingId)===$beforePending,'Falha de historico confirmou pagamento parcial');
    $pdo->exec('DROP TRIGGER deny_team_audit');
    rename($fixture.'/modules/financeiro_futebol/module.json',$fixture.'/modules/financeiro_futebol/module.disabled');
    ffCheck(!str_contains(ffRoute('create.php'),'team_party_kind'),'Outro projeto recebeu regras esportivas');
    ffRoute('store.php',['_csrf'=>'test','type'=>'income','title'=>'Entrada pessoal','amount'=>'5','status'=>'pending']);
    ffCheck($count()===$beforeCount+1,'Financeiro sem addon mudou comportamento');
    echo "PASS: identificacao, jogadores sem login, datas, CSRF/perfis, pendente/confirmado, snapshots, bloqueio de edicao/exclusao, cancelamento idempotente, legado e rollback atomico; modo geral preservado.\n";
}finally{
    if(!preg_match('/^test_football_finance_[a-f0-9]{10}$/D',$database))throw new RuntimeException('Banco de teste invalido');
    $server->exec('DROP DATABASE `'.$database.'`');
    if(is_dir($fixture)){$files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($files as$file)$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());rmdir($fixture);}
}
