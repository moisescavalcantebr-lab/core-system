<?php
declare(strict_types=1);

final class TrafficOperationsService
{
    public const TRANSITIONS = [
        'prepared'=>['platform_review'], 'platform_review'=>['active','platform_rejected'],
        'platform_rejected'=>['platform_review'], 'active'=>['paused','completed'],
        'paused'=>['active','completed'],
    ];
    public const PLAN_FIELDS = ['platform'=>'Plataforma definida','objective'=>'Objetivo definido','region'=>'Regiao definida',
        'audience'=>'Publico definido','placements'=>'Canais / posicionamentos','ad_title'=>'Titulo do anuncio',
        'ad_text'=>'Texto do anuncio','creative_url'=>'Material do anuncio','starts_at'=>'Inicio planejado','ends_at'=>'Fim planejado',
        'external_campaign_id'=>'ID na plataforma','external_campaign_url'=>'Referencia da campanha','evidence_url'=>'Referencia da veiculacao',
        'actual_started_at'=>'Inicio registrado','actual_ended_at'=>'Conclusao registrada'];

    public static function ensureSchema(PDO $pdo): void
    {
        $pdo->exec((string)file_get_contents(dirname(__DIR__).'/database/traffic_operations.sql'));
    }

    public static function plan(PDO $pdo, int $id, ?int $projectId = null): ?array
    {
        TrafficRequestService::find($pdo,$id,$projectId);
        $stmt=$pdo->prepare('SELECT * FROM content_studio_traffic_plans WHERE request_id = ?');
        $stmt->execute([$id]); return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function reports(PDO $pdo, int $id, ?int $projectId = null): array
    {
        TrafficRequestService::find($pdo,$id,$projectId);
        $stmt=$pdo->prepare('SELECT * FROM content_studio_traffic_reports WHERE request_id = ? ORDER BY report_date');
        $stmt->execute([$id]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function totals(array $reports): array
    {
        $totals=['impressions'=>null,'link_clicks'=>null,'visits'=>null];
        foreach ($reports as $report) foreach ($totals as $key=>$value) {
            if ($report[$key] !== null) $totals[$key]=($totals[$key] ?? 0)+(int)$report[$key];
        }
        return $totals;
    }

    public static function date(string $value): string
    {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if (!$date || $date->format('Y-m-d')!==$value) throw new InvalidArgumentException('Informe uma data valida.');
        return $value;
    }

    private static function locked(PDO $pdo,int $id,int $revision,int $actor): array
    {
        if ($actor<=0) throw new RuntimeException('Responsavel nao identificado.');
        $row=TrafficRequestService::find($pdo,$id,null,true);
        if ((int)$row['revision']!==$revision) throw new RuntimeException('A solicitacao mudou. Atualize a pagina.');
        if (!$row['campaign_id']) throw new RuntimeException('Prepare a campanha antes de registrar a operacao.');
        return $row;
    }

    private static function event(PDO $pdo,array $row,int $actor,string $message,string $status): void
    {
        $pdo->prepare('INSERT INTO content_studio_traffic_events (request_id,actor_type,actor_id,status,message) VALUES (?,\'core\',?,?,?)')
            ->execute([$row['id'],$actor,$status,$message]);
        $pdo->prepare('UPDATE content_studio_traffic_requests SET revision=revision+1, updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$row['id']]);
    }

    public static function savePlan(PDO $pdo,int $id,int $actor,int $revision,array $post): void
    {
        $data=[];
        foreach (['platform'=>20,'objective'=>120,'region'=>180,'audience'=>3000,'placements'=>255,
            'ad_title'=>160,'ad_text'=>5000,'creative_url'=>500,'external_campaign_id'=>120,'external_campaign_url'=>500,'evidence_url'=>500] as $key=>$limit) {
            $data[$key]=TrafficRequestService::text($post,$key,$limit);
        }
        if (!in_array($data['platform'],['meta','google'],true)) throw new InvalidArgumentException('Defina Meta ou Google para o planejamento.');
        foreach (['objective','region','ad_title','ad_text'] as $key) if ($data[$key]==='') throw new InvalidArgumentException('Preencha objetivo, regiao, titulo e texto do anuncio.');
        foreach (['creative_url','external_campaign_url','evidence_url'] as $key) if ($data[$key]!=='') TrafficRequestService::url($data[$key]);
        foreach (['starts_at','ends_at'] as $key) $data[$key]=self::date(TrafficRequestService::text($post,$key,10));
        if ($data['ends_at']<$data['starts_at']) throw new InvalidArgumentException('Periodo planejado invertido.');
        $pdo->beginTransaction();
        try {
            $row=self::locked($pdo,$id,$revision,$actor);
            if (!in_array($row['status'],['prepared','platform_rejected'],true)) throw new RuntimeException('Planejamento bloqueado nesta etapa.');
            $data['request_id']=$id;
            $columns=array_keys($data);
            $updates=implode(',',array_map(static fn($key)=>$key.'=VALUES('.$key.')',array_filter($columns,static fn($key)=>$key!=='request_id')));
            $pdo->prepare('INSERT INTO content_studio_traffic_plans ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).') ON DUPLICATE KEY UPDATE '.$updates)
                ->execute(array_values($data));
            $pdo->prepare('UPDATE content_studio_campaigns SET objective=?, starts_at=?, ends_at=? WHERE id=? AND project_id=?')
                ->execute([$data['objective'],$data['starts_at'],$data['ends_at'],$row['campaign_id'],$row['project_id']]);
            self::event($pdo,$row,$actor,'Planejamento da divulgacao atualizado pelo Studio.',$row['status']);
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }

    public static function transition(PDO $pdo,int $id,int $actor,int $revision,array $post): void
    {
        $next=TrafficRequestService::text($post,'operation_status',30);
        $message=TrafficRequestService::text($post,'operation_notes',3000);
        if (($post['manual_confirmed'] ?? '')!=='1') throw new InvalidArgumentException('Confirme a acao realizada manualmente na plataforma.');
        $pdo->beginTransaction();
        try {
            $row=self::locked($pdo,$id,$revision,$actor);
            if (!in_array($next,self::TRANSITIONS[$row['status']] ?? [],true)) throw new RuntimeException('Transicao operacional indisponivel.');
            if ((int)$row['conversation_open']) throw new RuntimeException('Encerre a conversa antes de atualizar a veiculacao.');
            $plan=self::plan($pdo,$id);
            if (!$plan || !$plan['external_campaign_id'] || !$plan['evidence_url']) throw new RuntimeException('Salve o planejamento, o ID da campanha e a referencia da operacao antes de continuar.');
            if (in_array($next,['platform_rejected','paused','completed'],true) && $message==='') throw new InvalidArgumentException('Informe o motivo ou resumo da operacao.');
            $pdo->prepare('UPDATE content_studio_traffic_requests SET status=? WHERE id=?')->execute([$next,$id]);
            $campaignStatus=match($next) {'active'=>'active','paused'=>'paused','completed'=>'finished',default=>'draft'};
            $pdo->prepare('UPDATE content_studio_campaigns SET status=? WHERE id=? AND project_id=?')->execute([$campaignStatus,$row['campaign_id'],$row['project_id']]);
            if ($next==='active') $pdo->prepare('UPDATE content_studio_traffic_plans SET actual_started_at=COALESCE(actual_started_at,CURRENT_TIMESTAMP) WHERE request_id=?')->execute([$id]);
            if ($next==='completed') $pdo->prepare('UPDATE content_studio_traffic_plans SET actual_ended_at=CURRENT_TIMESTAMP WHERE request_id=?')->execute([$id]);
            self::event($pdo,$row,$actor,'Registro manual: '.TrafficRequestService::STATUSES[$next].($message!==''?"\n".$message:''),$next);
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }

    public static function saveReport(PDO $pdo,int $id,int $actor,int $revision,array $post): void
    {
        $date=self::date(TrafficRequestService::text($post,'report_date',10));
        if ($date>date('Y-m-d')) throw new InvalidArgumentException('Nao registre resultados de uma data futura.');
        $metrics=[];
        foreach (['impressions','link_clicks','visits'] as $key) {
            $raw=TrafficRequestService::text($post,$key,10);
            if ($raw==='') { $metrics[$key]=null; continue; }
            if (!ctype_digit($raw) || (float)$raw>2147483647) throw new InvalidArgumentException('Informe quantidades inteiras e nao negativas.');
            $metrics[$key]=(int)$raw;
        }
        if (count(array_filter($metrics,static fn($value)=>$value!==null))===0) throw new InvalidArgumentException('Informe pelo menos uma metrica medida.');
        $source=TrafficRequestService::text($post,'source',180);
        if ($source==='') throw new InvalidArgumentException('Informe a fonte dos resultados.');
        $evidence=TrafficRequestService::url(TrafficRequestService::text($post,'report_evidence_url',500));
        $notes=TrafficRequestService::text($post,'report_notes',3000);
        $pdo->beginTransaction();
        try {
            $row=self::locked($pdo,$id,$revision,$actor);
            if (!in_array($row['status'],['active','paused','completed'],true)) throw new RuntimeException('Resultados disponiveis somente apos registrar a veiculacao.');
            $plan=self::plan($pdo,$id);
            if (!$plan['actual_started_at'] || $date<substr($plan['actual_started_at'],0,10)
                || ($plan['actual_ended_at'] && $date>substr($plan['actual_ended_at'],0,10))) throw new InvalidArgumentException('Data fora do periodo registrado de veiculacao.');
            $pdo->prepare('INSERT INTO content_studio_traffic_reports (request_id,report_date,impressions,link_clicks,visits,source,evidence_url,notes,recorded_by) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE impressions=VALUES(impressions),link_clicks=VALUES(link_clicks),visits=VALUES(visits),source=VALUES(source),evidence_url=VALUES(evidence_url),notes=VALUES(notes),recorded_by=VALUES(recorded_by)')
                ->execute([$id,$date,$metrics['impressions'],$metrics['link_clicks'],$metrics['visits'],$source,$evidence,$notes,$actor]);
            self::event($pdo,$row,$actor,'Resultados diarios registrados/atualizados: '.$date.".\nImpressoes: ".($metrics['impressions'] ?? 'nao informado')
                .' | Cliques no link: '.($metrics['link_clicks'] ?? 'nao informado').' | Visitas medidas: '.($metrics['visits'] ?? 'nao informado')
                ."\nFonte: ".$source."\nReferencia: ".$evidence,$row['status']);
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }
}
