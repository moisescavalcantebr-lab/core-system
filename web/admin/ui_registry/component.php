<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';


$service = new UiRegistryService();

$component = $_GET['component'] ?? null;
$restore   = $_GET['restore'] ?? null;

if (!$component) {
    exit('Componente não informado');
}

if ($restore) {
    $service->restore($component, $restore);
    header('Location: ?component=' . urlencode($component));
    exit;
}

$data    = $service->getComponent($component);
$backups = $service->getBackups($component);

require APP_PATH . '/views/admin/ui_registry/component.php';