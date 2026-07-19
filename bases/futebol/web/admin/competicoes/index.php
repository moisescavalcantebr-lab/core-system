<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/plan_fallback.php';

requireProjectAdmin();

$title = 'Competicoes';

function competitionEnsureDefaultFriendly(PDO $pdo): void
{
    try {
        $stmt = $pdo->query("
            SELECT id
            FROM competitions
            WHERE context = 'external'
              AND type = 'friendly'
              AND LOWER(name) = LOWER('Amistoso')
            LIMIT 1
        ");

        if ($stmt && $stmt->fetchColumn()) {
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO competitions (name, context, type, status, notes)
            VALUES (?, 'external', 'friendly', 'active', ?)
        ");
        $stmt->execute([
            'Amistoso',
            'Competicao simples para partidas amistosas do plano gratis.',
        ]);
    } catch (Throwable $e) {
        // A tela continua abrindo mesmo antes do schema ser sincronizado.
    }
}

competitionEnsureDefaultFriendly($pdo);

function competitionEnsureDefaultTraining(PDO $pdo): void
{
    try {
        $stmt = $pdo->query("
            SELECT id
            FROM competitions
            WHERE context = 'internal'
              AND type = 'training'
              AND LOWER(name) = LOWER('Treino')
            LIMIT 1
        ");

        if ($stmt && $stmt->fetchColumn()) {
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO competitions (name, context, type, status, notes)
            VALUES (?, 'internal', 'training', 'active', ?)
        ");
        $stmt->execute([
            'Treino',
            'Competicao interna para dividir o elenco em dois times de treino.',
        ]);
    } catch (Throwable $e) {
        // A tela continua abrindo mesmo antes do schema ser sincronizado.
    }
}

function competitionProjectPlanIsStart(PDO $pdo): bool
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
            // Projetos sem acesso ao Core seguem pelo fallback visual abaixo.
        }
    }

    return false;
}

function competitionEnsureOpenChampionship(PDO $pdo): ?array
{
    try {
        $stmt = $pdo->query("
            SELECT *
            FROM competitions
            WHERE context = 'external'
              AND type = 'championship'
              AND status IN ('draft', 'active')
            ORDER BY id DESC
            LIMIT 1
        ");

        return $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: null) : null;
    } catch (Throwable $e) {
        return null;
    }
}

function competitionTrainingModuleInstalled(): bool
{
    if (function_exists('projectModuleProvides') && projectModuleProvides('internal_competitions')) {
        return true;
    }

    if (!defined('PROJECT_PATH')) {
        return false;
    }

    return is_file(PROJECT_PATH . '/web/admin/treino/index.php')
        || is_file(PROJECT_PATH . '/modules/treino/web/admin/treino/index.php')
        || is_dir(PROJECT_PATH . '/web/admin/treino')
        || is_dir(PROJECT_PATH . '/modules/treino');
}

function competitionRelatedCount(PDO $pdo, int $competitionId): int
{
    $total = 0;

    foreach (['competition_participants', 'statistic_records', 'matches'] as $table) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE competition_id = ?");
            $stmt->execute([$competitionId]);
            $total += (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            continue;
        }
    }

    return $total;
}

$search = trim((string)($_GET['q'] ?? ''));
$contextFilter = in_array($_GET['context'] ?? '', ['external', 'internal'], true) ? $_GET['context'] : '';
$typeFilter = in_array($_GET['type'] ?? '', ['championship','cup','league','tournament','friendly','training','challenge','ranking','other'], true) ? $_GET['type'] : '';
$yearFilter = preg_match('/^\d{4}$/', (string)($_GET['year'] ?? '')) ? (string)$_GET['year'] : '';
$internalCompetitionsEnabled = function_exists('projectModuleProvides')
    && projectModuleProvides('internal_competitions');
$trainingModuleInstalled = competitionTrainingModuleInstalled();
$startFeaturesEnabled = competitionProjectPlanIsStart($pdo);

if (($internalCompetitionsEnabled || $trainingModuleInstalled) && $startFeaturesEnabled) {
    competitionEnsureDefaultTraining($pdo);
}

if (!$internalCompetitionsEnabled && $contextFilter === 'internal') {
    $contextFilter = '';
    $typeFilter = '';
}

if ($contextFilter === 'external' && $typeFilter === 'training') {
    $typeFilter = '';
}

