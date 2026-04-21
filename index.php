<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/app/bootstrap/bootstrap.php';

header('Location: /web/admin/login.php');
exit;
