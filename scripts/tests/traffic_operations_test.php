<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);
$root=dirname(__DIR__,2);
require $root.'/app/services/TrafficRequestService.php';
require $root.'/app/services/ContentStudioService.php';
function check(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
function reject(callable $action,string $message): void { try { $action(); } catch (RuntimeException|InvalidArgumentException $e) { return; } throw new RuntimeException($message); }
$config=require $root.'/env/env.production.php'; $db=$config['db'];
$pdo=new PDO('mysql:host='.$db['host'].';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$database='test_traffic_ops_'.bin2hex(random_bytes(6));
$pdo->exec('CREATE DATABASE `'.$database.'`');
try {
    $pdo->exec('USE `'.$database.'`');
    ContentStudioService::ensureSchema($pdo); TrafficRequestService::ensureSchema($pdo); TrafficRequestService::ensureSchema($pdo);
    $user=['name'=>'Cliente','email'=>'cliente@example.com'];
    $brief=['title'=>'Teste operacional','business_name'=>'Loja','destination_type'=>'external','destination_url'=>'https://example.com/oferta',
        'platform'=>'meta','objective'=>'Visitas','region'=>'Jundiai','language'=>'Portugues'];
    $id=TrafficRequestService::save($pdo,1,1,0,0,TrafficRequestService::payload($brief,$user),true,true);
    $revision=fn()=>(int)TrafficRequestService::find($pdo,$id)['revision'];
    TrafficRequestService::review($pdo,$id,9,$revision(),'reviewing','');
    TrafficRequestService::review($pdo,$id,9,$revision(),'prepared','');
    $campaign=(int)TrafficRequestService::find($pdo,$id)['campaign_id'];
    $plan=['platform'=>'google','objective'=>'Visitas ao site','region'=>'Jundiai e regiao','audience'=>'Moradores','placements'=>'Pesquisa',
        'ad_title'=>'Conheca a loja','ad_text'=>'Oferta de teste','creative_url'=>'https://example.com/material.png',
        'starts_at'=>date('Y-m-d'),'ends_at'=>date('Y-m-d',strtotime('+7 days')),'external_campaign_id'=>'campaign-123',
        'external_campaign_url'=>'https://example.com/campaign/123','evidence_url'=>'https://example.com/evidence/123'];
    $next=fn($state,$extra=[])=>TrafficOperationsService::transition($pdo,$id,9,$revision(),['operation_status'=>$state,'manual_confirmed'=>'1','operation_notes'=>'Teste manual',...$extra]);
    reject(fn()=>$next('platform_review'),'Etapa sem planejamento aceita');
    reject(fn()=>TrafficOperationsService::savePlan($pdo,$id,9,$revision(),[...$plan,'platform'=>'recommend']),'Plataforma nao definida aceita');
    reject(fn()=>TrafficOperationsService::savePlan($pdo,$id,9,$revision(),[...$plan,'creative_url'=>'javascript:alert(1)']),'Material inseguro aceito');
    reject(fn()=>TrafficOperationsService::savePlan($pdo,$id,9,$revision(),[...$plan,'ends_at'=>'2020-01-01']),'Periodo invalido aceito');
    TrafficOperationsService::savePlan($pdo,$id,9,$revision(),$plan);
    check(TrafficRequestService::find($pdo,$id)['platform']==='meta','Planejamento sobrescreveu preferencia do cliente');
    reject(fn()=>TrafficOperationsService::plan($pdo,$id,2),'Planejamento cruzado exposto');
    reject(fn()=>TrafficOperationsService::reports($pdo,$id,2),'Resultados cruzados expostos');
    reject(fn()=>$next('active'),'Veiculacao pulou revisao da plataforma');
    reject(fn()=>$next('platform_review',['manual_confirmed'=>'']),'Registro sem confirmacao aceito');
    TrafficRequestService::review($pdo,$id,9,$revision(),'reply','Duvida operacional');
    reject(fn()=>$next('platform_review'),'Etapa com conversa aberta aceita');
    TrafficRequestService::review($pdo,$id,9,$revision(),'close_conversation','');
    $next('platform_review'); $next('platform_rejected');
    TrafficOperationsService::savePlan($pdo,$id,9,$revision(),[...$plan,'ad_text'=>'Texto corrigido']);
    $next('platform_review'); $next('active');
    check($pdo->query('SELECT status FROM content_studio_campaigns WHERE id='.$campaign)->fetchColumn()==='active','Campanha e pedido divergentes');
    $started=TrafficOperationsService::plan($pdo,$id)['actual_started_at'];
    reject(fn()=>TrafficOperationsService::savePlan($pdo,$id,9,$revision(),$plan),'Planejamento em veiculacao editavel');
    $report=['report_date'=>date('Y-m-d'),'impressions'=>'1000','link_clicks'=>'50','visits'=>'','source'=>'Relatorio de teste Google',
        'report_evidence_url'=>'https://example.com/report','report_notes'=>'Dados do dia'];
    foreach (['-1','1.5','2147483648'] as $invalid) reject(fn()=>TrafficOperationsService::saveReport($pdo,$id,9,$revision(),[...$report,'link_clicks'=>$invalid]),'Metrica invalida aceita');
    reject(fn()=>TrafficOperationsService::saveReport($pdo,$id,9,$revision(),[...$report,'report_date'=>date('Y-m-d',strtotime('+1 day'))]),'Resultado futuro aceito');
    reject(fn()=>TrafficOperationsService::saveReport($pdo,$id,9,$revision(),[...$report,'report_date'=>'2020-01-01']),'Resultado fora da veiculacao aceito');
    reject(fn()=>TrafficOperationsService::saveReport($pdo,$id,9,$revision(),[...$report,'source'=>'']),'Fonte ausente aceita');
    TrafficOperationsService::saveReport($pdo,$id,9,$revision(),$report);
    $oldRevision=$revision();
    TrafficOperationsService::saveReport($pdo,$id,9,$revision(),[...$report,'link_clicks'=>'60']);
    reject(fn()=>TrafficOperationsService::saveReport($pdo,$id,9,$oldRevision,$report),'Revisao antiga sobrescreveu relatorio');
    $reports=TrafficOperationsService::reports($pdo,$id,1);
    check(count($reports)===1 && (int)$reports[0]['link_clicks']===60,'Atualizacao duplicou dia ou nao corrigiu metrica');
    check(TrafficOperationsService::totals($reports)===['impressions'=>1000,'link_clicks'=>60,'visits'=>null],'Visitas nao medidas viraram zero');
    $next('paused'); $next('active');
    check(TrafficOperationsService::plan($pdo,$id)['actual_started_at']===$started,'Retomada apagou inicio original');
    $next('completed');
    check($pdo->query('SELECT status FROM content_studio_campaigns WHERE id='.$campaign)->fetchColumn()==='finished','Conclusao nao sincronizou campanha');
    reject(fn()=>$next('active'),'Campanha concluida reativada');
    check($pdo->query("SHOW TABLES LIKE 'project_wallet_movements'")->fetchColumn()===false,'Operacao criou estruturas financeiras');
    echo "PASS: planejamento, isolamento, estados manuais, conversa, rejeicao, pausa, conclusao e resultados diarios sem financeiro.\n";
} finally { $pdo->exec('DROP DATABASE IF EXISTS `'.$database.'`'); }
