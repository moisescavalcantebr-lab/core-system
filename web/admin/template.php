<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

/*
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
*/

$title = 'Template';

/*
|--------------------------------------------------------------------------
| CONTENT
|--------------------------------------------------------------------------
*/

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Dashboard</h1>
            <p class="c-page-subtitle">Visão geral do sistema</p>
        </div>

        <div class="c-page-actions">
            <a href="#" class="c-btn-secondary">Ação</a>
        </div>
    </div>

    <div class="c-page-content">
        <!-- conteúdo -->
    </div>

</div>

<?php
$content = ob_get_clean();

require APP_PATH . '/views/layout_admin.php';