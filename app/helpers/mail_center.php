<?php
declare(strict_types=1);

function mailCenterOwnerId(PDO $pdo, array $config, array $session): ?int
{
    $options = $config['mail_center'] ?? [];
    $owner = filter_var($options['owner_user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $actor = $session['core_user']['id'] ?? null;
    if (($options['enabled'] ?? false) !== true || !$owner || (string)$actor !== (string)$owner) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT id FROM core_users WHERE id = ? AND status = 1 AND role IN ('ADMIN', 'SUPER_ADMIN')");
    $stmt->execute([$owner]);
    return $stmt->fetchColumn() !== false ? (int)$owner : null;
}

function mailCenterPrivateHeaders(): void
{
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
}

function mailCenterRequireOwner(PDO $pdo, array $config): int
{
    $GLOBALS['mailPrivatePage'] = true;
    mailCenterPrivateHeaders();
    $owner = mailCenterOwnerId($pdo, $config, $_SESSION);
    if ($owner === null) {
        http_response_code(404);
        exit('Recurso indisponível.');
    }
    $last = (int)($_SESSION['mail_center_last_activity'] ?? $_SESSION['core_authenticated_at'] ?? 0);
    $timeout = max(300, min(7200, (int)($config['mail_center']['idle_timeout'] ?? 1800)));
    if (!$last || time() - $last > $timeout) {
        unset($_SESSION['core_user'], $_SESSION['_csrf'], $_SESSION['mail_center_last_activity']);
        header('Location: /web/admin/login.php');
        exit;
    }
    $_SESSION['mail_center_last_activity'] = time();
    return $owner;
}

function mailCenterValidPost(string $method, array $post, array $session): bool
{
    $expected = $session['_csrf'] ?? null;
    $provided = $post['_csrf'] ?? null;
    return $method === 'POST' && is_string($expected) && $expected !== ''
        && is_string($provided) && hash_equals($expected, $provided);
}

function mailCenterRequirePost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        exit('Método não permitido.');
    }
    if (!mailCenterValidPost('POST', $_POST, $_SESSION)) {
        http_response_code(403);
        exit('CSRF inválido. Atualize a página.');
    }
}

function mailCenterId(mixed $value, bool $optional = false): int
{
    if ($optional && ($value === null || $value === '' || $value === '0' || $value === 0)) {
        return 0;
    }
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) {
        throw new InvalidArgumentException('Identificador inválido.');
    }
    return $id;
}

function mailCenterResourceAccount(PDO $pdo,int $owner,string $resource,int $id): int
{
    $table=match($resource){'message'=>'core_mail_messages','draft'=>'core_mail_drafts','outbox'=>'core_mail_outbox','attachment'=>'core_mail_attachments',default=>throw new InvalidArgumentException('Recurso inválido.')};
    $s=$pdo->prepare('SELECT account_id FROM '.$table.' WHERE id=? AND owner_user_id=?');$s->execute([$id,$owner]);$account=$s->fetchColumn();
    if($account===false)throw new OutOfBoundsException('Registro indisponível.');
    return (int)$account;
}

function mailCenterEscape(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mailCenterReady(PDO $pdo): bool
{
    try {
        return (bool)$pdo->query("SELECT 1 FROM core_mail_schema_versions WHERE version = '001_foundation'")->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

function mailCenterActionError(Throwable $error): never
{
    if ($error instanceof OutOfBoundsException) {
        http_response_code(404);
        exit('Registro não encontrado.');
    }
    if ($error instanceof InvalidArgumentException || $error instanceof DomainException) {
        flash('error', $error->getMessage());
    } else {
        flash('error', 'Não foi possível salvar. Tente novamente.');
    }
    redirect('/web/admin/central_contas/accounts.php');
    exit;
}

function mailCenterUnifiedFolders(): array
{
    return ['inbox'=>'Caixa de entrada','sent'=>'Enviados','drafts'=>'Rascunhos','spam'=>'Spam','trash'=>'Lixeira'];
}
function mailCenterAccountColor(mixed $color): string
{
    return is_string($color)&&preg_match('/^#[0-9a-fA-F]{6}$/D',$color)?strtolower($color):'#64748b';
}
function mailCenterOrganizationReady(PDO $pdo): bool
{
    try{return (bool)$pdo->query("SELECT 1 FROM core_mail_schema_versions WHERE version='003_organization'")->fetchColumn();}catch(Throwable $e){return false;}
}
function mailCenterMarkFilters(): array{return ['favorite'=>'Favoritos','flagged'=>'Sinalizados','pinned'=>'Fixados'];}
function mailCenterMarkColumn(string $mark): ?string
{
    return match($mark){'all'=>null,'favorite'=>'is_favorite','flagged'=>'is_flagged','pinned'=>'is_pinned',default=>throw new InvalidArgumentException('Filtro de marcação inválido.')};
}
function mailCenterFolderKind(string $provider,string $remote,string $name): ?string
{
    if($provider==='gmail')return match($remote){'INBOX'=>'inbox','SENT'=>'sent','DRAFT'=>'drafts','SPAM'=>'spam','TRASH'=>'trash',default=>null};
    return match(mb_strtolower(trim($name),'UTF-8')){'inbox','entrada','caixa de entrada'=>'inbox','sent','sentitems','sent items','enviados','itens enviados'=>'sent','drafts','rascunhos'=>'drafts','spam','junk','junkemail','junk email','lixo eletrônico','lixo eletronico'=>'spam','trash','deleteditems','deleted items','lixeira','excluídos','excluidos','itens excluídos','itens excluidos'=>'trash',default=>null};
}