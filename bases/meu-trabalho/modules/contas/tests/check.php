<?php
declare(strict_types=1);
require dirname(__DIR__).'/web/admin/contas/helpers.php';
function accountCheck(bool $condition,string $message): void
{
    if(!$condition) throw new RuntimeException($message);
}
function accountReject(callable $call): void
{
    try { $call(); } catch(InvalidArgumentException $e) { return; }
    throw new RuntimeException('Entrada invalida aceita.');
}
$today='2026-10-09';
$input=['kind'=>'payable','title'=>'Aluguel','counterparty'=>'Empresa teste','category'=>'Moradia','amount'=>'1.000,00','due_date'=>'2026-10-15'];
$record=contasNormalize($input);
accountCheck($record['amount']==='1000.00','Centavos.');
foreach([['kind'=>'invalid'],['amount'=>'0'],['amount'=>'-1'],['amount'=>'1e3'],['amount'=>'10000000000'],['due_date'=>'2026-02-30'],['title'=>[]],['due_date'=>'']] as $invalid) {
    accountReject(static fn()=>contasNormalize(array_replace($input,$invalid)));
}
$base=$record+['id'=>1,'paid_amount'=>'300.00','canceled'=>0];
accountCheck(contasRemaining($base)===70000 && contasStatus($base,$today)==='partial','Restante parcial.');
accountCheck(contasStatus(array_replace($base,['due_date'=>'2026-10-08']),$today)==='overdue','Parcial vencida.');
accountCheck(contasStatus(array_replace($base,['due_date'=>$today,'paid_amount'=>'0']),$today)==='open','Vencimento hoje nao atrasado.');
accountCheck(contasStatus(array_replace($base,['paid_amount'=>'1000']),$today)==='settled','Quitada.');
accountCheck(contasStatus(array_replace($base,['canceled'=>1]),$today)==='canceled','Cancelada.');
$movements=[['kind'=>'payable','category'=>'Moradia','amount'=>'300','paid_date'=>$today],['kind'=>'receivable','category'=>'Trabalho','amount'=>'500','paid_date'=>$today],['kind'=>'receivable','category'=>'Trabalho','amount'=>'999','paid_date'=>'2026-10-10']];
$stats=contasAnalytics([$base,array_replace($base,['id'=>2,'due_date'=>'2026-10-08'])],$movements,$today);
accountCheck($stats['summary']['pending_payable']===140000 && $stats['summary']['overdue_payable']===70000 && $stats['summary']['next7_payable']===70000,'Pendencias, vencidas e proximas.');
accountCheck($stats['summary']['paid_month']===30000 && $stats['summary']['received_month']===50000,'Realizado sem data futura.');
accountCheck(count($stats['actual'])===12 && count($stats['forecast'])===6 && $stats['actual']['2026-10']['payable']===30000,'Periodos e movimentos reais.');
accountCheck(array_keys(contasAnalytics([],[],'2026-03-31')['actual'])[10]==='2026-02','Meses sem saltos no dia 31.');
$pdf=contasPdfValidate('documento.pdf',"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
accountReject(static fn()=>contasPdfValidate('fake.pdf','<html>invalido</html>'));
accountReject(static fn()=>contasPdfValidate('fake.txt',$pdf['pdf']));
echo "Contas: valores, datas, situacoes, PDF e indicadores OK.\n";
if(getenv('ACCOUNTS_MYSQL_TEST')==='1') {
    $db='accounts_verify_'.bin2hex(random_bytes(5));
    $server=new PDO('mysql:host=app_db;charset=utf8mb4','root','root',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $server->exec('CREATE DATABASE `'.$db.'` CHARACTER SET utf8mb4');
    try {
        $pdo=new PDO('mysql:host=app_db;dbname='.$db.';charset=utf8mb4','root','root',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        contasEnsureSchema($pdo); contasEnsureSchema($pdo);
        $id=contasSave($pdo,10,0,$record,$pdf);
        accountCheck(contasAttachment($pdo,10,$id)['pdf']===$pdf['pdf'],'PDF salvo.');
        accountCheck(contasFind($pdo,11,$id)===null && contasAttachment($pdo,11,$id)===null && contasRecords($pdo,11)===[],'Leitura privada.');
        foreach(['save','pay','cancel','delete','remove_payment'] as $operation) {
            try {
                if($operation==='save') contasSave($pdo,11,$id,$record);
                elseif($operation==='pay') contasPay($pdo,11,$id,['payment_amount'=>'1','paid_date'=>$today,'method'=>'pix'],$today);
                else contasAction($pdo,11,$id,$operation,1);
                throw new LogicException('Escrita indevida aceita.');
            } catch(RuntimeException $e) { accountCheck($e->getMessage()==='Conta nao encontrada.','Escrita privada.'); }
        }
        $payment=['payment_amount'=>'300','paid_date'=>$today,'method'=>'pix','payment_token'=>str_repeat('a',32)];
        contasPay($pdo,10,$id,$payment,$today);
        contasPay($pdo,10,$id,$payment,$today);
        accountCheck(count(contasPayments($pdo,10))===1,'Reenvio idempotente.');
        accountReject(static fn()=>contasPay($pdo,10,$id,array_replace($payment,['payment_amount'=>'301']),$today));
        accountCheck(contasRemaining(contasFind($pdo,10,$id))===70000,'Parcial persistido.');
        accountReject(static fn()=>contasPay($pdo,10,$id,['payment_amount'=>'701','paid_date'=>$today,'method'=>'pix'],$today));
        accountReject(static fn()=>contasPay($pdo,10,$id,['payment_amount'=>'1','paid_date'=>'2026-10-10','method'=>'pix'],$today));
        accountReject(static fn()=>contasSave($pdo,10,$id,array_replace($record,['amount'=>'200.00'])));
        accountReject(static fn()=>contasSave($pdo,10,$id,array_replace($record,['kind'=>'receivable'])));
        accountReject(static fn()=>contasAction($pdo,10,$id,'delete'));
        accountReject(static fn()=>contasAction($pdo,10,$id,'cancel'));
        contasPay($pdo,10,$id,['payment_amount'=>'700','paid_date'=>$today,'method'=>'transfer'],$today);
        accountCheck(contasStatus(contasFind($pdo,10,$id),$today)==='settled','Quitacao total.');
        $payments=contasPayments($pdo,10);
        contasAction($pdo,10,$id,'remove_payment',(int)$payments[0]['id']);
        accountCheck(contasRemaining(contasFind($pdo,10,$id))===70000,'Correcao de movimento.');
        contasSave($pdo,10,$id,$record);
        accountCheck(contasAttachment($pdo,10,$id)!==null,'Documento preservado.');
        contasSave($pdo,10,$id,$record,null,true);
        accountCheck(contasAttachment($pdo,10,$id)===null,'Documento removido.');
        $cancel=contasSave($pdo,10,0,$record);
        contasAction($pdo,10,$cancel,'cancel');
        accountReject(static fn()=>contasPay($pdo,10,$cancel,['payment_amount'=>'1','paid_date'=>$today,'method'=>'pix'],$today));
        contasAction($pdo,10,$cancel,'cancel');
        contasAction($pdo,10,$cancel,'delete');
        accountCheck(contasFind($pdo,10,$cancel)===null,'Reativacao e exclusao.');
        $other=contasSave($pdo,11,0,$record);
        accountCheck(count(contasRecords($pdo,11))===1 && contasPayments($pdo,11)===[],'Outro dono independente.');
        $concurrent=contasSave($pdo,10,0,$record);
        $worker='require '.var_export(dirname(__DIR__).'/web/admin/contas/helpers.php',true).'; $pdo=new PDO("mysql:host=app_db;dbname='.$db.';charset=utf8mb4","root","root",[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); try { contasPay($pdo,10,'.$concurrent.',["payment_amount"=>"600","paid_date"=>"'.$today.'","method"=>"pix"],"'.$today.'"); echo "ok"; } catch(InvalidArgumentException $e) { echo "blocked"; }';
        $processes=[];
        for($i=0;$i<2;$i++) { $pipes=[]; $process=proc_open([PHP_BINARY,'-r',$worker],[1=>['pipe','w'],2=>['pipe','w']],$pipes); if(!is_resource($process)) throw new RuntimeException('Worker indisponivel.'); $processes[]=[$process,$pipes]; }
        $results=[];
        foreach($processes as [$process,$pipes]) { $results[]=stream_get_contents($pipes[1]); $errors=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); accountCheck(proc_close($process)===0 && $errors==='','Worker concorrente.'); }
        sort($results);
        accountCheck($results===['blocked','ok'] && contasCents(contasFind($pdo,10,$concurrent)['paid_amount'])===60000,'Concorrencia sem ultrapassar valor.');
        echo "Contas: MySQL, isolamento, parciais, idempotencia, concorrencia e documentos OK.\n";
    } finally { $server->exec('DROP DATABASE `'.$db.'`'); }
}
