<?php

require '../../views/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$data = json_decode(file_get_contents('php://input'), true);

$service = new UiRegistryService();

echo $service->updateComponent($data);