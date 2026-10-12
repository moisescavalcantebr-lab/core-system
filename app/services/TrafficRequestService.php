<?php
declare(strict_types=1);

final class TrafficRequestService
{
    public const STATUSES = [
        'draft' => 'Rascunho', 'submitted' => 'Enviada', 'reviewing' => 'Em analise',
        'changes_requested' => 'Aguardando ajustes', 'prepared' => 'Campanha preparada',
        'rejected' => 'Nao atendida', 'cancelled' => 'Cancelada',
        'platform_review'=>'Em revisao na plataforma','platform_rejected'=>'Recusada pela plataforma',
        'active'=>'Em veiculacao','paused'=>'Pausada','completed'=>'Concluida',
    ];
    public const CONVERSATION_STATUSES = ['submitted','reviewing','changes_requested','prepared','platform_review','platform_rejected','active','paused'];
    public const PLATFORMS = ['recommend' => 'Preciso de orientacao', 'meta' => 'Meta', 'google' => 'Google'];
    public const MESSAGE_TEMPLATES = [
        'received' => ['label'=>'Recebimento', 'message'=>'Recebemos sua solicitacao e vamos conferir as informacoes.'],
        'guidance' => ['label'=>'Esclarecimento', 'message'=>'Para orientar a divulgacao, precisamos entender melhor seu publico e sua regiao.'],
        'reviewing' => ['label'=>'Analise iniciada', 'message'=>'Estamos avaliando o destino e as opcoes de divulgacao.'],
        'changes' => ['label'=>'Ajustes necessarios', 'message'=>'Precisamos dos ajustes abaixo para continuar:'],
        'prepared' => ['label'=>'Preparacao concluida', 'message'=>'A campanha foi preparada em rascunho. Ainda nao esta em veiculacao.'],
    ];

    public static function ensureSchema(PDO $pdo): void
    {
        require_once __DIR__.'/TrafficOperationsService.php';
        TrafficOperationsService::ensureSchema($pdo);
        $pdo->exec((string)file_get_contents(dirname(__DIR__).'/database/traffic_requests.sql'));
        $column = $pdo->query("SHOW COLUMNS FROM content_studio_traffic_requests LIKE 'guidance_question'")->fetch();
        if (!$column) {
            try { $pdo->exec('ALTER TABLE content_studio_traffic_requests ADD COLUMN guidance_question TEXT NULL AFTER platform'); }
            catch (PDOException $e) { if ((int)($e->errorInfo[1] ?? 0) !== 1060) throw $e; }
        }
        if (!$pdo->query("SHOW COLUMNS FROM content_studio_traffic_requests LIKE 'conversation_open'")->fetch()) {
            try { $pdo->exec('ALTER TABLE content_studio_traffic_requests ADD COLUMN conversation_open TINYINT(1) NOT NULL DEFAULT 0 AFTER status'); }
            catch (PDOException $e) { if ((int)($e->errorInfo[1] ?? 0) !== 1060) throw $e; }
        }
    }

    public static function projectId(PDO $pdo, array $config, string $slug): int
    {
        $stmt = $pdo->prepare("SELECT id FROM projects WHERE slug = ? AND status = 'active'");
        $stmt->execute([$slug]);
        $id = (int)$stmt->fetchColumn();
        if (!$id || (isset($config['id']) && (int)$config['id'] !== $id)) {
            throw new RuntimeException('Projeto nao identificado no Core.');
        }
        return $id;
    }

