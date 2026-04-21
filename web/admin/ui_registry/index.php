<?php
require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$service = new UiRegistryService();
$components = $service->getAllGrouped();

/* =========================
CAPTURA VIEW
========================= */

ob_start();

require APP_PATH . '/views/admin/ui_registry/index.php';

$content = ob_get_clean();

/* =========================
LAYOUT
========================= */

require APP_PATH . '/views/layout_admin.php';