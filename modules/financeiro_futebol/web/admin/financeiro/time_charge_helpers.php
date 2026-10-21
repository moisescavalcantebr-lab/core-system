<?php
declare(strict_types=1);

function financeTeamCents(string $value): int
{
    if(!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',$value))throw new DomainException('Informe um valor com ate duas casas decimais.');
    [$whole,$fraction]=array_pad(explode('.',$value,2),2,'');
    return ((int)$whole)*100+(int)str_pad($fraction,2,'0');
}

function financeTeamDecimal(int $cents): string
{
    if($cents<0)throw new LogicException('Valor negativo.');
    return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
}

function financeTeamChargeSnapshot(PDO $pdo,int $id): array
{
    $stmt=$pdo->prepare("SELECT c.*,COALESCE((SELECT SUM(e.amount) FROM finance_team_charge_payments p JOIN finance_entries e ON e.id=p.entry_id WHERE p.charge_id=c.id AND e.status='paid'),0) AS received_amount FROM finance_team_charges c WHERE c.id=?");
    $stmt->execute([$id]);$charge=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$charge)throw new DomainException('Cobranca nao encontrada neste projeto.');
    $charge['remaining_amount']=financeTeamDecimal(max(0,financeTeamCents($charge['total_amount'])-financeTeamCents($charge['received_amount'])));
    return $charge;
}

