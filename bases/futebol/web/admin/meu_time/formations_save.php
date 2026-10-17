<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
requireProjectAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}
csrf_verify();
require_once __DIR__ . '/formations_helpers.php';
if (isset($_POST['second_team']) && !teamSecondFormationAllowed()) {
    flash('error', 'Segundo time disponivel a partir do Plano Start.');
    redirect(PROJECT_URL . '/admin/meu_time/index.php');
}
teamEnsureFormationsSchema($pdo);
teamSetFormationsCount($pdo, isset($_POST['second_team']) ? 2 : 1);
flash('success', isset($_POST['second_team']) ? 'Segundo time ativado.' : 'Segundo time desativado. Jogadores retornaram aos disponiveis.');
redirect(PROJECT_URL . '/admin/meu_time/index.php');
