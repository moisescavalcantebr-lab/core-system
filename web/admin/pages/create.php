<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

global $pdo;

/* =========================
BASE URL
========================= */

$baseUrl = '';

if (defined('PROJECT_PATH')) {
    $baseUrl = '/projects/' . basename(PROJECT_PATH);
}

/* =========================
MODELOS
========================= */

$models = $pdo->query("
SELECT slug,title
FROM core_page_contents
WHERE type='model'
AND area='public'
ORDER BY title
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
CATEGORIAS
========================= */

$categories = $pdo->query("
SELECT DISTINCT category 
FROM core_page_contents
WHERE category IS NOT NULL 
AND category != ''
ORDER BY category
")->fetchAll(PDO::FETCH_COLUMN);

ob_start();
?>

<div class="c-page">

    <!-- HEADER -->
    <div class="c-page-header">

        <div>
            <h1 class="c-page-title">Nova Página</h1>
            <p class="c-page-subtitle">Criação de conteúdo</p>
        </div>

        <div class="c-page-actions">
            <a class="c-btn-secondary"
               href="<?= $baseUrl ?>/public/admin/pages/index.php">
                ← Voltar
            </a>
        </div>

    </div>

    <!-- CONTENT -->
    <div class="c-page-content">

        <div class="c-grid c-grid-3">

            <!-- FORM -->
            <div class="c-card">

                <form method="post" action="/app/actions/pages/store.php">

                    <div class="c-form-group">
                        <label>Título</label>
                        <input name="title" class="c-input" required>
                    </div>

                    <div class="c-form-group">
                        <label>Slug</label>
                        <input id="slugInput" name="slug" class="c-input" required>
                    </div>

                    <div class="c-form-group">
                        <label>Modelo</label>
                        <select name="model_slug" class="c-input" required>
                            <option value="">Selecione</option>

                            <?php foreach ($models as $m): ?>
                                <option value="<?= htmlspecialchars($m['slug']) ?>">
                                    <?= htmlspecialchars($m['title']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="c-form-group">
                        <label>Categoria</label>
                        <input name="category" class="c-input">
                    </div>

                    <div class="c-form-group">
                        <label>Subcategoria</label>
                        <input name="sub_category" class="c-input">
                    </div>

                    <br>

                    <button class="c-btn-primary w-100">
                        Criar Página
                    </button>

                </form>

            </div>

            <!-- AJUDA -->
            <div class="c-card">

                <h3>Dicas</h3>

                <p class="c-text-muted" style="font-size:13px">
                    - O slug define a URL da página<br><br>
                    - Use nomes simples e sem acentos<br><br>
                    - Exemplo: <strong>minha-pagina</strong>
                </p>

            </div>

        </div>

    </div>

</div>

<script>
document.getElementById('slugInput').addEventListener('input', function(){
    this.value = this.value
        .toLowerCase()
        .replace(/[^a-z0-9\-]/g,'-')
        .replace(/\-+/g,'-');
});
</script>

<?php
$content = ob_get_clean();

/* =========================
SIDEBAR
========================= */

$rightSidebarEnabled = true;

$rightSidebarContent = '

<div class="c-card">

<h3>Sobre páginas</h3>

<p>
Páginas são criadas com base em modelos.<br><br>
Você poderá editar os blocos após criar.
</p>

</div>

';

require APP_PATH . '/views/layout_admin.php';