    public static function find(PDO $pdo, int $id, ?int $projectId = null, bool $lock = false): array
    {
        $stmt = $pdo->prepare('SELECT * FROM content_studio_traffic_requests WHERE id = ?'
            .($projectId !== null ? ' AND project_id = ?' : '').($lock ? ' FOR UPDATE' : ''));
        $stmt->execute($projectId !== null ? [$id, $projectId] : [$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Solicitacao nao encontrada.');
        return $row;
    }

    public static function list(PDO $pdo, ?int $projectId = null, string $status = ''): array
    {
        $where = []; $params = [];
        if ($projectId !== null) { $where[] = 'r.project_id = ?'; $params[] = $projectId; }
        else $where[] = "r.status <> 'draft'";
        if (isset(self::STATUSES[$status])) { $where[] = 'r.status = ?'; $params[] = $status; }
        $stmt = $pdo->prepare('SELECT r.*, p.name AS project_name FROM content_studio_traffic_requests r'
            .' INNER JOIN projects p ON p.id = r.project_id WHERE '.implode(' AND ', $where)
            .' ORDER BY r.updated_at DESC, r.id DESC LIMIT 200');
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function events(PDO $pdo, int $id, ?int $projectId = null): array
    {
        self::find($pdo, $id, $projectId);
        $stmt = $pdo->prepare('SELECT * FROM content_studio_traffic_events WHERE request_id = ? ORDER BY id');
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function text(array $data, string $key, int $limit): string
    {
        $value = $data[$key] ?? '';
        if (!is_string($value)) throw new InvalidArgumentException('Campo invalido: '.$key);
        $value = trim($value);
        $length = iconv_strlen($value, 'UTF-8');
        if ($length === false || $length > $limit) throw new InvalidArgumentException('Campo muito longo ou invalido: '.$key);
        return $value;
    }

    public static function url(string $value): string
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) throw new InvalidArgumentException('Informe um link HTTP ou HTTPS valido.');
        $parts = parse_url($value);
        if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || strlen($value) > 500) {
            throw new InvalidArgumentException('Link de destino invalido.');
        }
        return $value;
    }

    public static function payload(array $post, array $user, ?array $page = null): array
    {
        $data = [];
        foreach (['title'=>160,'business_name'=>160,'contact_phone'=>40,'objective'=>120,'region'=>180,
            'audience'=>3000,'language'=>60,'materials'=>3000,'notes'=>5000,'guidance_question'=>3000] as $key=>$limit) {
            $data[$key] = self::text($post, $key, $limit);
        }
        if ($data['title'] === '') throw new InvalidArgumentException('Informe o titulo da solicitacao.');
        $data['contact_name'] = self::text($user, 'name', 140);
        $data['contact_email'] = self::text($user, 'email', 180);
        $data['platform'] = self::text($post, 'platform', 20);
        $data['destination_type'] = self::text($post, 'destination_type', 20);
        if (!isset(self::PLATFORMS[$data['platform']]) || !in_array($data['destination_type'], ['page','external','whatsapp'], true)) {
            throw new InvalidArgumentException('Plataforma ou tipo de destino invalido.');
        }
        $data['project_page_id'] = null; $data['page_title'] = null;
        $data['destination_url'] = self::text($post, 'destination_url', 500);
        if ($data['destination_type'] === 'page') {
            if (!$page || $page['status'] !== 'published') throw new InvalidArgumentException('Selecione uma pagina publicada do projeto.');
            $data['project_page_id'] = (int)$page['id']; $data['page_title'] = $page['title'];
            $data['destination_url'] = self::url($page['public_url']);
        } elseif ($data['destination_url'] !== '') {
            self::url($data['destination_url']);
            if ($data['destination_type'] === 'whatsapp'
                && !in_array(strtolower(parse_url($data['destination_url'], PHP_URL_HOST) ?? ''), ['wa.me','api.whatsapp.com','www.whatsapp.com','whatsapp.com'], true)) {
                throw new InvalidArgumentException('Informe um link oficial do WhatsApp.');
            }
        }
        foreach (['starts_at','ends_at'] as $key) {
            $value = self::text($post, $key, 10);
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if ($value !== '' && (!$date || $date->format('Y-m-d') !== $value)) throw new InvalidArgumentException('Data invalida.');
            $data[$key] = $value !== '' ? $value : null;
        }
        if ($data['starts_at'] && $data['ends_at'] && $data['ends_at'] < $data['starts_at']) {
            throw new InvalidArgumentException('O fim deve ser igual ou posterior ao inicio.');
        }
        $data['authorized_at'] = null;
        return $data;
    }

    private static function event(PDO $pdo, int $id, string $actor, int $actorId, string $status, string $message): void
    {
        $pdo->prepare('INSERT INTO content_studio_traffic_events (request_id,actor_type,actor_id,status,message) VALUES (?,?,?,?,?)')
            ->execute([$id,$actor,$actorId,$status,$message]);
    }

    private static function revision(array $row, int $revision): void
    {
        if ((int)$row['revision'] !== $revision) throw new RuntimeException('A solicitacao mudou. Atualize a pagina antes de continuar.');
    }

    public static function save(PDO $pdo, int $projectId, int $actorId, int $id, int $revision, array $data, bool $submit, bool $authorized): int
    {
        if ($projectId <= 0 || $actorId <= 0) throw new RuntimeException('Identificacao invalida.');
        if ($submit) {
            if (($data['platform'] ?? '') === 'recommend' && trim((string)($data['guidance_question'] ?? '')) === '') {
                throw new InvalidArgumentException('Informe sua duvida ou o que pretende alcancar para receber orientacao.');
            }
            foreach (['business_name','objective','region','destination_url','contact_email'] as $key) {
                if (($data[$key] ?? '') === '') throw new InvalidArgumentException('Preencha negocio, objetivo, regiao, destino e contato antes de enviar.');
            }
            if (!filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL) || !$authorized) {
                throw new InvalidArgumentException('Confirme o contato e a autorizacao para divulgar este negocio.');
            }
            $data['authorized_at'] = date('Y-m-d H:i:s');
        }
        $pdo->beginTransaction();
        try {
            $row = $id ? self::find($pdo,$id,$projectId,true) : null;
            if ($row) {
                self::revision($row,$revision);
                if (!in_array($row['status'], ['draft','changes_requested'], true)) throw new RuntimeException('Esta solicitacao nao pode ser editada.');
            }
            $status = $submit ? 'submitted' : ($row['status'] ?? 'draft');
            $data['status'] = $status;
            $data['submitted_at'] = $submit ? date('Y-m-d H:i:s') : ($row['submitted_at'] ?? null);
            if ($row) {
                $set = implode(',', array_map(static fn($key) => $key.' = ?', array_keys($data)));
                $pdo->prepare('UPDATE content_studio_traffic_requests SET '.$set.', revision = revision + 1 WHERE id = ? AND project_id = ?')
                    ->execute([...array_values($data),$id,$projectId]);
            } else {
                $data['project_id'] = $projectId; $data['project_user_id'] = $actorId;
                $pdo->prepare('INSERT INTO content_studio_traffic_requests ('.implode(',',array_keys($data)).') VALUES ('.implode(',',array_fill(0,count($data),'?')).')')
                    ->execute(array_values($data));
                $id = (int)$pdo->lastInsertId();
            }
            $eventMessage = $submit ? 'Solicitacao enviada para analise.' : 'Briefing salvo.';
            if ($submit && ($data['guidance_question'] ?? '') !== '') $eventMessage .= "\nDuvida do cliente: ".$data['guidance_question'];
            self::event($pdo,$id,'project',$actorId,$status,$eventMessage);
            $pdo->commit(); return $id;
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }

    public static function cancel(PDO $pdo, int $id, int $projectId, int $actorId, int $revision): void
    {
        if ($projectId <= 0 || $actorId <= 0) throw new RuntimeException('Identificacao invalida.');
        $pdo->beginTransaction();
        try {
            $row = self::find($pdo,$id,$projectId,true); self::revision($row,$revision);
            if (!in_array($row['status'],['draft','submitted','reviewing','changes_requested'],true)) throw new RuntimeException('Cancelamento indisponivel nesta etapa.');
            $pdo->prepare("UPDATE content_studio_traffic_requests SET status = 'cancelled', conversation_open = 0, revision = revision + 1 WHERE id = ?")->execute([$id]);
            self::event($pdo,$id,'project',$actorId,'cancelled','Solicitacao cancelada pelo projeto.');
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }

    public static function review(PDO $pdo, int $id, int $actorId, int $revision, string $decision, string $message): void
    {
        if ($actorId <= 0) throw new RuntimeException('Responsavel nao identificado.');
        $message = self::text(['message'=>$message],'message',5000);
        $pdo->beginTransaction();
        try {
            $row = self::find($pdo,$id,null,true); self::revision($row,$revision);
            if (in_array($decision,['reply','close_conversation'],true)) {
                if (!in_array($row['status'],self::CONVERSATION_STATUSES,true)) {
                    throw new RuntimeException('Resposta indisponivel nesta etapa.');
                }
                if ($decision === 'reply' && $message === '') throw new InvalidArgumentException('Escreva uma resposta antes de enviar.');
                if ($decision === 'close_conversation' && !(int)$row['conversation_open']) throw new RuntimeException('A conversa ja esta encerrada.');
                $open = $decision === 'reply' ? 1 : 0;
                $pdo->prepare('UPDATE content_studio_traffic_requests SET conversation_open = ?, revision = revision + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$open,$id]);
                if ($decision === 'close_conversation') $message = ($message !== '' ? $message."\n" : '').'Conversa encerrada pelo Content Studio.';
                self::event($pdo,$id,'core',$actorId,$row['status'],$message);
                $pdo->commit(); return;
            }
            $allowed = ['submitted'=>['reviewing','changes_requested','rejected'], 'reviewing'=>['changes_requested','rejected','prepared']];
            if (!in_array($decision,$allowed[$row['status']] ?? [],true)) throw new RuntimeException('Transicao indisponivel.');
            if (in_array($decision,['reviewing','prepared'],true) && (int)$row['conversation_open']) {
                throw new RuntimeException('Encerre a conversa com o cliente antes de continuar a analise.');
            }
            if (in_array($decision,['changes_requested','rejected'],true) && $message === '') throw new InvalidArgumentException('Informe o motivo para o cliente.');
            $campaignId = $row['campaign_id'];
            if ($decision === 'prepared') {
                // The locked request makes campaign preparation atomic and prevents duplicate campaigns.
                $key = 'trafego-'.$id.'-'.bin2hex(random_bytes(4));
                $pdo->prepare("INSERT INTO content_studio_campaigns (name,slug,campaign_key,objective,project_id,landing_url,status,starts_at,ends_at,notes) VALUES (?,?,?,?,?,?,'draft',?,?,?)")
                    ->execute([$row['title'],$key,$key,$row['objective'],$row['project_id'],$row['destination_url'],$row['starts_at'],$row['ends_at'],
                        'Solicitacao #'.$id.' | Plataforma: '.$row['platform'].' | Regiao: '.$row['region']."\n".$row['notes']]);
                $campaignId = (int)$pdo->lastInsertId();
            }
            $pdo->prepare('UPDATE content_studio_traffic_requests SET status = ?, campaign_id = ?, conversation_open = ?, revision = revision + 1 WHERE id = ?')
                ->execute([$decision,$campaignId,$decision === 'rejected' ? 0 : ($decision === 'changes_requested' ? 1 : (int)$row['conversation_open']),$id]);
            self::event($pdo,$id,'core',$actorId,$decision,$message !== '' ? $message : ($decision === 'prepared' ? 'Campanha preparada em rascunho. Sem cobranca ou veiculacao.' : 'Analise iniciada.'));
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }

    public static function clientReply(PDO $pdo, int $id, int $projectId, int $actorId, int $revision, string $message): void
    {
        if ($projectId <= 0 || $actorId <= 0) throw new RuntimeException('Identificacao invalida.');
        $message = self::text(['message'=>$message],'message',5000);
        if ($message === '') throw new InvalidArgumentException('Escreva sua resposta antes de enviar.');
        $pdo->beginTransaction();
        try {
            $row = self::find($pdo,$id,$projectId,true); self::revision($row,$revision);
            if (!(int)$row['conversation_open'] || !in_array($row['status'],self::CONVERSATION_STATUSES,true)) {
                throw new RuntimeException('A conversa nao esta aberta para respostas.');
            }
            $pdo->prepare('UPDATE content_studio_traffic_requests SET revision = revision + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$id]);
            self::event($pdo,$id,'project',$actorId,$row['status'],$message);
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }
}
