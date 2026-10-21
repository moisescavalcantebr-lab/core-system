<?php
declare(strict_types=1);

function financeTeamGroupPlan(PDO $pdo,array $input,bool $lock=false): array
{
    if($lock&&!$pdo->inTransaction())throw new LogicException('Previa bloqueada exige transacao.');
    $ids=$input['player_ids']??[];
    if(!is_array($ids)||count($ids)<1||count($ids)>30)throw new DomainException('Selecione de 1 a 30 jogadores.');
    $normalized=[];
    foreach($ids as$id){$value=filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$value)throw new DomainException('Jogador invalido.');$normalized[]=$value;}
    sort($normalized,SORT_NUMERIC);
    if(count(array_unique($normalized))!==count($normalized))throw new DomainException('A selecao contem jogador repetido.');
    $mode=(string)($input['allocation_mode']??'');
    if(!in_array($mode,['per_player','split_total'],true))throw new DomainException('Selecione valor por jogador ou divisao do total.');
    $cents=financeTeamCents((string)($input['amount']??''));
    if($cents<=0||($mode==='split_total'&&$cents<count($normalized)))throw new DomainException('Cada cobranca precisa ter pelo menos um centavo.');
    $title=trim((string)($input['title']??''));if($title===''||mb_strlen($title)>150)throw new DomainException('Informe a finalidade, com ate 150 caracteres.');
    $date=(string)($input['due_date']??'');if(!financeTeamDate($date))throw new DomainException('Informe uma data prevista valida.');
    $stmt=$pdo->prepare('SELECT id,name,status FROM players WHERE id IN ('.implode(',',array_fill(0,count($normalized),'?')).') ORDER BY id'.($lock?' LOCK IN SHARE MODE':''));$stmt->execute($normalized);$players=$stmt->fetchAll(PDO::FETCH_ASSOC);
    if(count($players)!==count($normalized))throw new DomainException('Um dos jogadores nao existe neste projeto.');
    $categoryId=null;
    if(!empty($input['category_id'])){
        $stmt=$pdo->prepare("SELECT id FROM finance_categories WHERE id=? AND status='active' AND type IN ('income','both')".($lock?' LOCK IN SHARE MODE':''));$stmt->execute([(int)$input['category_id']]);$categoryId=$stmt->fetchColumn();
        if(!$categoryId)throw new DomainException('Selecione uma categoria de entrada valida.');
        $categoryId=(int)$categoryId;
    }
    $rows=[];$base=$mode==='per_player'?$cents:intdiv($cents,count($players));$extra=$mode==='split_total'?$cents%count($players):0;
    foreach($players as$index=>$player){
        $name=trim((string)$player['name']);if($name==='')throw new DomainException('Um dos jogadores precisa de nome cadastrado.');
        $rows[]=['player_id'=>(int)$player['id'],'name'=>$name,'status'=>$player['status'],'amount'=>financeTeamDecimal($base+($index<$extra?1:0))];
    }
    return ['title'=>$title,'allocation_mode'=>$mode,'input_amount'=>financeTeamDecimal($cents),'total_amount'=>financeTeamDecimal($mode==='per_player'?$cents*count($players):$cents),'due_date'=>$date,'category_id'=>$categoryId,'players'=>$rows];
}

function financeTeamGroupSignature(array $plan,string $key,int $actor,string $secret): string
{
    if($actor<=0||!preg_match('/^[a-f0-9]{64}$/D',$key)||!preg_match('/^[a-f0-9]{64}$/D',$secret))throw new DomainException('Reabra a previa antes de confirmar.');
    return hash_hmac('sha256',json_encode([$actor,$key,$plan],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$secret);
}

function financeTeamGroupCreate(PDO $pdo,array $input,int $actor,string $secret): int
{
    $key=(string)($input['request_key']??'');$signature=(string)($input['preview_signature']??'');
    financeTeamTransaction($pdo);
    try{
        // Revalidate the exact preview with current project rows held until commit.
        $plan=financeTeamGroupPlan($pdo,$input,true);
        if(!hash_equals(financeTeamGroupSignature($plan,$key,$actor,$secret),$signature))throw new DomainException('A previa mudou ou expirou. Confira os jogadores e valores novamente.');
        $json=json_encode($plan,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $fingerprint=hash('sha256',json_encode([$actor,$plan],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        $pdo->prepare('INSERT INTO finance_team_charge_batches(title,allocation_mode,input_amount,total_amount,due_date,category_id,plan_json,created_by_user_id,request_key,request_fingerprint) VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$plan['title'],$plan['allocation_mode'],$plan['input_amount'],$plan['total_amount'],$plan['due_date'],$plan['category_id'],$json,$actor,$key,$fingerprint]);
        $batchId=(int)$pdo->lastInsertId();
        $stmt=$pdo->prepare('SELECT request_fingerprint FROM finance_team_charge_batches WHERE id=?');$stmt->execute([$batchId]);
        if(!hash_equals((string)$stmt->fetchColumn(),$fingerprint))throw new DomainException('Este envio ja criou outro grupo. Reabra o formulario.');
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM finance_team_charge_batch_items WHERE batch_id=?');$stmt->execute([$batchId]);$existing=(int)$stmt->fetchColumn();
        if($existing){if($existing!==count($plan['players']))throw new LogicException('Grupo incompleto.');$pdo->commit();return $batchId;}
        foreach($plan['players']as$player){
            $party=['party_type'=>'member','party_module'=>'jogadores','party_id'=>$player['player_id'],'party_name'=>$player['name']];
            $childKey=hash('sha256','team-batch:'.$batchId.':'.$key.':'.$player['player_id']);
            $id=financeTeamChargeWrite($pdo,$party,$plan['title'],financeTeamCents($player['amount']),$plan['category_id'],$plan['due_date'],$actor,$childKey);
            $pdo->prepare('INSERT INTO finance_team_charge_batch_items(batch_id,charge_id,player_id,amount) VALUES(?,?,?,?)')->execute([$batchId,$id,$player['player_id'],$player['amount']]);
        }
        $pdo->commit();return $batchId;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
