<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$baseId = (int)($_GET['base_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM bases WHERE id = :id");
$stmt->execute(['id' => $baseId]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    die('Base inválida.');
}

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Clonar Base: <?= htmlspecialchars($base['name']) ?></h1>
            <p class="c-page-subtitle">Crie uma base limpa. Os módulos serão adicionados depois na base clonada.</p>
        </div>

        <a class="c-btn-secondary" href="/web/admin/bases/index.php">
            Voltar Para Bases
        </a>
    </div>

    <div class="c-page-content">

        <?php flash_show(); ?>

        <form method="post" action="/app/actions/bases/base_clone_store.php">

            <input type="hidden" name="base_id" value="<?= (int)$base['id'] ?>">

            <div class="c-card">
                <h3>Dados da Nova Base</h3>

                <div class="c-form-grid">
                    <div class="c-form-group">
                        <label>Nome da Nova Base</label>
                        <input class="c-input" name="name" placeholder="Ex: Gol Fut" required>
                    </div>

                    <div class="c-form-group">
                        <label>Slug da Pasta</label>
                        <input class="c-input" name="slug" placeholder="Ex: gol-fut" required>
                    </div>
                </div>

                <p class="c-page-subtitle">
                    Use apenas letras minúsculas, números e hífen. Se o slug já existir, o sistema cria uma variação automaticamente.
                </p>
            </div>

            <div class="c-card">
                <h3>Clonagem Limpa</h3>
                <p>A nova base receberá apenas a estrutura principal: login, configurações, páginas, layout e banco base.</p>
                <p>Pastas de módulos e arquivos temporários não serão copiados. Depois da clonagem, entre na base criada e adicione somente os módulos necessários.</p>
            </div>

            <button class="c-btn-primary">
                Clonar Base
            </button>

        </form>

    </div>

</div>

<?php
$content = ob_get_clean();
$title = 'Clonar Base';

$rightSidebarEnabled = true;

$rightSidebarContent = '

<div class="c-card">
    <h3>Origem</h3>
    <p><strong>Base:</strong> '.htmlspecialchars($base['name']).'</p>
    <p><strong>Slug:</strong> '.htmlspecialchars($base['slug']).'</p>
</div>

<br>

<div class="c-card">
    <h3>Como funciona</h3>
    <p>A base original permanece intocável. A nova base nasce limpa para evitar carregar módulos e arquivos desnecessários.</p>
    <p>Depois, use a tela de módulos da própria base para montar o segmento.</p>
</div>

<br>

<div class="c-card">
    <h3>Módulos</h3>
    <p>Não são adicionados na clonagem.</p>
    <p>Isso deixa a base padrão mais segura e facilita manutenção.</p>
</div>
';
	
require APP_PATH . '/views/layout_admin.php';
