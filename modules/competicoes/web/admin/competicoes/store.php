<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/plan_fallback.php';

requireProjectAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

function competitionStoreProjectPlanIsStart(PDO $pdo): bool
{
    if (function_exists('projectPlanName')) {
        $planName = strtolower(projectPlanName());
        if (!str_contains($planName, 'gratis')) {
            return true;
        }
    }

    if (defined('PROJECT_PATH')) {
        $projectSlug = basename((string)PROJECT_PATH);
        try {
            $stmt = $pdo->prepare("
                SELECT pl.name
                FROM core.projects pr
                INNER JOIN core.plans pl ON pl.id = pr.plan_id
                WHERE pr.slug = ?
                LIMIT 1
            ");
            $stmt->execute([$projectSlug]);
            $planName = strtolower((string)$stmt->fetchColumn());

            if ($planName !== '' && !str_contains($planName, 'gratis')) {
                return true;
            }
        } catch (Throwable $e) {
            // Projetos sem Core seguem as regras do helper do plano.
        }
    }

    return false;
}

$name = trim((string)($_POST['name'] ?? ''));
$context = in_array($_POST['context'] ?? '', ['external', 'internal'], true) ? $_POST['context'] : 'external';
$type = in_array($_POST['type'] ?? '', ['championship','cup','league','tournament','friendly','training','challenge','ranking','other'], true) ? $_POST['type'] : 'tournament';
$status = in_array($_POST['status'] ?? '', ['draft','active','finished','canceled'], true) ? $_POST['status'] : 'active';
$startsAt = trim((string)($_POST['starts_at'] ?? '')) ?: null;
$endsAt = trim((string)($_POST['ends_at'] ?? '')) ?: null;
$notes = trim((string)($_POST['notes'] ?? '')) ?: null;

if ($name === '') {
    flash('error', 'Nome obrigatorio.');
    redirect(PROJECT_URL . '/admin/competicoes/index.php');
}

if ($context === 'internal') {
    if (!function_exists('projectModuleProvides') || !projectModuleProvides('internal_competitions')) {
        flash('error', 'Treino interno precisa do modulo Treinos Internos.');
        redirect(PROJECT_URL . '/admin/competicoes/index.php');
    }

    $type = 'training';
} elseif ($type === 'training') {
    $type = 'tournament';
}

if ($context === 'external') {
    $allowedTypes = projectPlanList('external_competition_types', ['friendly','championship','cup','league','tournament']);

    if (!in_array($type, $allowedTypes, true)) {
        flash('error', projectPlanName() . ' permite apenas os tipos de competicao liberados no plano.');
        redirect(PROJECT_URL . '/admin/competicoes/index.php');
    }
}

if ($context === 'external' && $type === 'championship') {
    if (!competitionStoreProjectPlanIsStart($pdo)) {
        flash('error', 'Campeonato esta disponivel a partir do Plano Start.');
        redirect(PROJECT_URL . '/admin/competicoes/index.php');
    }

    $status = 'active';
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM competitions
        WHERE context = 'external'
          AND type = 'championship'
          AND status IN ('draft', 'active')
    ");
    $stmt->execute();

    if ((int)$stmt->fetchColumn() > 0) {
        flash('error', 'Ja existe um campeonato aberto. Finalize ou cancele o campeonato atual antes de criar outro.');
        redirect(PROJECT_URL . '/admin/competicoes/index.php');
    }
}

if (in_array($status, ['draft', 'active'], true)) {
    $openLimit = projectPlanLimit('competitions_open', 3);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM competitions WHERE status IN ('draft', 'active')");
    $stmt->execute();

    if ((int)$stmt->fetchColumn() >= $openLimit) {
        flash('error', 'Limite de ' . $openLimit . ' competicao aberta atingido neste plano. Finalize ou cancele uma competicao para criar outra.');
        redirect(PROJECT_URL . '/admin/competicoes/index.php');
    }
}

$stmt = $pdo->prepare("
    INSERT INTO competitions (name, context, type, season, status, starts_at, ends_at, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $name,
    $context,
    $type,
    trim((string)($_POST['season'] ?? '')) ?: null,
    $status,
    $startsAt,
    $endsAt,
    $notes,
]);

flash('success', 'Competicao criada com sucesso.');
redirect(PROJECT_URL . '/admin/competicoes/index.php');
