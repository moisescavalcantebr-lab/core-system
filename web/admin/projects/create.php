<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

/* =========================
CAPTURAR LEAD
========================= */

$leadId = (int)($_GET['lead_id'] ?? 0);
$lead = null;

if ($leadId) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM leads
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$leadId]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =========================
BASES ATIVAS
========================= */

$bases = $pdo->query("
    SELECT *
    FROM bases
    WHERE status = 1
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
PLANOS ATIVOS
========================= */

$plans = $pdo->query("
    SELECT *
    FROM plans
    WHERE status = 1
    ORDER BY price ASC
")->fetchAll(PDO::FETCH_ASSOC);

$title = 'Criar Projeto';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">

        <div>
            <h1 class="c-page-title">Criar Projeto</h1>
            <p class="c-page-subtitle">Configure um novo projeto no sistema</p>
        </div>

        <div class="c-page-actions">
            <a class="c-btn-secondary" href="/public/admin/projects/index.php">
                Voltar
            </a>
        </div>

    </div>

    <div class="c-page-content">

        <div class="c-card">
            <p>Preencha os dados para criar um novo projeto.</p>
        </div>

        <form method="post" action="/app/actions/projects/store.php">

            <?= csrf_field() ?>

            <input type="hidden" name="lead_id" value="<?= $leadId ?>">

            <div class="c-card">

                <div class="c-form-grid-2">

                    <div class="c-form-group">
                        <label>Nome do Projeto</label>
                        <input class="c-input" name="name"
                               value="<?= htmlspecialchars($lead['site_name'] ?? '') ?>"
                               required>
                    </div>

                    <div class="c-form-group">
                        <label>Slug (URL)</label>
                        <input class="c-input" name="slug"
                               value="<?= htmlspecialchars($lead['slug'] ?? '') ?>"
                               required>

                        <small class="c-form-hint">
                            Somente letras minúsculas, números e hífen
                        </small>
                    </div>

                    <div class="c-form-group">
                        <label>Nome do Responsável</label>
                        <input class="c-input" name="owner_name"
                               value="<?= htmlspecialchars($lead['name'] ?? '') ?>"
                               required>
                    </div>

                    <div class="c-form-group">
                        <label>Email do Responsável</label>
                        <input class="c-input" type="email" name="owner_email"
                               value="<?= htmlspecialchars($lead['email'] ?? '') ?>"
                               required>
                    </div>

                    <div class="c-form-group">
                        <label>Base</label>

                        <select class="c-input" name="base_id" required>
                            <option value="">Selecione a base</option>

                            <?php foreach ($bases as $base): ?>
                                <option value="<?= $base['id'] ?>"
                                    <?= isset($lead['base_id']) && $lead['base_id'] == $base['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($base['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="c-form-group">
                        <label>Plano Inicial</label>

                        <input class="c-input" value="Plano Free (automático)" disabled>

                        <small class="c-form-hint">
                            O projeto será criado no plano gratuito.
                        </small>
                    </div>

                </div>

                <div style="margin-top:20px;">
                    <button class="c-btn-secondary">
                        Criar Projeto
                    </button>
                </div>

            </div>

        </form>

    </div>

</div>

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
Crie projetos baseados em templates (bases).
Cada projeto é isolado e configurável.
</p>

</div>

';

require APP_PATH . '/views/layout_admin.php';