if ($contextFilter === 'internal' && $typeFilter !== '' && $typeFilter !== 'training') {
    $typeFilter = '';
}

$where = [];
$params = [];

$where[] = "c.status NOT IN ('finished', 'canceled')";

if ($search !== '') {
    $where[] = "c.name LIKE ?";
    $params[] = '%' . $search . '%';
}

if ($contextFilter !== '') {
    $where[] = "c.context = ?";
    $params[] = $contextFilter;
}

if ($typeFilter !== '') {
    $where[] = "c.type = ?";
    $params[] = $typeFilter;
}

if ($yearFilter !== '') {
    $where[] = "(YEAR(c.starts_at) = ? OR c.season = ?)";
    $params[] = (int)$yearFilter;
    $params[] = $yearFilter;
}

$sql = "
    SELECT c.*
    FROM competitions c
    WHERE " . implode(' AND ', $where) . "
    ORDER BY c.id DESC
    LIMIT 50
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$competitions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$typeLabels = [
    'championship' => 'Campeonato',
    'cup' => 'Copa',
    'league' => 'Liga',
    'tournament' => 'Torneio',
    'friendly' => 'Amistoso',
    'challenge' => 'Desafio',
    'ranking' => 'Ranking',
    'other' => 'Outro',
    'training' => 'Treino',
];

$statusLabels = [
    'active' => 'Ativa',
    'draft' => 'Rascunho',
    'finished' => 'Finalizada',
    'canceled' => 'Cancelada',
];

function competitionStatusBadge(?string $status): string
{
    return match ($status) {
        'active' => 'c-badge--success',
        'draft' => 'c-badge--warning',
        'finished' => 'c-badge--neutral',
        'canceled' => 'c-badge--danger',
        default => 'c-badge--neutral',
    };
}

$openCompetitionLimit = projectPlanLimit('competitions_open', 3);
$openCompetitionCount = 0;
try {
    $openCompetitionCount = (int)$pdo->query("SELECT COUNT(*) FROM competitions WHERE status IN ('draft', 'active')")->fetchColumn();
} catch (Throwable $e) {
    $openCompetitionCount = 0;
}
$canCreateCompetition = $openCompetitionCount < $openCompetitionLimit;
$friendlyCompetition = null;
$trainingCompetition = null;
$championshipCompetition = competitionEnsureOpenChampionship($pdo);

foreach ($competitions as $competition) {
    if (($competition['type'] ?? '') === 'friendly' && !$friendlyCompetition) {
        $friendlyCompetition = $competition;
    } elseif (($competition['type'] ?? '') === 'training' && !$trainingCompetition) {
        $trainingCompetition = $competition;
    } elseif (($competition['type'] ?? '') === 'championship' && !$championshipCompetition) {
        $championshipCompetition = $competition;
    }
}

$friendlyStats = ['total' => 0, 'finished' => 0, 'scheduled' => 0];
if ($friendlyCompetition) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(status = 'finished') AS finished,
                SUM(status = 'scheduled') AS scheduled
            FROM matches
            WHERE competition_id = ?
        ");
        $stmt->execute([(int)$friendlyCompetition['id']]);
        $friendlyStats = array_map('intval', $stmt->fetch(PDO::FETCH_ASSOC) ?: $friendlyStats);
    } catch (Throwable $e) {
        $friendlyStats = ['total' => 0, 'finished' => 0, 'scheduled' => 0];
    }
}

$trainingStats = ['total' => 0, 'finished' => 0, 'scheduled' => 0];
if ($trainingCompetition) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(status = 'finished') AS finished,
                SUM(status = 'scheduled') AS scheduled
            FROM matches
            WHERE competition_id = ?
        ");
        $stmt->execute([(int)$trainingCompetition['id']]);
        $trainingStats = array_map('intval', $stmt->fetch(PDO::FETCH_ASSOC) ?: $trainingStats);
    } catch (Throwable $e) {
        $trainingStats = ['total' => 0, 'finished' => 0, 'scheduled' => 0];
    }
}

