<?php
require __DIR__ . '/../../bootstrap/bootstrap.php';

$authService = new AuthService($pdo);
$authService->logout();

redirect('/public/admin/login.php');