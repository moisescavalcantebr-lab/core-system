<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);
financeEnsureEntryMetaSchema($pdo);

if (!financeUsesParticipants($pdo)) {
    flash('info', 'Solicitacoes de saldo ficam disponiveis apenas no modo participantes.');
    redirect(PROJECT_URL . '/admin/financeiro/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$currentUser = projectUser();
$id = (int)($_GET['id'] ?? 0);
$action = (string)($_POST['action'] ?? '');

if ($id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    flash('error', 'Solicitação inválida.');
    redirect(PROJECT_URL . '/admin/financeiro/wallet_requests.php');
}

$stmt = $pdo->prepare("
    SELECT r.*, u.name AS user_name
    FROM finance_wallet_requests r
    LEFT JOIN project_users u ON u.id = r.user_id
    WHERE r.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request || ($request['status'] ?? '') !== 'pending') {
    flash('error', 'Solicitação não encontrada ou já revisada.');
    redirect(PROJECT_URL . '/admin/financeiro/wallet_requests.php');
}
$teamWalletParty=null;
if(financeTeamEnabled()&&$action==='approve'){
    $identity=$pdo->prepare('SELECT id FROM players WHERE user_id=? ORDER BY id LIMIT 2');$identity->execute([(int)$request['user_id']]);$matches=$identity->fetchAll(PDO::FETCH_COLUMN);
    if(count($matches)!==1){http_response_code(422);exit('Vincule o solicitante a um unico jogador antes de aprovar o recebimento.');}
    $teamWalletParty=financeTeamParty($pdo,['team_party_kind'=>'player','team_player_id'=>$matches[0]]);
    try{financeTeamCreditRecord($pdo,['kind'=>'deposit','credit_player_id'=>$matches[0],'team_party_kind'=>'player','team_player_id'=>$matches[0],'amount'=>(string)$request['amount'],'paid_at'=>(string)($_POST['paid_at']??''),'account_id'=>(int)($_POST['account_id']??0),'payment_method'=>'pix','request_key'=>hash('sha256','wallet-request:'.$id)],(int)$currentUser['id'],$request);}catch(Throwable $e){http_response_code(422);exit($e instanceof DomainException?$e->getMessage():'Nao foi possivel aprovar o credito.');}
    redirect(PROJECT_URL.'/admin/financeiro/wallet_requests.php');exit;
}

$pdo->beginTransaction();

try {
    if(financeTeamEnabled()){
        $lock=$pdo->prepare('SELECT status FROM finance_wallet_requests WHERE id=? FOR UPDATE');$lock->execute([$id]);
        if($lock->fetchColumn()!=='pending'){$pdo->rollBack();redirect(PROJECT_URL.'/admin/financeiro/wallet_requests.php');exit;}
    }
    if ($action === 'reject') {
        $stmt = $pdo->prepare("
            UPDATE finance_wallet_requests
            SET status = 'rejected', reviewed_by_user_id = ?, reviewed_at = NOW(), notes = ?
            WHERE id = ?
        ");
        $stmt->execute([(int)$currentUser['id'], 'Solicitação rejeitada pelo administrador.', $id]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE finance_wallet_requests
            SET status = 'approved', reviewed_by_user_id = ?, reviewed_at = NOW(), notes = ?
            WHERE id = ?
        ");
        $stmt->execute([(int)$currentUser['id'], 'Saldo aprovado pelo administrador.', $id]);

        $entry = $pdo->prepare("
            INSERT INTO finance_entries (
                category_id, type, title, description, amount,
                party_type, party_module, party_id, party_name,
                due_date, paid_at, status, source, payment_method, receipt_path,
                created_by_user_id, updated_by_user_id
            )
            VALUES (NULL, 'income', ?, ?, ?, 'other', NULL, NULL, NULL, NULL, CURDATE(), 'paid', 'balance_deposit', 'pix', ?, ?, ?)
        ");
        $entry->execute([
            'Adicao de saldo',
            'Saldo para pagamentos aprovado a partir de comprovante enviado.',
            (float)$request['amount'],
            $request['receipt_path'] ?? null,
            (int)$currentUser['id'],
            (int)$currentUser['id'],
        ]);
        if($teamWalletParty){
            $teamWalletEntryId=(int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE finance_entries SET party_type=?,party_module=?,party_id=?,party_name=? WHERE id=?')->execute([$teamWalletParty['party_type'],$teamWalletParty['party_module'],$teamWalletParty['party_id'],$teamWalletParty['party_name'],$teamWalletEntryId]);
            financeTeamAudit($pdo,$teamWalletEntryId,'created',(int)$currentUser['id']);
        }
    }

    $pdo->commit();
    flash('success', $action === 'approve' ? 'Saldo aprovado.' : 'Solicitação rejeitada.');
} catch (Throwable $e) {
    $pdo->rollBack();
    flash('error', 'Erro ao revisar solicitação: ' . $e->getMessage());
}

redirect(PROJECT_URL . '/admin/financeiro/wallet_requests.php');
