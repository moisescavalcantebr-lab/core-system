<?php
declare(strict_types=1);

final class TrafficApprovalService
{
    public const STATUSES = ['pending'=>'Aguardando aprovacao','approved'=>'Aprovado pelo cliente',
        'changes_requested'=>'Cliente solicitou ajustes','invalidated'=>'Versao substituida'];

    public static function ensureSchema(PDO $pdo): void
    {
        $pdo->exec((string)file_get_contents(dirname(__DIR__).'/database/traffic_approvals.sql'));
    }

    public static function latest(PDO $pdo, int $id, ?int $projectId = null): ?array
    {
        TrafficRequestService::find($pdo,$id,$projectId);
        $stmt=$pdo->prepare('SELECT * FROM content_studio_traffic_approvals WHERE request_id=? ORDER BY version DESC LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function snapshot(PDO $pdo, array $row): string
    {
        $plan=TrafficOperationsService::plan($pdo,(int)$row['id']);
        if (!$plan) throw new RuntimeException('Salve o planejamento antes de solicitar aprovacao.');
        // Platform identifiers and evidence are operational metadata, not the approved advertising content.
        $data=['destination_url'=>$row['destination_url'],'language'=>$row['language']];
        foreach (['platform','objective','region','audience','placements','ad_title','ad_text','creative_url','starts_at','ends_at'] as $key) $data[$key]=$plan[$key];
        return json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }

    public static function isApproved(PDO $pdo, array $row): bool
    {
        $approval=self::latest($pdo,(int)$row['id']);
        return $approval && $approval['status']==='approved' && hash_equals($approval['fingerprint'],hash('sha256',self::snapshot($pdo,$row)));
    }

    public static function invalidateChanged(PDO $pdo, array $row, int $actor): void
    {
        $approval=self::latest($pdo,(int)$row['id']);
        if ($approval && in_array($approval['status'],['pending','approved'],true)
            && !hash_equals($approval['fingerprint'],hash('sha256',self::snapshot($pdo,$row)))) {
            $pdo->prepare("UPDATE content_studio_traffic_approvals SET status='invalidated' WHERE id=?")->execute([$approval['id']]);
            $pdo->prepare("INSERT INTO content_studio_traffic_events (request_id,actor_type,actor_id,status,message) VALUES (?,'core',?,? ,?)")
                ->execute([$row['id'],$actor,$row['status'],'Planejamento alterado. A versao '.$approval['version'].' nao autoriza a nova divulgacao.']);
        }
    }

    private static function event(PDO $pdo, array $row, string $type, int $actor, string $message): void
    {
        $pdo->prepare('INSERT INTO content_studio_traffic_events (request_id,actor_type,actor_id,status,message) VALUES (?,?,?,?,?)')
            ->execute([$row['id'],$type,$actor,$row['status'],$message]);
        $pdo->prepare('UPDATE content_studio_traffic_requests SET revision=revision+1,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$row['id']]);
    }

    private static function locked(PDO $pdo,int $id,?int $projectId,int $actor,int $revision): array
    {
        if ($actor<=0) throw new RuntimeException('Responsavel nao identificado.');
        $row=TrafficRequestService::find($pdo,$id,$projectId,true);
        if ((int)$row['revision']!==$revision) throw new RuntimeException('A solicitacao mudou. Atualize a pagina.');
        if (!$row['campaign_id'] || !in_array($row['status'],['prepared','platform_rejected'],true)) throw new RuntimeException('Aprovacao indisponivel nesta etapa.');
        if ((int)$row['conversation_open']) throw new RuntimeException('Encerre a conversa antes de solicitar ou decidir a aprovacao.');
        return $row;
    }

    public static function offer(PDO $pdo,int $id,int $actor,int $revision): void
    {
        $pdo->beginTransaction();
        try {
            $row=self::locked($pdo,$id,null,$actor,$revision);
            $snapshot=self::snapshot($pdo,$row);
            $latest=self::latest($pdo,$id);
            if ($latest && in_array($latest['status'],['pending','approved'],true)) throw new RuntimeException('Esta versao ja foi enviada ou aprovada.');
            $version=($latest ? (int)$latest['version'] : 0)+1;
            $pdo->prepare('INSERT INTO content_studio_traffic_approvals (request_id,version,snapshot,fingerprint,offered_by) VALUES (?,?,?,?,?)')
                ->execute([$id,$version,$snapshot,hash('sha256',$snapshot),$actor]);
            self::event($pdo,$row,'core',$actor,'Planejamento versao '.$version.' enviado para aprovacao do cliente.');
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }

    public static function decide(PDO $pdo,int $id,int $projectId,int $actor,int $revision,int $version,string $decision,string $message,bool $confirmed): void
    {
        if (!in_array($decision,['approve','request_changes'],true)) throw new InvalidArgumentException('Decisao invalida.');
        $message=TrafficRequestService::text(['message'=>$message],'message',3000);
        if ($decision==='approve' && !$confirmed) throw new InvalidArgumentException('Confirme a aprovacao do planejamento apresentado.');
        if ($decision==='request_changes' && $message==='') throw new InvalidArgumentException('Descreva os ajustes desejados.');
        $pdo->beginTransaction();
        try {
            $row=self::locked($pdo,$id,$projectId,$actor,$revision);
            $latest=self::latest($pdo,$id,$projectId);
            if (!$latest || (int)$latest['version']!==$version || $latest['status']!=='pending'
                || !hash_equals($latest['fingerprint'],hash('sha256',self::snapshot($pdo,$row)))) throw new RuntimeException('Versao indisponivel. Atualize a pagina.');
            $status=$decision==='approve'?'approved':'changes_requested';
            $pdo->prepare('UPDATE content_studio_traffic_approvals SET status=?,decided_by=?,decided_at=CURRENT_TIMESTAMP,message=? WHERE id=?')
                ->execute([$status,$actor,$message,$latest['id']]);
            self::event($pdo,$row,'project',$actor,'Planejamento versao '.$version.': '.self::STATUSES[$status].($message!==''?"\n".$message:''));
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }
}
