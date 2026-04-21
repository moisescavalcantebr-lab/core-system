<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

/*
|--------------------------------------------------------------------------
| SERVICE
|--------------------------------------------------------------------------
*/

$service = new MediaService($pdo);
$images = $service->all();

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$title = 'Media';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">

        <div>
            <h1 class="c-page-title">Biblioteca de Imagens</h1>
            <p class="c-page-subtitle">Gerencie os arquivos enviados</p>
        </div>

    </div>

    <div class="c-page-content">

        <!-- UPLOAD -->
        <div class="c-card">

            <form action="/app/actions/media/upload.php"
                  method="POST"
                  enctype="multipart/form-data">

                <?= csrf_field(); ?>

                <div class="c-form-group">
                    <input type="file" name="image" class="c-input" required>
                </div>

                <button class="c-btn-secondary">
                    Enviar
                </button>

            </form>

        </div>

        <!-- GRID -->
        <?php if(empty($images)): ?>

            <div class="c-card">
                Nenhuma imagem encontrada.
            </div>

        <?php else: ?>

            <div class="c-media-grid">

                <?php foreach ($images as $img): ?>

                    <div class="c-media-card">

                        <img src="/storage/media/<?= htmlspecialchars($img['file_name']) ?>" alt="">

                        <div class="c-media-actions">

                            <button 
                                class="c-btn-secondary"
                                onclick="deleteImage(<?= (int)$img['id'] ?>)">
                                Excluir
                            </button>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</div>

<script>
function deleteImage(id){

    if(!confirm('Excluir esta imagem?')) return;

    fetch('/app/actions/media/delete.php?id=' + id)
    .then(res => res.text())
    .then(() => location.reload())
    .catch(() => alert('Erro ao excluir'));

}
</script>

<?php
$content = ob_get_clean();

/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

$rightSidebarEnabled = true;

$rightSidebarContent = '

<div class="c-card">

<h3>Informações</h3>

<p>
Armazene e gerencie imagens usadas no sistema.
</p>

</div>

';

require APP_PATH . '/views/layout_admin.php';