<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/../../app/bootstrap/project_bootstrap.php';

requireProjectAuth();

$title = 'Dashboard';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Dashboard</h1>
            <p class="c-page-subtitle">Teste funcionando</p>
        </div>
    </div>

    <div class="c-page-content">

        <div class="c-card">
            <h3>Teste</h3>
            <p>Se você está vendo isso, o layout está OK.</p>
        </div>

    </div>

</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
