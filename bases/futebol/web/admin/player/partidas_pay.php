<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/../partidas/lineup_helpers.php';
require __DIR__ . '/../financeiro/helpers.php';

requireProjectAuth();
matchLineupEnsureSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$matchId = (int)($_GET['id'] ?? 0);
$user = projectUser();

$stmt = $pdo->prepare("SELECT * FROM players WHERE user_id = ? AND status = 'active' LIMIT 1");
$stmt->execute([(int)$user['id']]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if ($matchId <= 0 || !$player) {
    flash('error', 'Dados invalidos.');
    redirect(PROJECT_URL . '/admin/player/partidas.php');
}

$stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ? AND status IN ('scheduled','live') LIMIT 1");
$stmt->execute([$matchId]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    flash('error', 'Partida indisponivel para pagamento.');
    redirect(PROJECT_URL . '/admin/player/partidas.php');
}

$fee = (float)($match['match_fee'] ?? 0);

if ($fee <= 0) {
    flash('success', 'Esta partida e gratis.');
    redirect(PROJECT_URL . '/admin/player/partidas.php');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM match_confirmations
    WHERE match_id = ? AND player_id = ?
    LIMIT 1
");
$stmt->execute([$matchId, (int)$player['id']]);
$confirmation = $stmt->fetch(PDO::FETCH_ASSOC);

if ($confirmation && ($confirmation['payment_status'] ?? '') === 'paid') {
    flash('success', 'Pagamento ja registrado. Voce ja pode confirmar presenca.');
    redirect(PROJECT_URL . '/admin/player/partidas.php');
}

$balance = financePlayerBalance($pdo, (int)$player['id']);

if ($balance + 0.0001 < $fee) {
    flash('error', 'Saldo insuficiente para pagar esta partida.');
    redirect(PROJECT_URL . '/admin/player/partidas.php');
}

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("
        INSERT INTO finance_entries (
            category_id, type, title, description, amount, party_type, party_module,
            party_id, party_name, paid_at, status, source, created_by_user_id, updated_by_user_id
        )
        VALUES (NULL, 'expense', ?, ?, ?, 'player', 'partidas', ?, ?, CURDATE(), 'paid', 'wallet_payment', ?, ?)
    ");
    $stmt->execute([
        'Pagamento de partida',
        trim((string)$match['participant_a'] . ' x ' . (string)($match['participant_b'] ?? '')),
        $fee,
        (int)$player['id'],
        (string)$player['name'],
        (int)$user['id'],
        (int)$user['id'],
    ]);

    $financeEntryId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO match_confirmations (match_id, player_id, status, payment_status, payment_amount, finance_entry_id, paid_at)
        VALUES (?, ?, 'pending', 'paid', ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            payment_status = 'paid',
            payment_amount = VALUES(payment_amount),
            finance_entry_id = VALUES(finance_entry_id),
            paid_at = NOW()
    ");
    $stmt->execute([$matchId, (int)$player['id'], $fee, $financeEntryId]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    flash('error', 'Nao foi possivel registrar o pagamento.');
    redirect(PROJECT_URL . '/admin/player/partidas.php');
}

flash('success', 'Pagamento realizado. Agora confirme sua presenca.');
redirect(PROJECT_URL . '/admin/player/partidas.php');
