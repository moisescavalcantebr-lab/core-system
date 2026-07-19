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

function pagesModelTitle(string $slug, array $data = []): string
{
    $title = trim((string)($data['title'] ?? $data['name'] ?? ''));

    if ($title !== '') {
        return $title;
    }

    return match ($slug) {
        'model_page' => 'Modelo Página',
        'model_blog' => 'Modelo Blog',
        default => ucwords(str_replace(['_', '-'], ' ', $slug)),
    };
}

$modelsBySlug = [];

foreach ($pdo->query("
    SELECT slug,title,content_path
    FROM core_page_contents
    WHERE type='model'
    AND area='public'
    ORDER BY title
")->fetchAll(PDO::FETCH_ASSOC) as $modelRow) {
    $slug = trim((string)($modelRow['slug'] ?? ''));

    if ($slug === '') {
        continue;
    }

    $modelsBySlug[$slug] = [
        'slug' => $slug,
        'title' => trim((string)($modelRow['title'] ?? '')) ?: pagesModelTitle($slug),
    ];
}

foreach (glob(STORAGE_PATH . '/paginas/models/*.json') ?: [] as $modelFile) {
    $slug = pathinfo($modelFile, PATHINFO_FILENAME);
    $json = json_decode((string)file_get_contents($modelFile), true);
    $json = is_array($json) ? $json : [];

    $modelsBySlug[$slug] ??= [
        'slug' => $slug,
        'title' => pagesModelTitle($slug, $json),
    ];
}

$models = array_values($modelsBySlug);
usort($models, fn(array $a, array $b): int => strcmp((string)$a['title'], (string)$b['title']));

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
               href="<?= $baseUrl ?>/web/admin/pages/index.php">
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
                        <select name="model_slug" class="c-input" id="pageModelSelect" required>
                            <option value="">Selecione</option>

                            <?php foreach ($models as $m): ?>
                                <option value="<?= htmlspecialchars($m['slug']) ?>">
                                    <?= htmlspecialchars($m['title']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="c-form-group c-blog-taxonomy-field" hidden>
                        <label>Categoria</label>
                        <input name="category" class="c-input" placeholder="Ex: Financeiro">
                    </div>

                    <div class="c-form-group c-blog-taxonomy-field" hidden>
                        <label>Subcategoria</label>
                        <input name="sub_category" class="c-input" placeholder="Ex: Gestão">
                    </div>

                    <p class="c-text-muted c-page-category-hint" hidden>
                        Páginas comuns entram em Produtos por padrão.
                    </p>

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

const pageModelSelect = document.getElementById('pageModelSelect');
const blogTaxonomyFields = document.querySelectorAll('.c-blog-taxonomy-field');
const pageCategoryHint = document.querySelector('.c-page-category-hint');

function toggleBlogTaxonomyFields() {
    const isBlog = pageModelSelect && pageModelSelect.value === 'model_blog';
    const isPage = pageModelSelect && pageModelSelect.value === 'model_page';

    blogTaxonomyFields.forEach(function(field) {
        field.hidden = !isBlog;

        if (!isBlog) {
            const input = field.querySelector('input');
            if (input) input.value = '';
        }
    });

    if (pageCategoryHint) {
        pageCategoryHint.hidden = !isPage;
    }
}

if (pageModelSelect) {
    pageModelSelect.addEventListener('change', toggleBlogTaxonomyFields);
    toggleBlogTaxonomyFields();
}
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