function financeTeamChargeAudit(PDO $pdo,int $id,string $action,int $actor,?array $before=null,?int $paymentId=null,?string $reason=null): void
{
    if(!$pdo->inTransaction()||$actor<=0)throw new LogicException('Historico exige transacao e responsavel.');
    $after=financeTeamChargeSnapshot($pdo,$id);
    $pdo->prepare('INSERT INTO finance_team_charge_history(charge_id,action,actor_user_id,payment_entry_id,reason,before_json,after_json) VALUES(?,?,?,?,?,?,?)')->execute([$id,$action,$actor,$paymentId,$reason,$before?json_encode($before,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE):null,json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
}

function financeTeamOutstanding(PDO $pdo,?int $playerId=null): string
{
    $sql="SELECT COALESCE(SUM(GREATEST(c.total_amount-COALESCE((SELECT SUM(e.amount) FROM finance_team_charge_payments p JOIN finance_entries e ON e.id=p.entry_id WHERE p.charge_id=c.id AND e.status='paid'),0),0)),0) FROM finance_team_charges c WHERE c.status='open'";
    $params=[];if($playerId){$sql.=' AND c.beneficiary_player_id=?';$params[]=$playerId;}
    $stmt=$pdo->prepare($sql);$stmt->execute($params);return (string)$stmt->fetchColumn();
}

function financeTeamChargeCreate(PDO $pdo,array $input,int $actor): int
{
    if($actor<=0)throw new DomainException('Responsavel invalido.');
    $title=trim((string)($input['title']??''));
    if($title===''||mb_strlen($title)>150)throw new DomainException('Informe a finalidade, com ate 150 caracteres.');
    $cents=financeTeamCents((string)($input['amount']??''));if($cents<=0)throw new DomainException('O valor precisa ser positivo.');
    if(!financeTeamDate((string)($input['due_date']??'')))throw new DomainException('Informe uma data prevista valida.');
    $party=financeTeamParty($pdo,['team_party_kind'=>'player','team_player_id'=>$input['beneficiary_player_id']??null]);
    $key=(string)($input['request_key']??'');if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('Reabra o formulario para criar a cobranca.');
    $categoryId=null;
    if(!empty($input['category_id'])){
        $stmt=$pdo->prepare("SELECT id FROM finance_categories WHERE id=? AND status='active' AND type IN ('income','both')");$stmt->execute([(int)$input['category_id']]);$categoryId=$stmt->fetchColumn();
        if(!$categoryId)throw new DomainException('Selecione uma categoria de entrada valida.');
    }
    $pdo->beginTransaction();
    try{
        $id=financeTeamChargeWrite($pdo,$party,$title,$cents,$categoryId?(int)$categoryId:null,$input['due_date'],$actor,$key);
        $pdo->commit();return $id;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

/** Internal writer shared by individual and atomic group creation. */
function financeTeamChargeWrite(PDO $pdo,array $party,string $title,int $cents,?int $categoryId,string $dueDate,int $actor,string $key): int
{
    if(!$pdo->inTransaction()||$actor<=0||$cents<=0)throw new LogicException('Cobranca exige transacao, valor e responsavel.');
    $fingerprint=hash('sha256',json_encode([$actor,$party,$title,$cents,$categoryId,$dueDate],JSON_THROW_ON_ERROR));
    $pdo->prepare('INSERT INTO finance_team_charges(beneficiary_player_id,beneficiary_name,title,total_amount,category_id,due_date,created_by_user_id,creation_key,creation_fingerprint) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$party['party_id'],$party['party_name'],$title,financeTeamDecimal($cents),$categoryId,$dueDate,$actor,$key,$fingerprint]);
    $id=(int)$pdo->lastInsertId();$charge=financeTeamChargeSnapshot($pdo,$id);
    if(!hash_equals($charge['creation_fingerprint'],$fingerprint))throw new DomainException('Este envio ja foi utilizado. Reabra o formulario para outra cobranca.');
    $history=$pdo->prepare("SELECT 1 FROM finance_team_charge_history WHERE charge_id=? AND action='created' LIMIT 1");$history->execute([$id]);
    if(!$history->fetchColumn())financeTeamChargeAudit($pdo,$id,'created',$actor);
    return $id;
}

function financeTeamChargePay(PDO $pdo,int $chargeId,array $input,int $actor,?string $receipt=null): int
{
    if($chargeId<=0||$actor<=0)throw new DomainException('Cobranca ou responsavel invalido.');
    $amount=financeTeamCents((string)($input['amount']??''));if($amount<=0)throw new DomainException('O recebimento precisa ser positivo.');
    if(!financeTeamDate((string)($input['paid_at']??'')))throw new DomainException('Informe a data do recebimento.');
    $key=(string)($input['request_key']??'');if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('Reabra a cobranca antes de registrar o recebimento.');
    $party=financeTeamParty($pdo,$input);
    $method=trim((string)($input['payment_method']??''));
    if($method!==''&&!isset(financePaymentMethodOptions()[$method]))throw new DomainException('Forma de pagamento invalida.');
    $fingerprint=hash('sha256',json_encode([$chargeId,$actor,$amount,$input['paid_at'],$party,$method],JSON_THROW_ON_ERROR));
    $pdo->beginTransaction();
    try{
        $lock=$pdo->prepare('SELECT id FROM finance_team_charges WHERE id=? FOR UPDATE');$lock->execute([$chargeId]);
        if(!$lock->fetchColumn())throw new DomainException('Cobranca nao encontrada neste projeto.');
        $retry=$pdo->prepare('SELECT charge_id,entry_id,request_fingerprint FROM finance_team_charge_payments WHERE request_key=?');$retry->execute([$key]);$previous=$retry->fetch(PDO::FETCH_ASSOC);
        if($previous){
            if((int)$previous['charge_id']!==$chargeId||!hash_equals($previous['request_fingerprint'],$fingerprint))throw new DomainException('Este envio ja foi utilizado. Reabra a cobranca para outro recebimento.');
            $pdo->commit();return (int)$previous['entry_id'];
        }
        $before=financeTeamChargeSnapshot($pdo,$chargeId);
        if($before['status']!=='open')throw new DomainException('Cobranca cancelada.');
        if($amount>financeTeamCents($before['remaining_amount']))throw new DomainException('O valor ultrapassa o saldo restante da cobranca.');
        $pdo->prepare("INSERT INTO finance_entries(category_id,type,title,description,amount,party_type,party_module,party_id,party_name,due_date,paid_at,status,source,payment_method,receipt_path,created_by_user_id,updated_by_user_id) VALUES(?,'income',?,?,?,?,?,?,?,?,?,'paid','team_payment',?,?,?,?)")->execute([$before['category_id'],mb_substr('Recebimento: '.$before['title'],0,150),'Cobranca #'.$chargeId.' de '.$before['beneficiary_name'],financeTeamDecimal($amount),$party['party_type'],$party['party_module'],$party['party_id'],$party['party_name'],$before['due_date'],$input['paid_at'],$method?:null,$receipt,$actor,$actor]);
        $id=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO finance_team_charge_payments(charge_id,entry_id,request_key,request_fingerprint) VALUES(?,?,?,?)')->execute([$chargeId,$id,$key,$fingerprint]);
        financeTeamAudit($pdo,$id,'created',$actor);
        financeTeamChargeAudit($pdo,$chargeId,'payment_recorded',$actor,$before,$id);
        $pdo->commit();return $id;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function financeTeamChargeCancel(PDO $pdo,int $id,string $reason,int $actor): void
{
    $reason=trim($reason);
    if($id<=0||$actor<=0||$reason===''||mb_strlen($reason)>500)throw new DomainException('Informe o motivo do cancelamento, com ate 500 caracteres.');
    $pdo->beginTransaction();
    try{
        $lock=$pdo->prepare('SELECT id FROM finance_team_charges WHERE id=? FOR UPDATE');$lock->execute([$id]);
        if(!$lock->fetchColumn())throw new DomainException('Cobranca nao encontrada.');
        $before=financeTeamChargeSnapshot($pdo,$id);
        if($before['status']==='canceled'){$pdo->commit();return;}
        if(financeTeamCents($before['received_amount'])>0)throw new DomainException('A cobranca possui recebimentos. Regularize esses registros antes de cancelar.');
        $pdo->prepare("UPDATE finance_team_charges SET status='canceled' WHERE id=?")->execute([$id]);
        financeTeamChargeAudit($pdo,$id,'canceled',$actor,$before,null,$reason);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