$championshipStats = ['total' => 0, 'finished' => 0, 'scheduled' => 0];
if ($championshipCompetition) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(status = 'finished') AS finished,
                SUM(status = 'scheduled') AS scheduled
            FROM matches
            WHERE competition_id = ?
        ");
        $stmt->execute([(int)$championshipCompetition['id']]);
        $championshipStats = array_map('intval', $stmt->fetch(PDO::FETCH_ASSOC) ?: $championshipStats);
    } catch (Throwable $e) {
        $championshipStats = ['total' => 0, 'finished' => 0, 'scheduled' => 0];
    }
}

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Competicoes</h1>
            <p class="c-page-subtitle">Partidas simples do Meu Time. Campeonatos e treinos entram no Start.</p>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-competition-cards">
            <div class="c-competition-card c-competition-card--active">
                <div class="c-competition-card-visual c-competition-card-visual--friendly" aria-hidden="true"></div>
                <div class="c-competition-card-body">
                    <div>
                        <div class="c-competition-card-head">
                            <span class="c-badge c-badge--success">Gratis</span>
                            <span class="c-event-label">Evento padrão</span>
                        </div>
                        <h3>Amistoso</h3>
                        <p>Centraliza partidas rápidas, placar simples e histórico básico do time.</p>
                    </div>

                    <div class="c-event-stats">
                        <span><strong><?= $friendlyStats['total'] ?></strong> partidas</span>
                        <span><strong><?= $friendlyStats['scheduled'] ?></strong> agendadas</span>
                        <span><strong><?= $friendlyStats['finished'] ?></strong> finalizadas</span>
                    </div>

                    <div class="c-competition-card-actions">
                        <?php if ($friendlyCompetition): ?>
                            <a class="c-btn-event" href="<?= PROJECT_URL ?>/admin/competicoes/view.php?id=<?= (int)$friendlyCompetition['id'] ?>">Entrar no Amistoso</a>
                        <?php else: ?>
                            <span class="c-muted">Sincronize o schema para criar o Amistoso padrão.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="c-competition-card <?= $startFeaturesEnabled && $trainingCompetition ? 'c-competition-card--start-active' : 'c-competition-card--locked' ?>">
                <div class="c-competition-card-visual c-competition-card-visual--training" aria-hidden="true"></div>
                <div class="c-competition-card-body">
                    <div>
                        <div class="c-competition-card-head">
                            <span class="c-badge c-badge--info">Start</span>
                            <span class="c-event-label"><?= $startFeaturesEnabled && $trainingCompetition ? 'Evento interno' : 'Em preparo' ?></span>
                        </div>
                        <h3>Treino</h3>
                        <p>Reservado para escalações internas e treinos com dois times do próprio elenco.</p>
                    </div>

                    <?php if ($startFeaturesEnabled && $trainingCompetition): ?>
                        <div class="c-event-stats">
                            <span><strong><?= $trainingStats['total'] ?></strong> treinos</span>
                            <span><strong><?= $trainingStats['scheduled'] ?></strong> agendados</span>
                            <span><strong><?= $trainingStats['finished'] ?></strong> finalizados</span>
                        </div>
                    <?php endif; ?>

                    <div class="c-competition-card-actions">
                        <?php if ($startFeaturesEnabled && $trainingCompetition): ?>
                            <a class="c-btn-event c-btn-event--start" href="<?= PROJECT_URL ?>/admin/competicoes/view.php?id=<?= (int)$trainingCompetition['id'] ?>">Entrar no Treino</a>
                        <?php else: ?>
                            <span class="c-muted">Preparado para o Start</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="c-competition-card <?= $startFeaturesEnabled ? 'c-competition-card--start-active' : 'c-competition-card--locked' ?>">
                <div class="c-competition-card-visual c-competition-card-visual--championship" aria-hidden="true"></div>
                <div class="c-competition-card-body">
                    <div>
                        <div class="c-competition-card-head">
                            <span class="c-badge c-badge--info">Start</span>
                            <span class="c-event-label"><?= $championshipCompetition ? 'Campeonato ativo' : ($startFeaturesEnabled ? 'Disponível' : 'Em preparo') ?></span>
                        </div>
                        <h3>Campeonato</h3>
                        <p>Um campeonato aberto por vez, com partidas, agenda e resumo próprio.</p>
                    </div>

                    <?php if ($championshipCompetition): ?>
                        <div class="c-event-stats">
                            <span><strong><?= $championshipStats['total'] ?></strong> partidas</span>
                            <span><strong><?= $championshipStats['scheduled'] ?></strong> agendadas</span>
                            <span><strong><?= $championshipStats['finished'] ?></strong> finalizadas</span>
                        </div>
                    <?php endif; ?>

                    <div class="c-competition-card-actions">
                        <?php if ($championshipCompetition): ?>
                            <a class="c-btn-event c-btn-event--championship" href="<?= PROJECT_URL ?>/admin/competicoes/view.php?id=<?= (int)$championshipCompetition['id'] ?>">
                                Entrar no Campeonato
                            </a>
                        <?php elseif ($startFeaturesEnabled && $canCreateCompetition): ?>
                            <a class="c-btn-event c-btn-event--championship" href="<?= PROJECT_URL ?>/admin/competicoes/create.php?type=championship">
                                Criar Campeonato
                            </a>
                        <?php else: ?>
                            <span class="c-muted">Preparado para o Start</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.c-competition-cards {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.c-competition-card {
    min-height: 260px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid var(--border-color, rgba(255,255,255,.15));
    background: rgba(255,255,255,.025);
}

.c-competition-card--active {
    border-left: 3px solid #22c55e;
}

.c-competition-card--locked {
    border-left: 3px solid #3b82f6;
    opacity: .82;
}

.c-competition-card--start-active {
    border-left: 3px solid #38bdf8;
}

.c-competition-card-visual {
    min-height: 88px;
    border-bottom: 1px solid rgba(255,255,255,.09);
    background:
        linear-gradient(135deg, rgba(255,255,255,.08), rgba(255,255,255,0)),
        radial-gradient(circle at 18% 35%, rgba(34,197,94,.28), transparent 28%),
        radial-gradient(circle at 78% 22%, rgba(59,130,246,.2), transparent 24%),
        rgba(2,10,24,.36);
}

.c-competition-card-visual--friendly {
    background:
        linear-gradient(90deg, rgba(34,197,94,.16) 1px, transparent 1px),
        linear-gradient(180deg, rgba(255,255,255,.08) 1px, transparent 1px),
        radial-gradient(circle at 50% 50%, rgba(34,197,94,.32), transparent 34%),
        #11321f;
    background-size: 42px 42px, 42px 42px, auto, auto;
}

.c-competition-card-visual--training {
    background:
        radial-gradient(circle at 30% 45%, rgba(59,130,246,.28), transparent 24%),
        radial-gradient(circle at 68% 55%, rgba(59,130,246,.18), transparent 22%),
        rgba(15,23,42,.72);
}

.c-competition-card-visual--championship {
    background:
        radial-gradient(circle at 50% 38%, rgba(245,158,11,.22), transparent 26%),
        linear-gradient(135deg, rgba(245,158,11,.12), rgba(59,130,246,.08)),
        rgba(15,23,42,.72);
}

.c-competition-card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 14px;
    padding: 16px;
}

