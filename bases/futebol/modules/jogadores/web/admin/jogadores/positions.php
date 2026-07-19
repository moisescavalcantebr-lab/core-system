<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/positions_helper.php';

requireProjectAdmin();
playerEnsureDefaultPositions($pdo);

$title = 'Posições';
$positions = $pdo->query("
    SELECT pp.*,
        COUNT(p.id) AS active_players,
        COALESCE(SUM(p.roster_status = 'reserva'), 0) AS reserve_players,
        COALESCE(SUM(p.roster_status = 'titular' OR p.roster_status IS NULL), 0) AS starter_players
    FROM player_positions pp
    LEFT JOIN players p ON p.position_id = pp.id AND p.status = 'active'
    WHERE pp.status = 'active'
    GROUP BY pp.id
    ORDER BY pp.sort_order ASC, pp.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$groupedPositions = [];
foreach ($positions as $position) {
    $groupedPositions[$position['group_label'] ?? 'Outras'][] = $position;
}

$preferredOrder = ['Goleiros', 'Zagueiros', 'Laterais', 'Meias', 'Pontas', 'Atacantes'];
$orderedGroups = [];

foreach ($preferredOrder as $label) {
    if (isset($groupedPositions[$label])) {
        $orderedGroups[$label] = $groupedPositions[$label];
    }
}

foreach ($groupedPositions as $label => $items) {
    if (!isset($orderedGroups[$label])) {
        $orderedGroups[$label] = $items;
    }
}

$groupedPositions = $orderedGroups;

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Posições</h1>
            <p class="c-page-subtitle">Estrutura padrão do elenco ativo</p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/jogadores/index.php" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">

        <div class="c-card">
            <?php if (empty($positions)): ?>
                <p>Nenhuma posição cadastrada.</p>
            <?php else: ?>
                <div class="c-position-map">
                    <?php foreach ($groupedPositions as $groupLabel => $groupPositions): ?>
                        <section class="c-position-column">
                            <h3><?= htmlspecialchars($groupLabel) ?></h3>

                            <?php foreach ($groupPositions as $position): ?>
                                <div class="c-position-item">
                                    <div>
                                        <strong><?= htmlspecialchars($position['code'] ?? '-') ?></strong>
                                        <span><?= htmlspecialchars($position['name']) ?></span>
                                    </div>
                                    <?php
                                        $activePlayers = (int)$position['active_players'];
                                        $reservePlayers = (int)$position['reserve_players'];
                                        $badgeClass = $activePlayers <= 0 ? 'c-badge--neutral' : ($reservePlayers > 0 ? 'c-badge--warning' : 'c-badge--success');
                                        $badgeText = $activePlayers <= 0 ? 'Livre' : ($reservePlayers > 0 ? 'Reserva' : 'Titular');
                                    ?>
                                    <span class="c-badge <?= $badgeClass ?>">
                                        <?= $badgeText ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<style>
.c-position-map {
    display: grid;
    grid-template-columns: repeat(6, minmax(120px, 1fr));
    gap: 8px;
    align-items: start;
}

.c-position-column {
    display: grid;
    gap: 6px;
}

.c-position-column h3 {
    margin: 0;
    padding: 6px 8px;
    border: 1px solid var(--border-color, rgba(255,255,255,.16));
    background: rgba(255,255,255,.04);
    font-size: 11px;
    text-align: center;
}

.c-position-item {
    display: grid;
    gap: 4px;
    min-height: 58px;
    padding: 7px;
    border: 1px solid var(--border-color, rgba(255,255,255,.16));
    background: rgba(255,255,255,.03);
}

.c-position-item strong,
.c-position-item span {
    display: block;
}

.c-position-item strong {
    margin-bottom: 1px;
    font-size: 12px;
}

.c-position-item span {
    font-size: 11px;
    line-height: 1.2;
}

.c-position-item .c-badge {
    min-height: 17px;
    padding: 2px 7px;
    font-size: 10px;
}

@media (max-width: 1100px) {
    .c-position-map {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .c-position-map {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .c-position-column {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        align-items: start;
    }

    .c-position-column h3 {
        grid-column: 1 / -1;
    }

    .c-position-item {
        min-height: 54px;
        padding: 6px;
    }
}

@media (max-width: 430px) {
    .c-position-map {
        gap: 7px;
    }

    .c-position-column {
        gap: 5px;
    }

    .c-position-column h3 {
        padding: 6px 4px;
        font-size: 10px;
    }

    .c-position-item strong {
        font-size: 11px;
    }

    .c-position-item span,
    .c-position-item .c-badge {
        font-size: 9px;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
