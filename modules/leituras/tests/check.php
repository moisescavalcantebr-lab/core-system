<?php
declare(strict_types=1);
require dirname(__DIR__).'/web/admin/leituras/helpers.php';
require dirname(__DIR__).'/web/admin/leituras/report.php';
function eqCheck(bool $condition, string $message): void
{
    if (!$condition) throw new LogicException($message);
}
function eqReject(callable $call): void
{
    try { $call(); } catch (InvalidArgumentException $e) { return; }
    throw new LogicException('Entrada invalida aceita.');
}
$input=['reason_id'=>'1','work_date'=>'2026-10-09','total'=>'500','lis'=>'400','leit'=>'100'];
$record=eqNormalizeReading($input);
eqCheck($record['executed']===null && $record['sent']===null,'Resultados ausentes nao sao zero.');
eqCheck(eqNormalizeReading($input+['executed'=>'0','sent'=>'0'])['executed']===0,'Zero explicito.');
eqCheck(eqNormalizeReading(array_replace($input,['lis'=>'900','leit'=>'800','executed'=>'100','sent'=>'70']))['total']===500,'LIS/LEIT independentes.');
foreach([['total'=>'-1'],['total'=>'1.2'],['total'=>'1e3'],['total'=>'1000001'],['total'=>[]],['executed'=>'501'],['sent'=>'1'],['executed'=>'5','sent'=>'6'],['work_date'=>'2026-02-30'],['reason_id'=>'0'],['lis'=>'-1'],['leit'=>[]]] as $invalid) eqReject(static fn()=>eqNormalizeReading(array_replace($input,$invalid)));
eqReject(static fn()=>eqMonth('2026-13'));
eqReject(static fn()=>eqNormalizeReason(['number'=>'abc','location'=>'Jaiara']));
eqReject(static fn()=>eqNormalizeReason(['number'=>'22','location'=>'']));
eqCheck(eqNormalizeReason(['number'=>'0022','location'=>'Jaiara'])['number']==='22','Numero canonico.');
eqCheck(eqStatus($record)==='A executar','Estado inicial.');
eqCheck(eqStatus(array_replace($record,['executed'=>400,'sent'=>400]))==='Em execucao','Execucao parcial.');
eqCheck(eqStatus(array_replace($record,['executed'=>500,'sent'=>400]))==='A enviar','Envio parcial.');
eqCheck(eqStatus(array_replace($record,['executed'=>500,'sent'=>500]))==='Concluida','Conclusao.');
$previous=array_replace($record,['work_date'=>'2026-09-09','lis'=>380,'leit'=>120,'executed'=>500,'sent'=>500]);
$stats=eqAnalytics([$record,$previous],'2026-10');
eqCheck($stats['current']['pending']===0 && $stats['current']['executed_days']===0,'Pendencia desconhecida com cobertura zero.');
eqCheck($stats['deltas']['lis']['amount']===20 && $stats['deltas']['leit']['amount']===-20,'Variacao independente.');
eqCheck($stats['previous_totals'][1]===500,'Total anterior da mesma razao.');
$otherReason=array_replace($previous,['reason_id'=>2,'total'=>900]);
eqCheck(eqAnalytics([$record,$previous,$otherReason],'2026-10')['previous_totals'][1]===500,'Nao misturar razoes.');
eqCheck(eqAnalytics([$record,array_replace($previous,['work_date'=>'2026-08-09'])],'2026-10')['previous_totals']===[],'Nao usar um mes mais antigo como anterior.');
eqCheck(eqAnalytics([$record,array_replace($previous,['total'=>0])],'2026-10')['previous_totals'][1]===0,'Zero anterior distinto de ausencia.');
eqCheck(eqAnalytics([$record],'2026-10')['deltas']['lis']['amount']===null,'Mes anterior ausente.');
eqCheck(eqAnalytics([$record,array_replace($previous,['lis'=>null])],'2026-10')['deltas']['lis']['amount']===null,'Referencias incompletas.');
eqCheck(eqAnalytics([$record,array_replace($previous,['lis'=>0])],'2026-10')['deltas']['lis']['percent']===null,'Nao dividir por zero.');
eqCheck(array_keys(eqAnalytics([],'2026-03')['months'])[10]==='2026-02','Calendario sem salto.');
ob_start(); eqReport($stats); $html=ob_get_clean();
eqCheck(str_contains($html,'0/1 dias informados') && str_contains($html,'Producao mensal'),'Renderizacao e cobertura.');
echo "Leituras: validacao, estados, referencias independentes, cobertura e comparacao OK.\n";
if (getenv('LEITURAS_MYSQL_TEST')==='1') {
    $db='leituras_verify_'.bin2hex(random_bytes(5));
    $server=new PDO('mysql:host=app_db;charset=utf8mb4','root','root',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $server->exec('CREATE DATABASE `'.$db.'` CHARACTER SET utf8mb4');
    try {
        $pdo=new PDO('mysql:host=app_db;dbname='.$db.';charset=utf8mb4','root','root',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        eqEnsureSchema($pdo); eqEnsureSchema($pdo);
        $reason=eqSaveReason($pdo,10,0,['number'=>'22','location'=>'Jaiara']);
        eqReject(static fn()=>eqSaveReason($pdo,10,0,['number'=>'022','location'=>'Outro']));
        $other=eqSaveReason($pdo,11,0,['number'=>'22','location'=>'Privado']);
        eqCheck(eqFind($pdo,11,$reason,true)===null && count(eqReasons($pdo,11))===1,'Cadastro privado.');
        $record['reason_id']=$reason;
        $id=eqSaveReading($pdo,10,0,$record);
        eqCheck(eqFind($pdo,10,$id)['executed']===null,'Nulo persistido.');
        eqCheck(eqFind($pdo,11,$id)===null && eqReadings($pdo,11,'2026-10-01','2026-10-31')===[],'Leituras privadas.');
        eqReject(static fn()=>eqSaveReading($pdo,11,0,$record));
        eqReject(static fn()=>eqSaveReading($pdo,10,0,array_replace($record,['reason_id'=>$other])));
        eqReject(static fn()=>eqSaveReading($pdo,10,0,$record));
        foreach(['reason','reading','action'] as $operation) {
            try {
                if($operation==='reason') eqSaveReason($pdo,11,$reason,['number'=>'23','location'=>'Outro'],1);
                elseif($operation==='reading') eqSaveReading($pdo,11,$id,$record,1);
                else eqAction($pdo,11,$id,'delete_reading',1);
                throw new LogicException('Escrita privada falhou.');
            } catch(RuntimeException $e) { eqCheck(str_contains($e->getMessage(),'nao encontrad'),'Mensagem de isolamento.'); }
        }
        eqSaveReason($pdo,10,$reason,['number'=>'23','location'=>'Jaiara nova'],1);
        eqReject(static fn()=>eqSaveReason($pdo,10,$reason,['number'=>'24','location'=>'Velho'],1));
        $result=array_replace($record,['executed'=>450,'sent'=>400]);
        eqSaveReading($pdo,10,$id,$result,1);
        $saved=eqFind($pdo,10,$id);
        eqCheck($saved['reason_number']==='22' && $saved['location']==='Jaiara','Historico preservado.');
        eqReject(static fn()=>eqSaveReading($pdo,10,$id,$record,1));
        eqReject(static fn()=>eqAction($pdo,10,$reason,'delete_reason',2));
        eqAction($pdo,10,$reason,'toggle_reason',2);
        eqReject(static fn()=>eqSaveReading($pdo,10,0,array_replace($record,['work_date'=>'2026-11-09'])));
        eqSaveReading($pdo,10,$id,array_replace($result,['sent'=>450]),2);
        eqAction($pdo,10,$reason,'toggle_reason',3);
        $next=eqSaveReading($pdo,10,0,array_replace($record,['work_date'=>'2026-11-09']));
        eqCheck(eqFind($pdo,10,$next)['location']==='Jaiara nova','Reutilizacao no mes seguinte.');
        eqCheck((int)eqReasons($pdo,10)[0]['used_count']===2,'Vinculos registrados.');
        $unused=eqSaveReason($pdo,10,0,['number'=>'99','location'=>'Excluir']);
        eqAction($pdo,10,$unused,'delete_reason',1);
        eqCheck(eqFind($pdo,10,$unused,true)===null,'Exclusao sem historico.');
        $helper=var_export(dirname(__DIR__).'/web/admin/leituras/helpers.php',true);
        $dsn=var_export('mysql:host=app_db;dbname='.$db.';charset=utf8mb4',true);
        $race=var_export(array_replace($record,['work_date'=>'2026-12-09']),true);
        $code='require '.$helper.'; $pdo=new PDO('.$dsn.',"root","root",[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); try {eqSaveReading($pdo,10,0,'.$race.');echo "saved";}catch(InvalidArgumentException $e){echo "duplicate";}';
        $processes=[];
        for($i=0;$i<2;$i++) {
            $pipes=[];
            $process=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
            eqCheck(is_resource($process),'Processo de concorrencia.');
            $processes[]=[$process,$pipes];
        }
        $outputs=[];
        foreach($processes as [$process,$pipes]) {
            $outputs[]=stream_get_contents($pipes[1]); $errors=stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); eqCheck(proc_close($process)===0 && $errors==='','Concorrencia sem erro inesperado.');
        }
        sort($outputs); eqCheck($outputs===['duplicate','saved'],'Apenas um lancamento por dia sob concorrencia.');
        eqAction($pdo,10,$id,'delete_reading',3);
        eqCheck(eqFind($pdo,10,$id)===null,'Exclusao diaria.');
        // Existing installations gain month controls without rewriting their readings.
        $legacyReason=eqSaveReason($pdo,30,0,['number'=>'77','location'=>'Legado']);
        $stmt=$pdo->prepare('INSERT INTO leituras_readings (owner_user_id,reason_id,reason_number,location,work_date,total,executed,sent,lis,leit) VALUES (30,?,77,?,"2025-12-01",600,600,600,500,100)');
        $stmt->execute([$legacyReason,'Legado']);
        $legacyId=(int)$pdo->lastInsertId();
        eqEnsureSchema($pdo);
        eqCheck(eqWorkspace($pdo,30)==='2025-12','Inicializar pelo ultimo mes existente, nao pelo calendario.');
        $january=['reason_id'=>$legacyReason,'work_date'=>'2026-01-01','total'=>660,'executed'=>660,'sent'=>660,'lis'=>550,'leit'=>110];
        $januaryId=eqSaveReading($pdo,30,0,$january);
        eqCheck(eqWorkspace($pdo,30)==='2025-12','Lancamento em outro mes nao muda o mes de trabalho.');
        eqCheck(eqSetPeriod($pdo,30,'2025-12','archive_month',0)==='2026-01','Avanco manual na virada do ano.');
        eqCheck(eqFind($pdo,30,$legacyId)['total']===600 && count(eqArchives($pdo,30))===1,'Arquivo conserva leituras.');
        eqCheck(eqArchives($pdo,31)===[],'Arquivos privados.');
        eqReject(static fn()=>eqSetPeriod($pdo,31,'2025-12','archive_month',0));
        $legacy=array_replace($january,['work_date'=>'2025-12-01','total'=>600,'executed'=>600,'sent'=>600]);
        eqReject(static fn()=>eqSaveReading($pdo,30,$legacyId,$legacy,1));
        eqReject(static fn()=>eqSaveReading($pdo,30,$legacyId,array_replace($legacy,['work_date'=>'2026-02-01']),1));
        eqReject(static fn()=>eqSaveReading($pdo,30,0,array_replace($legacy,['work_date'=>'2025-12-02'])));
        eqReject(static fn()=>eqAction($pdo,30,$legacyId,'delete_reading',1));
        $archivedStats=eqAnalytics(eqReadings($pdo,30,'2025-12-01','2026-01-31'),'2026-01');
        eqCheck($archivedStats['previous_totals'][$legacyReason]===600 && $archivedStats['months']['2025-12']['total']===600,'Arquivos continuam em comparacoes e graficos.');
        $oldPeriod=eqPeriod($pdo,30,'2026-01');
        eqSaveReading($pdo,30,$januaryId,array_replace($january,['executed'=>650,'sent'=>640]),1);
        eqReject(static fn()=>eqSetPeriod($pdo,30,'2026-01','archive_month',(int)$oldPeriod['revision']));
        eqCheck(eqPeriod($pdo,30,'2026-01')['archived_at']===null,'Conferencia desatualizada nao arquiva.');
        eqCheck(eqSetPeriod($pdo,30,'2025-12','reopen_month',1)==='2025-12','Reabertura manual.');
        eqSaveReading($pdo,30,$legacyId,array_replace($legacy,['executed'=>599,'sent'=>599]),1);
        eqCheck(eqSetPeriod($pdo,30,'2025-12','archive_month',3)==='2026-01','Rearquivar sem apagar proximo mes.');
        eqCheck((int)eqFind($pdo,30,$januaryId)['executed']===650,'Dados do proximo mes preservados.');
        $archiveCode='require '.$helper.'; $pdo=new PDO('.$dsn.',"root","root",[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);try{eqSetPeriod($pdo,30,"2026-01","archive_month",2);echo "archive";}catch(InvalidArgumentException $e){echo "blocked_archive";}';
        $editData=var_export(array_replace($january,['executed'=>600,'sent'=>600]),true);
        $editCode='require '.$helper.'; $pdo=new PDO('.$dsn.',"root","root",[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);try{eqSaveReading($pdo,30,'.$januaryId.','.$editData.',2);echo "edit";}catch(InvalidArgumentException $e){echo "blocked_edit";}';
        $processes=[];
        foreach([$archiveCode,$editCode] as $code) {
            $pipes=[]; $process=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
            eqCheck(is_resource($process),'Processo de fechamento.'); $processes[]=[$process,$pipes];
        }
        $outputs=[];
        foreach($processes as [$process,$pipes]) {
            $outputs[]=stream_get_contents($pipes[1]); $errors=stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); eqCheck(proc_close($process)===0 && $errors==='','Fechamento concorrente sem erro inesperado.');
        }
        sort($outputs);
        eqCheck($outputs===['archive','blocked_edit'] || $outputs===['blocked_archive','edit'],'Edicao e fechamento nao passam com a mesma conferencia antiga.');
        $before=[];
        foreach(['reasons','readings','workspaces','periods'] as $suffix) $before[$suffix]=$pdo->query('SELECT * FROM leituras_'.$suffix.' ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC);
        $names=[];
        foreach(array_keys($before) as $suffix) $names[]='`leituras_'.$suffix.'` TO `equatorial_'.$suffix.'`';
        $pdo->exec('RENAME TABLE '.implode(', ',$names));
        eqEnsureSchema($pdo); eqEnsureSchema($pdo);
        foreach($before as $suffix=>$rows) eqCheck($rows===$pdo->query('SELECT * FROM leituras_'.$suffix.' ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC),'Renomeacao preserva dados e IDs: '.$suffix);
        $pdo->exec('CREATE TABLE equatorial_reasons (id INT PRIMARY KEY)');
        try {
            eqEnsureSchema($pdo);
            throw new LogicException('Conflito de tabelas aceito.');
        } catch(RuntimeException $e) { eqCheck(str_contains($e->getMessage(),'coexistem'),'Conflito deve interromper a migracao.'); }
        $pdo->exec('DROP TABLE equatorial_reasons');
        echo "Leituras: renomeacao preserva IDs, leituras, arquivos e rejeita conflitos OK.\n";
        echo "Leituras: migracao, total anterior, ciclos manuais, arquivos, reabertura e fechamento concorrente OK.\n";
        echo "Leituras: MySQL, isolamento, historico, reutilizacao, revisoes e concorrencia OK.\n";
    } finally { $server->exec('DROP DATABASE `'.$db.'`'); }
}
