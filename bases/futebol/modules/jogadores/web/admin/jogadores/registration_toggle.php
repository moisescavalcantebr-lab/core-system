<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

require __DIR__ . '/positions_helper.php';

if (!playerAccessFeatureEnabled()) {
    setSetting('player_public_registration_enabled', '0', 'players');
    flash('warning', 'Cadastro público de jogador fica disponível a partir do Plano Start.');
    redirect(PROJECT_URL . '/admin/jogadores/index.php');
}

$enabled = ($_POST['enabled'] ?? '0') === '1' ? '1' : '0';
setSetting('player_public_registration_enabled', $enabled, 'players');

flash('success', $enabled === '1' ? 'Cadastro de jogador liberado.' : 'Cadastro de jogador bloqueado.');
redirect(PROJECT_URL . '/admin/jogadores/index.php');
