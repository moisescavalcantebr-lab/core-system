<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Partida invalida.');
    redirect(PROJECT_URL . '/admin/partidas/index.php');
}

$stmt = $pdo->prepare("SELECT id, competition_id, status FROM matches WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    flash('error', 'Partida nao encontrada.');
    redirect(PROJECT_URL . '/admin/partidas/index.php');
}

$redirectUrl = !empty($match['competition_id'])
    ? PROJECT_URL . '/admin/competicoes/view.php?id=' . (int)$match['competition_id']
    : PROJECT_URL . '/admin/partidas/index.php';

if (($match['status'] ?? '') !== 'scheduled') {
    flash('error', 'Apenas partidas agendadas podem ser iniciadas.');
    redirect($redirectUrl);
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE status = 'live' AND id <> ?");
$stmt->execute([$id]);
if ((int)$stmt->fetchColumn() > 0) {
    flash('error', 'Ja existe uma partida em andamento. Finalize a partida aberta antes de iniciar outra.');
    redirect($redirectUrl);
}

$stmt = $pdo->prepare("UPDATE matches SET status = 'live' WHERE id = ?");
$stmt->execute([$id]);

flash('success', 'Partida iniciada.');
redirect(PROJECT_URL . '/admin/partidas/index.php');
