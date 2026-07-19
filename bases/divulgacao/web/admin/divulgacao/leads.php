<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

divulgacaoRequireAdmin();
divulgacaoEnsureSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? 'novo');

    if ($id > 0 && in_array($status, ['novo', 'contatado', 'convertido', 'arquivado'], true)) {
        $stmt = $pdo->prepare('UPDATE divulgacao_leads SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
        flash('success', 'Lead atualizado.');
    }

    redirect(PROJECT_URL . '/admin/divulgacao/leads.php');
}

$title = 'Leads';
$leads = $pdo->query("
    SELECT l.*, p.title AS page_title, p.slug AS page_slug
    FROM divulgacao_leads l
    LEFT JOIN divulgacao_pages p ON p.id = l.page_id
    ORDER BY l.created_at DESC
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<div class="c-page-header">
    <div>
        <h1>Leads</h1>
        <p>Contatos capturados pelas paginas de divulgacao.</p>
    </div>
    <div class="c-page-actions">
        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/divulgacao/index.php">Paginas</a>
    </div>
</div>

<?php flash_show(); ?>

<div class="c-card">
    <?php if (empty($leads)): ?>
        <p>Nenhum lead capturado ainda.</p>
    <?php else: ?>
        <div class="c-table-wrap">
            <table class="c-table">
                <thead>
                    <tr>
                        <th>Contato</th>
                        <th>Pagina</th>
                        <th>Mensagem</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $lead): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars((string)$lead['name']) ?></strong><br>
                                <?= htmlspecialchars((string)$lead['phone']) ?><br>
                                <small><?= htmlspecialchars((string)($lead['email'] ?? '')) ?></small>
                            </td>
                            <td><?= htmlspecialchars((string)($lead['page_title'] ?? '-')) ?></td>
                            <td><?= nl2br(htmlspecialchars((string)($lead['message'] ?? ''))) ?></td>
                            <td>
                                <span class="c-badge <?= divulgacaoBadgeClass((string)$lead['status']) ?>">
                                    <?= divulgacaoLeadStatusLabel((string)$lead['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars((string)$lead['created_at']) ?></td>
                            <td>
                                <form method="post" class="lead-status-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$lead['id'] ?>">
                                    <select name="status">
                                        <?php foreach (['novo', 'contatado', 'convertido', 'arquivado'] as $status): ?>
                                            <option value="<?= $status ?>" <?= (string)$lead['status'] === $status ? 'selected' : '' ?>>
                                                <?= divulgacaoLeadStatusLabel($status) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="c-btn-secondary" type="submit">Salvar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.lead-status-form { display:flex; gap:8px; align-items:center; }
@media (max-width: 760px) { .lead-status-form { flex-direction: column; align-items: stretch; } }
</style>
<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