.c-competition-card-head {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.c-event-label {
    color: var(--muted-color, rgba(255,255,255,.58));
    font-size: 11px;
}

.c-competition-card h3 {
    margin: 10px 0 6px;
}

.c-competition-card p {
    margin: 0;
    color: var(--muted-color, rgba(255,255,255,.68));
}

.c-competition-card-actions {
    min-height: 32px;
    display: flex;
    align-items: flex-end;
}

.c-event-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 6px;
    padding-top: 2px;
}

.c-event-stats span {
    display: grid;
    gap: 2px;
    color: var(--muted-color, rgba(255,255,255,.62));
    font-size: 11px;
}

.c-event-stats strong {
    color: var(--text-color, #fff);
    font-size: 17px;
    line-height: 1;
}

.c-btn-event {
    width: 100%;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    min-height: 36px;
    padding: 8px 12px;
    border-radius: 6px;
    background: #2563eb;
    color: #fff;
    font-weight: 800;
    text-decoration: none;
}

.c-btn-event:hover {
    background: #3b82f6;
}

.c-btn-event--start {
    background: #0e7490;
}

.c-btn-event--start:hover {
    background: #0891b2;
}

.c-btn-event--championship {
    background: #b45309;
}

.c-btn-event--championship:hover {
    background: #d97706;
}

.c-muted {
    color: var(--muted-color, rgba(255,255,255,.58));
    font-size: 12px;
}

.c-competition-filter-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    align-items: end;
}

.c-competition-filter-actions {
    margin-top: 12px;
}

@media (max-width: 900px) {
    .c-competition-cards {
        grid-template-columns: 1fr;
    }

    .c-competition-filter-row {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
