<?php

require '../../bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$path = $_GET['path'] ?? null;

$service = new UiRegistryService();

$service->deleteComponent($path);

header('Location: /admin/ui_registry/index.php');
exit;