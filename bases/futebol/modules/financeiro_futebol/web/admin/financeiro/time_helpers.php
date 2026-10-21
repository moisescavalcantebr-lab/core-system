<?php
declare(strict_types=1);
require_once __DIR__.'/time_charge_helpers.php';
require_once __DIR__.'/time_group_helpers.php';

function financeTeamEnsureSchema(PDO $pdo): void
{
    static $done=[];
    $key=spl_object_id($pdo);if(isset($done[$key]))return;
    $pdo->exec((string)file_get_contents(PROJECT_PATH.'/modules/financeiro_futebol/database/schema.sql'));
    $done[$key]=true;
}

function financeTeamTracked(PDO $pdo,int $id): bool
{
    $stmt=$pdo->prepare('SELECT 1 FROM finance_team_history WHERE entry_id=? LIMIT 1');$stmt->execute([$id]);return (bool)$stmt->fetchColumn();
}

function financeTeamPlayers(PDO $pdo): array
{
    return $pdo->query('SELECT id,name,status FROM players ORDER BY name,id')->fetchAll(PDO::FETCH_ASSOC);
}

function financeTeamDate(string $value): bool
{
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    return $date!==false && $date->format('Y-m-d')===$value;
}

/** Validate the selected counterparty independently of the participant login. */
function financeTeamParty(PDO $pdo,array $input,?array $existing=null): array
{
    $kind=(string)($input['team_party_kind']??'');
    if($kind==='player'){
        $id=filter_var($input['team_player_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$id)throw new DomainException('Selecione um jogador cadastrado no projeto.');
        $stmt=$pdo->prepare('SELECT id,name FROM players WHERE id=?');$stmt->execute([$id]);$player=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$player)throw new DomainException('Jogador nao encontrado neste projeto.');
        $name=trim((string)$player['name']);
        if($existing && ($existing['party_module']??'')==='jogadores' && (int)$existing['party_id']===$id && trim((string)($existing['party_name']??''))!=='')$name=$existing['party_name'];
        if($name==='')throw new DomainException('O jogador precisa ter nome cadastrado.');
        return ['party_type'=>'member','party_module'=>'jogadores','party_id'=>$id,'party_name'=>$name];
    }
    if($kind!=='external')throw new DomainException('Identifique de quem veio ou para quem foi o valor.');
    $name=trim((string)($input['team_party_name']??''));
    if($name===''||mb_strlen($name)>150)throw new DomainException('Informe o nome da pessoa ou empresa, com ate 150 caracteres.');
    return ['party_type'=>'other','party_module'=>null,'party_id'=>null,'party_name'=>$name];
}

function financeTeamValidate(array $input): void
{
    if(!in_array($input['type']??'', ['income','expense'],true))throw new DomainException('Selecione entrada ou saida.');
    if(!in_array($input['status']??'', ['pending','paid'],true))throw new DomainException('Use o cancelamento com motivo para cancelar um lancamento.');
    if(trim((string)($input['title']??''))===''||mb_strlen((string)$input['title'])>150)throw new DomainException('Informe a finalidade, com ate 150 caracteres.');
    $amount=(string)($input['amount']??'');
    if(!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',$amount)||(float)$amount<=0)throw new DomainException('Informe um valor positivo com ate duas casas decimais.');
    $dateKey=$input['status']==='paid'?'paid_at':'due_date';
    if(!financeTeamDate((string)($input[$dateKey]??'')))throw new DomainException($dateKey==='paid_at'?'Informe a data do recebimento ou pagamento.':'Informe a data prevista.');
    foreach(['paid_at','due_date']as$key)if(!empty($input[$key])&&!financeTeamDate((string)$input[$key]))throw new DomainException('Data invalida.');
}

function financeTeamAudit(PDO $pdo,int $id,string $action,int $actor,?array $before=null,?string $reason=null): void
{
    if(!$pdo->inTransaction()||$actor<=0)throw new RuntimeException('Historico exige transacao e responsavel.');
    $stmt=$pdo->prepare('SELECT * FROM finance_entries WHERE id=?');$stmt->execute([$id]);$after=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$after)throw new RuntimeException('Lancamento nao encontrado.');
    $pdo->prepare('INSERT INTO finance_team_history(entry_id,action,actor_user_id,reason,before_json,after_json) VALUES(?,?,?,?,?,?)')->execute([$id,$action,$actor,$reason,$before?json_encode($before,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE):null,json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
}

function financeTeamGuardEditable(PDO $pdo,array $entry): void
{
    if(financeTeamTracked($pdo,(int)$entry['id'])&&$entry['status']!=='pending')throw new DomainException('Lancamento confirmado ou cancelado nao pode ser reescrito. Use cancelar com motivo e registre a correcao.');
}

function financeTeamFinish(PDO $pdo,array $ids,int $actor): void
{
    foreach($ids as$id)financeTeamAudit($pdo,$id,'created',$actor);
    $pdo->commit();
}
