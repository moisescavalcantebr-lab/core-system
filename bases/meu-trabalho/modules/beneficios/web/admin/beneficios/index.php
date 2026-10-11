<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require_once __DIR__ . '/helpers.php';
$owner = holeriteOwner();
benefitEnsureSchema($pdo);
$title = 'Beneficios';
$url = PROJECT_URL . '/admin/beneficios/index.php';
$id = max(0, (int)($_GET['id'] ?? 0));
$edit = ($_GET['view'] ?? '') === 'edit';
$entry = $id > 0 && $edit ? benefitFind($pdo, $owner, $id) : [];
if ($edit && $id > 0 && !$entry) {
    http_response_code(404);
    exit('Beneficio nao encontrado.');
}
if (isset($_GET['pdf']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $pdfId = filter_var($_GET['pdf'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $attachment = $pdfId ? benefitAttachment($pdo, $owner, $pdfId) : null;
    if (!$attachment) {
        http_response_code(404);
        exit('PDF nao encontrado.');
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="beneficio-' . $pdfId . '.pdf"');
    header('Content-Length: ' . strlen($attachment['pdf']));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $attachment['pdf'];
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        exit('Requisicao invalida.');
    }
    csrf_verify();
    try {
        if (($_POST['operation'] ?? '') === 'save' && $edit) {
            if (($_POST['form_complete'] ?? '') !== '1') {
                throw new InvalidArgumentException('Envio incompleto. Confira o formulario e tente novamente.');
            }
            $record = benefitNormalize($_POST);
            benefitSave($pdo, $owner, $id, $record, holeritePdfUpload($_FILES['benefit_pdf'] ?? null), ($_POST['remove_pdf'] ?? '') === '1');
            flash('success', 'Beneficio salvo.');
        } elseif (($_POST['operation'] ?? '') === 'delete') {
            $deleteId = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$deleteId) {
                throw new InvalidArgumentException('Beneficio invalido.');
            }
            benefitDelete($pdo, $owner, $deleteId);
            flash('success', 'Beneficio excluido.');
        } else {
            throw new InvalidArgumentException('Operacao invalida.');
        }
        redirect($url);
    } catch (Throwable $e) {
        $error = $e instanceof PDOException
            ? ((int)($e->errorInfo[1] ?? 0) === 1062 ? 'Ja existe este beneficio para a empresa, matricula e competencia. Edite o registro existente.' : 'Nao foi possivel salvar. Tente novamente.')
            : $e->getMessage();
        if ($edit) {
            $entry = array_replace($entry, array_filter($_POST, static fn($value) => is_scalar($value)));
        }
    }
}
$records = benefitRecords($pdo, $owner);
holeriteEnsureSchema($pdo);
$stmt = $pdo->prepare('SELECT DISTINCT employer, registration FROM holerite_receipts WHERE owner_user_id = ? ORDER BY employer, registration');
$stmt->execute([$owner]);
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);
$companies = array_unique([...array_column($records, 'employer'), ...array_column($links, 'employer')]);
sort($companies);
$years = array_unique([date('Y'), ...array_map(static fn($row) => substr($row['reference_month'], 0, 4), $records)]);
rsort($years);
$year = is_string($_GET['year'] ?? null) && in_array($_GET['year'], $years, true) ? $_GET['year'] : substr($records[0]['reference_month'] ?? date('Y-m-d'), 0, 4);
$employer = is_string($_GET['employer'] ?? null) ? $_GET['employer'] : '';
$type = is_string($_GET['benefit_type'] ?? null) && isset(benefitTypes()[$_GET['benefit_type']]) ? $_GET['benefit_type'] : '';
$filtered = array_values(array_filter($records, static fn($row) => substr($row['reference_month'], 0, 4) === $year && ($employer === '' || $row['employer'] === $employer) && ($type === '' || $row['benefit_type'] === $type)));
$months = benefitMonths($filtered);
$transport = array_sum(array_column($months, 'transport'));
$meal = array_sum(array_column($months, 'meal'));
$chartMax = max([1, ...array_column($months, 'transport'), ...array_column($months, 'meal')]);
$labels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
ob_start();
?>
<div class="c-page h-page">
    <div class="c-page-header"><h1 class="c-page-title">Beneficios</h1><div class="h-actions"><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/meu_holerite/index.php">Meu Holerite</a><a class="c-btn-secondary" href="<?= holeriteEscape($edit ? $url : $url . '?view=edit') ?>"><?= $edit ? 'Historico' : 'Novo beneficio' ?></a></div></div>
    <div class="c-page-content">
        <?php flash_show(); ?>
        <?php if ($error !== ''): ?><p class="h-error" role="alert"><?= holeriteEscape($error) ?></p><?php endif; ?>
        <?php if ($edit): require __DIR__ . '/form.php'; else: ?>
        <form method="get" class="h-filters">
            <label>Ano<select class="c-input" name="year"><?php foreach ($years as $option): ?><option value="<?= holeriteEscape($option) ?>" <?= $option === $year ? 'selected' : '' ?>><?= holeriteEscape($option) ?></option><?php endforeach; ?></select></label>
            <label>Empresa<select class="c-input" name="employer"><option value="">Todas</option><?php foreach ($companies as $company): ?><option value="<?= holeriteEscape($company) ?>" <?= $company === $employer ? 'selected' : '' ?>><?= holeriteEscape($company) ?></option><?php endforeach; ?></select></label>
            <label>Beneficio<select class="c-input" name="benefit_type"><option value="">Todos</option><?php foreach (benefitTypes() as $key => $label): ?><option value="<?= $key ?>" <?= $type === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><button class="c-btn-secondary">Filtrar</button>
        </form>
        <div class="h-metrics b-metrics"><div><span>VT disponibilizado no ano</span><strong><?= holeriteMoney(holeriteDecimal($transport)) ?></strong></div><div><span>VA disponibilizado no ano</span><strong><?= holeriteMoney(holeriteDecimal($meal)) ?></strong></div></div>
        <section class="h-section">
            <div class="h-section-head"><h2>Creditos por competencia</h2><span><?= holeriteEscape($year) ?></span></div>
            <?php if (!$months): ?><p class="h-empty">Nenhum beneficio neste periodo.</p><?php else: ?>
            <div class="h-legend"><span class="b-transport">Vale-transporte</span><span class="b-meal">Vale-alimentacao</span></div>
            <div class="h-chart-scroll"><div class="h-chart" role="img" aria-label="VT e VA disponibilizados por competencia; valores no historico abaixo."><div class="h-plot">
                <?php for ($m = 1; $m <= 12; $m++): $key = sprintf('%s-%02d', $year, $m); ?><div class="h-bars"><?php foreach (benefitTypes() as $kind => $label): $value = $months[$key][$kind] ?? 0; ?><div class="h-bar b-<?= $kind ?>" style="height:<?= number_format($value / $chartMax * 100, 3, '.', '') ?>%" title="<?= $labels[$m - 1] ?> / <?= $label ?>: <?= holeriteMoney(holeriteDecimal($value)) ?>"></div><?php endforeach; ?></div><?php endfor; ?>
            </div><div class="h-chart-labels"><?php foreach ($labels as $label): ?><span><?= $label ?></span><?php endforeach; ?></div></div></div>
            <div class="h-table-scroll"><table class="c-table"><thead><tr><th>Competencia</th><th>VT</th><th>VA</th><th>Variacao VT</th><th>Variacao VA</th></tr></thead><tbody>
                <?php foreach ($months as $month => $values): $previous = date('Y-m', strtotime($month . '-01 -1 month')); $prior = $months[$previous] ?? null; ?><tr><td><?= date('m/Y', strtotime($month . '-01')) ?></td><td><?= benefitMonthValue($values, 'transport') ?></td><td><?= benefitMonthValue($values, 'meal') ?></td><td><?= benefitMonthChange($values, $prior, 'transport') ?></td><td><?= benefitMonthChange($values, $prior, 'meal') ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php endif; ?>
        </section>
        <section class="h-section"><div class="h-section-head"><h2>Historico</h2><span><?= count($filtered) ?> registro(s)</span></div><div class="h-table-scroll"><table class="c-table h-history"><thead><tr><th>Competencia</th><th>Empresa / matricula</th><th>Beneficio</th><th>Previsto</th><th>Disponibilizado</th><th>Diferenca</th><th>Credito</th><th>Acoes</th></tr></thead><tbody>
            <?php foreach ($filtered as $row): ?><tr><td><?= date('m/Y', strtotime($row['reference_month'])) ?></td><td><?= holeriteEscape($row['employer']) ?><small><?= holeriteEscape($row['registration']) ?></small></td><td><?= benefitTypes()[$row['benefit_type']] ?></td><td><?= $row['expected_amount'] === null ? '-' : holeriteMoney($row['expected_amount']) ?></td><td><?= holeriteMoney($row['credited_amount']) ?></td><td><?= $row['expected_amount'] === null ? '-' : holeriteMoney(holeriteDecimal(holeriteCents($row['credited_amount']) - holeriteCents($row['expected_amount']))) ?></td><td><?= $row['credited_date'] ? date('d/m/Y', strtotime($row['credited_date'])) : '-' ?></td><td><div class="h-actions"><a class="c-btn-secondary" href="<?= holeriteEscape($url . '?view=edit&id=' . $row['id']) ?>">Editar</a><?php if ($row['attachment_name']): ?><a class="c-btn-secondary" href="<?= holeriteEscape($url . '?pdf=' . $row['id']) ?>" title="Baixar comprovante PDF">PDF</a><?php endif; ?><form method="post" data-benefit-delete><?= csrf_field() ?><input type="hidden" name="operation" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="c-btn-secondary">Excluir</button></form></div></td></tr><?php endforeach; ?>
            <?php if (!$filtered): ?><tr><td colspan="8">Nenhum beneficio encontrado.</td></tr><?php endif; ?>
        </tbody></table></div></section>
        <?php endif; ?>
    </div>
</div>
<?php require dirname(__DIR__) . '/meu_holerite/ui.php'; ?>
<style>.b-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }.b-transport { --series: #14b8a6; }.b-meal { --series: #3b82f6; }#benefit-form > .h-actions { padding-top: 22px; }</style>
<script>document.querySelectorAll('[data-benefit-delete]').forEach(form => form.addEventListener('submit', event => { if (!confirm('Excluir este beneficio e seu comprovante?')) event.preventDefault(); }));</script>
<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
