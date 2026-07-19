<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$enabled = ($_POST['enabled'] ?? '0') === '1' ? '1' : '0';
setSetting('participant_public_registration_enabled', $enabled, 'participants');

flash('success', $enabled === '1' ? 'Cadastro publico liberado.' : 'Cadastro publico bloqueado.');
redirect(participantAdminUrl());
