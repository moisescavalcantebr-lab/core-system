<?php
declare(strict_types=1);

final class TrafficDeliveryService
{
    public static function ensureSchema(PDO $pdo): void
    {
        $pdo->exec((string)file_get_contents(dirname(__DIR__).'/database/traffic_deliveries.sql'));
    }

    public static function find(PDO $pdo,int $id,?int $projectId=null): ?array
    {
        TrafficRequestService::find($pdo,$id,$projectId);
        $stmt=$pdo->prepare('SELECT * FROM content_studio_traffic_deliveries WHERE request_id=?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function coverage(array $reports): array
    {
        $known=['impressions'=>0,'link_clicks'=>0,'visits'=>0];
        foreach ($reports as $report) foreach ($known as $key=>$count) if ($report[$key]!==null) $known[$key]++;
        $days=array_column($reports,'report_date');
        return ['days'=>count($reports),'first_day'=>$days?min($days):null,'last_day'=>$days?max($days):null,'known'=>$known];
    }

    private static function current(PDO $pdo,array $row): array
    {
        $id=(int)$row['id'];
        $reports=TrafficOperationsService::reports($pdo,$id);
        $approval=TrafficApprovalService::latest($pdo,$id);
        // Reports never include project leads, account contacts or bank information.
        return ['request'=>array_intersect_key($row,array_flip(['id','project_id','title','business_name','destination_url','language','status','campaign_id'])),
            'plan'=>TrafficOperationsService::plan($pdo,$id),'reports'=>$reports,'totals'=>TrafficOperationsService::totals($reports),
            'coverage'=>self::coverage($reports),'approval_version'=>$approval && $approval['status']==='approved'?(int)$approval['version']:null];
    }

    public static function report(PDO $pdo,int $id,?int $projectId=null): array
    {
        $row=TrafficRequestService::find($pdo,$id,$projectId);
        $delivery=self::find($pdo,$id,$projectId);
        if ($delivery) {
            if (!hash_equals($delivery['fingerprint'],hash('sha256',$delivery['snapshot']))) throw new RuntimeException('Relatorio final inconsistente. Solicite verificacao ao Studio.');
            return ['data'=>json_decode($delivery['snapshot'],true,512,JSON_THROW_ON_ERROR),'delivery'=>$delivery];
        }
        if (!$row['campaign_id']) throw new RuntimeException('Relatorio disponivel apos preparar a campanha.');
        return ['data'=>self::current($pdo,$row),'delivery'=>null];
    }

    public static function finalize(PDO $pdo,int $id,int $actor,int $revision,array $post): void
    {
        if ($actor<=0) throw new RuntimeException('Responsavel nao identificado.');
        $summary=TrafficRequestService::text($post,'delivery_summary',5000);
        if ($summary==='') throw new InvalidArgumentException('Informe o resumo da entrega e suas limitacoes.');
        $evidence=TrafficRequestService::url(TrafficRequestService::text($post,'delivery_evidence_url',500));
        if (($post['delivery_confirmed'] ?? '')!=='1') throw new InvalidArgumentException('Confirme a conferencia dos resultados e da conclusao na plataforma.');
        $pdo->beginTransaction();
        try {
            $row=TrafficRequestService::find($pdo,$id,null,true);
            if ((int)$row['revision']!==$revision) throw new RuntimeException('A solicitacao mudou. Atualize a pagina.');
            if ($row['status']!=='completed' || !$row['campaign_id']) throw new RuntimeException('Registre a conclusao da veiculacao antes de encerrar a entrega.');
            if ((int)$row['conversation_open']) throw new RuntimeException('Encerre a conversa antes de finalizar a entrega.');
            if (self::find($pdo,$id)) throw new RuntimeException('Esta entrega ja foi encerrada.');
            $data=self::current($pdo,$row);
            if (!$data['plan'] || !$data['plan']['actual_ended_at'] || !$data['reports']) throw new RuntimeException('Registre o termino e pelo menos um relatorio diario antes de encerrar.');
            $snapshot=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $pdo->prepare('INSERT INTO content_studio_traffic_deliveries (request_id,snapshot,fingerprint,summary,evidence_url,finalized_by) VALUES (?,?,?,?,?,?)')
                ->execute([$id,$snapshot,hash('sha256',$snapshot),$summary,$evidence,$actor]);
            $pdo->prepare("INSERT INTO content_studio_traffic_events (request_id,actor_type,actor_id,status,message) VALUES (?,'core',?,'completed',?)")
                ->execute([$id,$actor,"Entrega encerrada. Relatorio final disponibilizado ao cliente.\n".$summary."\nReferencia final: ".$evidence]);
            $pdo->prepare('UPDATE content_studio_traffic_requests SET revision=revision+1,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$id]);
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }

    public static function csv(array $reports): string
    {
        $stream=fopen('php://temp','w+');
        fputcsv($stream,['Dia','Impressoes','Cliques no link','Visitas medidas','Fonte','Referencia','Observacoes'],';', '"', '');
        foreach ($reports as $report) {
            $values=[];
            foreach (['report_date','impressions','link_clicks','visits','source','evidence_url','notes'] as $key) {
                $value=(string)($report[$key] ?? '');
                // Neutralize spreadsheet formulas, including leading whitespace/control characters.
                if (preg_match('/^[\s\x00-\x20]*[=+@-]/u',$value) || preg_match('/^[\t\r\n]/',$value)) $value="'".$value;
                $values[]=$value;
            }
            fputcsv($stream,$values,';','"','');
        }
        rewind($stream); $csv=stream_get_contents($stream); fclose($stream);
        return "\xEF\xBB\xBF".$csv;
    }
}
