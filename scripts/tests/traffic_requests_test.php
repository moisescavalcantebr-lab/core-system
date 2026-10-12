<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$root=dirname(__DIR__,2);
require $root.'/app/services/TrafficRequestService.php';
require $root.'/app/services/ContentStudioService.php';
function expect(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
function rejects(callable $action,string $message): void {
    try { $action(); } catch (RuntimeException|InvalidArgumentException $e) { return; }
    throw new RuntimeException($message);
}
function copyTree(string $from,string $to): void {
    if (!is_dir($to)) mkdir($to,0700,true);
    foreach (new DirectoryIterator($from) as $entry) {
        if ($entry->isDot()) continue;
        $dest=$to.'/'.$entry->getFilename();
        $entry->isDir() ? copyTree($entry->getPathname(),$dest) : copy($entry->getPathname(),$dest);
    }
}
function route(string $file,array $post=[],array $get=[],int $user=1,string $role='ADMIN'): string {
    $code='$_SERVER["REQUEST_METHOD"]='.var_export($post?'POST':'GET',true).';$_SERVER["REQUEST_URI"]="/requests.php";$_SERVER["PHP_SELF"]="request.php";$_POST='.var_export($post,true).';$_GET='.var_export($get,true).';$testUser='.$user.';$testRole='.var_export($role,true).';require '.var_export($file,true).';';
    $proc=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    expect(proc_close($proc)===0,'Falha na rota: '.$error);
    expect(!str_contains($out,'Warning:')&&!str_contains($out,'Fatal error:'),'Aviso na rota: '.$out);
    return $out;
}
$config=require $root.'/env/env.production.php'; $db=$config['db'];
$pdo=new PDO('mysql:host='.$db['host'].';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$database='test_traffic_'.bin2hex(random_bytes(6)); $fixture=sys_get_temp_dir().'/'.$database;
$pdo->exec('CREATE DATABASE `'.$database.'`');
try {
    $pdo->exec('USE `'.$database.'`');
    $pdo->exec("CREATE TABLE projects (id INT UNSIGNED PRIMARY KEY,name VARCHAR(160),slug VARCHAR(160),status VARCHAR(20) DEFAULT 'active')");
    $pdo->exec("INSERT INTO projects(id,name,slug) VALUES (1,'Projeto A','project-a'),(2,'Projeto B','project-b')");
    $pdo->exec("CREATE TABLE project_users (id INT PRIMARY KEY,name VARCHAR(140),email VARCHAR(180),username VARCHAR(120),role VARCHAR(20),status ENUM('active','inactive','blocked') DEFAULT 'active',avatar VARCHAR(100),must_change_password INT)");
    $pdo->exec("INSERT INTO project_users VALUES (1,'Cliente','cliente@example.com','cliente','ADMIN','active','',0),(2,'Usuario','user@example.com','user','USER','active','',0),(3,'Inativo','inactive@example.com','inactive','ADMIN','inactive','',0),(4,'Bloqueado','blocked@example.com','blocked','ADMIN','blocked','',0)");
    TrafficRequestService::ensureSchema($pdo); TrafficRequestService::ensureSchema($pdo);
    ContentStudioService::ensureSchema($pdo);
    $pdo->exec((string)file_get_contents($root.'/modules/landing_page/database/schema.sql'));
    $pdo->exec("INSERT INTO landing_page_pages(title,slug,headline,status) VALUES ('Pagina publicada','publicada','Headline','published'),('Rascunho','rascunho','Headline','draft')");
    expect(TrafficRequestService::projectId($pdo,['id'=>1],'project-a')===1,'Identidade nao resolvida');
    rejects(fn()=>TrafficRequestService::projectId($pdo,['id'=>2],'project-a'),'Identidade adulterada aceita');
    $pdo->exec("UPDATE projects SET status='blocked' WHERE id=2");
    rejects(fn()=>TrafficRequestService::projectId($pdo,['id'=>2],'project-b'),'Projeto bloqueado autorizado');
    $pdo->exec("UPDATE projects SET status='active' WHERE id=2");
    $user=['id'=>1,'name'=>'Cliente','email'=>'cliente@example.com'];
    $post=['title'=>'Divulgacao local','business_name'=>'Loja A','destination_type'=>'external','destination_url'=>'https://example.com/oferta',
        'platform'=>'meta','objective'=>'Visitas a pagina','region'=>'Jundiai','audience'=>'Moradores da regiao','language'=>'Portugues','materials'=>'Imagens e texto da oferta',
        'starts_at'=>'2026-11-01','ends_at'=>'2026-11-07','notes'=>'Teste'];
    $data=TrafficRequestService::payload($post,$user);
    $id=TrafficRequestService::save($pdo,1,1,0,0,$data,false,false);
    $pdo->exec('ALTER TABLE content_studio_traffic_requests DROP COLUMN guidance_question');
    $pdo->exec('ALTER TABLE content_studio_traffic_requests DROP COLUMN conversation_open');
    TrafficRequestService::ensureSchema($pdo); TrafficRequestService::ensureSchema($pdo);
    expect(TrafficRequestService::find($pdo,$id,1)['title']===$post['title'],'Migracao perdeu briefing existente');
    expect((int)TrafficRequestService::find($pdo,$id,1)['conversation_open']===0,'Migracao abriu conversa antiga');
    $guidance=TrafficRequestService::payload([...$post,'platform'=>'recommend','guidance_question'=>'Qual plataforma atende melhor meu comercio?'],$user);
    $guidanceId=TrafficRequestService::save($pdo,2,1,0,0,$guidance,false,false);
    rejects(fn()=>TrafficRequestService::save($pdo,2,1,$guidanceId,1,[...$guidance,'guidance_question'=>''],true,true),'Orientacao enviada sem pergunta');
    TrafficRequestService::save($pdo,2,1,$guidanceId,1,$guidance,true,true);
    expect(str_contains(TrafficRequestService::events($pdo,$guidanceId,2)[1]['message'],$guidance['guidance_question']),'Duvida ausente no historico');
    $beforeReply=TrafficRequestService::find($pdo,$guidanceId,2);
    TrafficRequestService::review($pdo,$guidanceId,9,2,'reply','Vamos avaliar as opcoes para a sua regiao.');
    $afterReply=TrafficRequestService::find($pdo,$guidanceId,2);
    expect($afterReply['status']===$beforeReply['status'] && $afterReply['campaign_id']===null,'Resposta mudou andamento ou criou campanha');
    expect((int)$afterReply['conversation_open']===1,'Resposta do Studio nao abriu conversa');
    rejects(fn()=>TrafficRequestService::review($pdo,$guidanceId,9,3,'reviewing',''),'Analise iniciada com conversa aberta');
    rejects(fn()=>TrafficRequestService::clientReply($pdo,$guidanceId,1,1,3,'Resposta'),'Resposta cruzada aceita');
    rejects(fn()=>TrafficRequestService::clientReply($pdo,$guidanceId,2,1,3,''),'Resposta vazia do cliente aceita');
    rejects(fn()=>TrafficRequestService::review($pdo,$guidanceId,9,3,'reply',''),'Resposta vazia aceita');
    TrafficRequestService::cancel($pdo,$guidanceId,2,1,3);
    rejects(fn()=>TrafficRequestService::review($pdo,$guidanceId,9,4,'reply','Resposta'),'Resposta em pedido cancelado aceita');
    expect(count(TrafficRequestService::list($pdo,1))===1,'Rascunho ausente');
    expect(!in_array($id,array_map('intval',array_column(TrafficRequestService::list($pdo),'id')),true),'Rascunho exposto ao Studio');
    rejects(fn()=>TrafficRequestService::review($pdo,$id,9,1,'reply','Resposta'),'Resposta a rascunho privado aceita');
    rejects(fn()=>TrafficRequestService::save($pdo,1,1,$id,1,$data,true,false),'Envio sem autorizacao aceito');
    rejects(fn()=>TrafficRequestService::find($pdo,$id,2),'Consulta cruzada aceita');
    rejects(fn()=>TrafficRequestService::events($pdo,$id,2),'Historico cruzado aceito');
    rejects(fn()=>TrafficRequestService::save($pdo,2,1,$id,1,$data,false,false),'Edicao cruzada aceita');
    rejects(fn()=>TrafficRequestService::cancel($pdo,$id,2,1,1),'Cancelamento cruzado aceito');
    foreach (['javascript:alert(1)','https://','ftp://example.com','https://user:secret@example.com'] as $url) rejects(fn()=>TrafficRequestService::url($url),'URL insegura aceita');
    rejects(fn()=>TrafficRequestService::payload([...$post,'starts_at'=>'2026-02-31'],$user),'Data inexistente aceita');
    rejects(fn()=>TrafficRequestService::payload([...$post,'ends_at'=>'2026-10-01'],$user),'Periodo invertido aceito');
    rejects(fn()=>TrafficRequestService::payload([...$post,'platform'=>'outro'],$user),'Plataforma invalida aceita');
    rejects(fn()=>TrafficRequestService::payload([...$post,'destination_type'=>'whatsapp'],$user),'Destino falso do WhatsApp aceito');
    rejects(fn()=>TrafficRequestService::payload([...$post,'destination_type'=>'page'],$user,['id'=>2,'title'=>'Rascunho','status'=>'draft','public_url'=>'https://example.com']),'Pagina nao publicada aceita');
    TrafficRequestService::save($pdo,1,1,$id,1,$data,true,true);
    expect(TrafficRequestService::find($pdo,$id,1)['authorized_at']!==null,'Autorizacao nao registrada');
    rejects(fn()=>TrafficRequestService::save($pdo,1,1,$id,2,$data,false,false),'Pedido enviado editavel');
    rejects(fn()=>TrafficRequestService::review($pdo,$id,9,2,'prepared',''),'Preparacao sem analise aceita');
    rejects(fn()=>TrafficRequestService::review($pdo,$id,9,2,'changes_requested',''),'Ajustes sem motivo aceitos');
    TrafficRequestService::review($pdo,$id,9,2,'changes_requested','Detalhe o publico.');
    rejects(fn()=>TrafficRequestService::save($pdo,1,1,$id,2,$data,true,true),'Revisao antiga sobrescreveu analise');
    $post['notes']='Briefing corrigido'; $data=TrafficRequestService::payload($post,$user);
    TrafficRequestService::save($pdo,1,1,$id,3,$data,true,true);
    TrafficRequestService::review($pdo,$id,9,4,'close_conversation','');
    TrafficRequestService::review($pdo,$id,9,5,'reviewing','Analise iniciada');
    TrafficRequestService::review($pdo,$id,9,6,'prepared','Destino e briefing revisados.');
    $prepared=TrafficRequestService::find($pdo,$id,1);
    $campaign=$pdo->query('SELECT * FROM content_studio_campaigns WHERE id='.(int)$prepared['campaign_id'])->fetch(PDO::FETCH_ASSOC);
    expect($campaign['status']==='draft'&&(int)$campaign['project_id']===1&&$campaign['landing_url']===$post['destination_url'],'Campanha incorreta');
    expect($campaign['page_id']===null,'Pagina do projeto confundida com pagina do Core');
    rejects(fn()=>TrafficRequestService::review($pdo,$id,9,7,'prepared',''),'Preparacao duplicada aceita');
    expect((int)$pdo->query('SELECT COUNT(*) FROM content_studio_campaigns')->fetchColumn()===1,'Campanha duplicada');
    expect(count(TrafficRequestService::events($pdo,$id,1))===7,'Historico perdido');
    $cancelId=TrafficRequestService::save($pdo,2,1,0,0,$data,false,false);
    TrafficRequestService::cancel($pdo,$cancelId,2,1,1);
    expect(TrafficRequestService::find($pdo,$cancelId,2)['status']==='cancelled','Cancelamento falhou');

    // Exercise real route guards and CSRF using isolated credentials and test accounts.
    copyTree($root.'/modules/trafego',$fixture.'/modules/trafego');
    copyTree($root.'/app/services',$fixture.'/app/services');
    copyTree($root.'/app/database',$fixture.'/app/database');
    mkdir($fixture.'/env',0700,true); $testConfig=$config; $testConfig['db']['name']=$database; $testConfig['app_url']='https://example.com';
    file_put_contents($fixture.'/env/env.production.php','<?php return '.var_export($testConfig,true).';');
    $projectPath=$fixture.'/projects/project-a';
    copyTree($root.'/modules/trafego',$projectPath.'/modules/trafego');
    copyTree($root.'/modules/trafego/web',$projectPath.'/web');
    copyTree($root.'/modules/landing_page',$projectPath.'/modules/landing_page');
    copyTree($root.'/modules/landing_page/web',$projectPath.'/web');
    copyTree($root.'/bases/marketing/app/helpers',$projectPath.'/app/helpers');
    mkdir($projectPath.'/app/bootstrap',0700,true); mkdir($projectPath.'/app/views',0700,true);
    $dsn='mysql:host='.$db['host'].';dbname='.$database.';charset=utf8mb4';
    $connection='$pdo=new PDO('.var_export($dsn,true).','.var_export($db['user'],true).','.var_export($db['pass'],true).',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);';
    $bootstrap='<?php define("PROJECT_PATH",'.var_export($projectPath,true).');define("APP_PATH",PROJECT_PATH."/app");define("PUBLIC_PATH",PROJECT_PATH."/web");define("PROJECT_URL","/projects/project-a/web");$project=["id"=>1];$_SESSION=["project_user_id"=>$testUser,"_csrf"=>"test"];'.$connection.'require APP_PATH."/helpers/project_auth.php";require APP_PATH."/helpers/csrf.php";function flash($type,$msg){echo $msg;}function flash_show(){}function redirect($url){exit;}';
    file_put_contents($projectPath.'/app/bootstrap/project_bootstrap.php',$bootstrap);
    $css='';
    foreach (['core_base.css','admin/layout.css','admin/components.css'] as $sheet) {
        $css.=(string)file_get_contents($root.'/bases/marketing/web/assets/css/'.$sheet);
    }
    $theme=(string)file_get_contents($root.'/bases/marketing/web/assets/css/themes/dark.css');
    $layout='<?php echo "<!doctype html><html><head><meta charset=utf-8><meta name=viewport content=\"width=device-width,initial-scale=1\"><style>".'.var_export($css.$theme,true).'."body{padding:20px}.c-content{margin:0!important;max-width:1200px}</style></head><body class=c-app><main class=c-content>".$content."</main></body></html>";';
    file_put_contents($projectPath.'/app/views/layout_admin.php',$layout);
    $save=$projectPath.'/web/admin/trafego/save.php';
    $routePost=[...$post,'_csrf'=>'test','id'=>0,'revision'=>0,'action'=>'submit','authorized'=>'1','project_id'=>2];
    $count=(int)$pdo->query('SELECT COUNT(*) FROM content_studio_traffic_requests')->fetchColumn();
    expect(str_contains(route($save,[...$routePost,'_csrf'=>'invalid']),'CSRF'),'CSRF invalido aceito');
    expect(str_contains(route($save,$routePost,[],2),'Acesso negado'),'Usuario comum autorizado');
    expect(str_contains(route($save,$routePost,[],3),'Acesso negado'),'Administrador inativo autorizado');
    expect(str_contains(route($save,$routePost,[],4),'Acesso negado'),'Administrador bloqueado autorizado');
    expect((int)$pdo->query('SELECT COUNT(*) FROM content_studio_traffic_requests')->fetchColumn()===$count,'Guards nao impediram escrita');
    $routePost['destination_type']='page'; $routePost['project_page_id']=1;
    expect(str_contains(route($save,$routePost),'Solicitacao enviada'),'Envio pela rota falhou');
    $routeRow=$pdo->query('SELECT * FROM content_studio_traffic_requests ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    expect((int)$routeRow['project_id']===1&&$routeRow['contact_email']===$user['email'],'Projeto ou contato adulterado');
    expect($routeRow['destination_url']==='https://example.com/projects/project-a/web/landing_page.php?slug=publicada','URL canonica incorreta');
    expect(str_contains(route($projectPath.'/web/admin/trafego/request.php',[],['id'=>$cancelId]),'Solicitacao nao encontrada'),'Rota revelou outro projeto');
    $preview=$root.'/_deploy'; if (!is_dir($preview)) mkdir($preview,0700,true);
    file_put_contents($preview.'/traffic-form-preview.html',route($projectPath.'/web/admin/trafego/request.php'));
    file_put_contents($preview.'/traffic-index-preview.html',route($projectPath.'/web/admin/trafego/index.php'));
    file_put_contents($preview.'/traffic-detail-preview.html',route($projectPath.'/web/admin/trafego/request.php',[],['id'=>$id]));
    copyTree($root.'/web/admin/content_studio',$fixture.'/web/admin/content_studio');
    copyTree($root.'/app/actions/content_studio',$fixture.'/app/actions/content_studio');
    mkdir($fixture.'/app/bootstrap',0700,true); mkdir($fixture.'/app/helpers',0700,true); mkdir($fixture.'/app/views',0700,true);
    copy($root.'/app/helpers/auth.php',$fixture.'/app/helpers/auth.php');
    $coreBootstrap='<?php define("APP_PATH",'.var_export($fixture.'/app',true).');$_SESSION=["core_user"=>["id"=>9,"role"=>$testRole],"_csrf"=>"test"];'.$connection.'require APP_PATH."/services/TrafficRequestService.php";require APP_PATH."/services/ContentStudioService.php";require '.var_export($projectPath.'/app/helpers/csrf.php',true).';function flash($type,$msg){echo $msg;}function flash_show(){}function redirect($url){exit;}';
    file_put_contents($fixture.'/app/bootstrap/bootstrap.php',$coreBootstrap);
    file_put_contents($fixture.'/app/views/layout_admin.php',$layout);
    $review=$fixture.'/app/actions/content_studio/traffic_review.php';
    $reviewPost=['id'=>$routeRow['id'],'revision'=>1,'decision'=>'reviewing','message'=>'Analise manual','_csrf'=>'test'];
    expect(str_contains(route($review,$reviewPost,[],1,'USER'),'Acesso restrito'),'Core sem privilegios autorizado');
    expect(str_contains(route($review,[...$reviewPost,'_csrf'=>'invalid']),'CSRF'),'Core aceitou CSRF invalido');
    expect(str_contains(route($review,$reviewPost),'Solicitacao atualizada'),'Analise Core falhou');
    $replyPost=[...$reviewPost,'revision'=>2,'decision'=>'reply','message'=>'Resposta independente <script>alert(1)</script>'];
    expect(str_contains(route($review,$replyPost),'Resposta enviada'),'Rota de resposta falhou');
    $answered=TrafficRequestService::find($pdo,(int)$routeRow['id'],1);
    expect($answered['status']==='reviewing' && $answered['campaign_id']===null,'Rota alterou status ao responder');
    $clientHtml=route($projectPath.'/web/admin/trafego/request.php',[],['id'=>$routeRow['id']]);
    expect(str_contains($clientHtml,'Resposta independente &lt;script&gt;')&&!str_contains($clientHtml,'<script>alert(1)</script>'),'Resposta nao apareceu de forma segura ao cliente');
    expect(str_contains($clientHtml,'name="action" value="reply"'),'Campo de resposta do cliente ausente');
    file_put_contents($preview.'/traffic-conversation-preview.html',$clientHtml);
    $clientReply=['id'=>$routeRow['id'],'revision'=>3,'action'=>'reply','message'=>'Minha regiao e Jundiai.','_csrf'=>'test'];
    expect(str_contains(route($save,[...$clientReply,'_csrf'=>'invalid']),'CSRF'),'Resposta do cliente ignorou CSRF');
    expect(str_contains(route($save,$clientReply),'Resposta enviada'),'Rota de resposta do cliente falhou');
    expect(TrafficRequestService::find($pdo,(int)$routeRow['id'],1)['status']==='reviewing','Resposta do cliente alterou o status');
    rejects(fn()=>TrafficRequestService::review($pdo,(int)$routeRow['id'],9,3,'close_conversation',''),'Encerramento antigo ignorou nova resposta');
    rejects(fn()=>TrafficRequestService::review($pdo,(int)$routeRow['id'],9,4,'prepared',''),'Campanha preparada com conversa aberta');
    $coreHtml=route($fixture.'/web/admin/content_studio/requests.php',[],['id'=>$routeRow['id']]);
    expect(str_contains($coreHtml,'Minha regiao e Jundiai.'),'Studio nao recebeu resposta do cliente');
    expect(str_contains($coreHtml,'value="close_conversation"'),'Botao de encerrar conversa ausente');
    TrafficRequestService::review($pdo,(int)$routeRow['id'],9,4,'close_conversation','Obrigado pelo esclarecimento.');
    rejects(fn()=>TrafficRequestService::clientReply($pdo,(int)$routeRow['id'],1,1,5,'Mais uma resposta'),'Cliente respondeu conversa fechada');
    expect(!str_contains(route($projectPath.'/web/admin/trafego/request.php',[],['id'=>$routeRow['id']]),'name="action" value="reply"'),'Cliente viu resposta em conversa fechada');
    TrafficRequestService::review($pdo,(int)$routeRow['id'],9,5,'reply','Mais uma pergunta.');
    expect((int)TrafficRequestService::find($pdo,(int)$routeRow['id'],1)['conversation_open']===1,'Nova pergunta nao reabriu conversa');
    TrafficRequestService::review($pdo,(int)$routeRow['id'],9,6,'close_conversation','');
    TrafficRequestService::review($pdo,(int)$routeRow['id'],9,7,'prepared','Conversa concluida.');
    $operationPost=['id'=>$routeRow['id'],'revision'=>8,'_csrf'=>'test','decision'=>'operation_save','platform'=>'google',
        'objective'=>'Visitas ao site','region'=>'Jundiai','audience'=>'Moradores da regiao','placements'=>'Pesquisa',
        'ad_title'=>'Conheca nossa oferta','ad_text'=>'Conteudo do anuncio de teste','starts_at'=>date('Y-m-d'),'ends_at'=>date('Y-m-d',strtotime('+7 days')),
        'creative_url'=>'https://example.com/material.png','external_campaign_id'=>'campaign-test','external_campaign_url'=>'https://example.com/campaign',
        'evidence_url'=>'https://example.com/evidence'];
    expect(str_contains(route($review,$operationPost),'Solicitacao atualizada'),'Rota de planejamento falhou');
    file_put_contents($preview.'/traffic-core-operation-ready-preview.html',route($fixture.'/web/admin/content_studio/requests.php',[],['id'=>$routeRow['id']]));
    $stagePost=['id'=>$routeRow['id'],'revision'=>9,'_csrf'=>'test','decision'=>'operation_transition','operation_status'=>'platform_review','manual_confirmed'=>'1'];
    expect(str_contains(route($review,$stagePost),'Solicitacao atualizada'),'Rota de revisao da plataforma falhou');
    $stagePost['revision']=10; $stagePost['operation_status']='active';
    expect(str_contains(route($review,$stagePost),'Solicitacao atualizada'),'Rota de registro de veiculacao falhou');
    $reportPost=['id'=>$routeRow['id'],'revision'=>11,'_csrf'=>'test','decision'=>'report_save','report_date'=>date('Y-m-d'),
        'impressions'=>'1000','link_clicks'=>'45','visits'=>'','source'=>'Google / relatorio manual','report_evidence_url'=>'https://example.com/results','report_notes'=>'Resultado do dia'];
    expect(str_contains(route($review,$reportPost),'Solicitacao atualizada'),'Rota de resultados falhou');
    $operationClientHtml=route($projectPath.'/web/admin/trafego/request.php',[],['id'=>$routeRow['id']]);
    expect(str_contains($operationClientHtml,'Planejamento da divulgacao')&&str_contains($operationClientHtml,'Resultados registrados manualmente'),'Cliente nao recebeu acompanhamento');
    expect(!str_contains($operationClientHtml,'name="decision"')&&!str_contains($operationClientHtml,'Editar</a>'),'Cliente recebeu controles operacionais');
    file_put_contents($preview.'/traffic-client-operation-preview.html',$operationClientHtml);
    file_put_contents($preview.'/traffic-core-operation-active-preview.html',route($fixture.'/web/admin/content_studio/requests.php',[],['id'=>$routeRow['id']]));
    file_put_contents($preview.'/traffic-core-preview.html',route($fixture.'/web/admin/content_studio/requests.php'));
    file_put_contents($preview.'/traffic-core-detail-preview.html',$coreHtml);
    expect((int)$pdo->query('SELECT COUNT(*) FROM landing_page_leads')->fetchColumn()===0,'Leads alterados');
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/modules/trafego',FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative=substr($file->getPathname(),strlen($root.'/modules/trafego/'));
        expect(hash_file('sha256',$file->getPathname())===hash_file('sha256',$root.'/bases/marketing/modules/trafego/'.$relative),'Copia da base divergente');
    }
    echo "PASS: schema, identidade, isolamento, briefing, revisoes, estados, campanha unica, rotas, auth, CSRF e leads preservados.\n";
} finally {
    $pdo->exec('DROP DATABASE IF EXISTS `'.$database.'`');
    if (is_dir($fixture) && str_starts_with($fixture,sys_get_temp_dir().'/test_traffic_')) {
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());
        rmdir($fixture);
    }
}
