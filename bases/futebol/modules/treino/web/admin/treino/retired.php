<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
requireProjectAdmin();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') csrf_verify();
redirect(PROJECT_URL . '/admin/meu_time/index.php');
