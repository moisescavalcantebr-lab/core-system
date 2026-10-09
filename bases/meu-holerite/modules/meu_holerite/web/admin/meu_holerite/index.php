<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require_once __DIR__ . '/helpers.php';

$owner = holeriteOwner();
holeriteEnsureSchema($pdo);
$title = 'Meu Holerite';
$url = PROJECT_URL . '/admin/meu_holerite/index.php';
$view = ($_GET['view'] ?? '') === 'edit' ? 'edit' : 'history';
$id = max(0, (int)($_GET['id'] ?? 0));
$error = '';
$entry = [];
$items = [];

if ($view === 'edit' && $id > 0) {
    $entry = holeriteFind($pdo, $owner, $id);
    if (!$entry) {
        http_response_code(404);
        exit('Holerite nao encontrado.');
    }
    $stmt = $pdo->prepare('SELECT * FROM holerite_items WHERE receipt_id = ? ORDER BY display_order, id');
    $stmt->execute([$id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        exit('Requisicao invalida.');
    }
    csrf_verify();
    try {
        if (($_POST['operation'] ?? '') === 'delete') {
            $deleteId = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$deleteId) {
                throw new InvalidArgumentException('Holerite invalido.');
            }
            $stmt = $pdo->prepare('DELETE FROM holerite_receipts WHERE id = ? AND owner_user_id = ?');
            $stmt->execute([$deleteId, $owner]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Holerite nao encontrado.');
            }
            flash('success', 'Holerite excluido.');
        } elseif (($_POST['operation'] ?? '') === 'save' && $view === 'edit') {
            if (($_POST['form_complete'] ?? '') !== '1') {
                throw new InvalidArgumentException('Envio incompleto. Confira os itens e tente novamente.');
            }
            holeriteSave($pdo, $owner, $id, holeriteNormalize($_POST));
            flash('success', 'Holerite salvo.');
        } else {
            throw new InvalidArgumentException('Operacao invalida.');
        }
        redirect($url);
    } catch (Throwable $e) {
        $error = $e instanceof PDOException
            ? ((int)($e->errorInfo[1] ?? 0) === 1062 ? 'Ja existe um holerite desta empresa, competencia, tipo e sequencia.' : 'Nao foi possivel salvar. Tente novamente.')
            : $e->getMessage();
        if ($view === 'edit') {
            $entry = array_replace($entry, array_filter($_POST, static fn($value) => is_scalar($value)));
            $items = is_array($_POST['items'] ?? null) ? array_values(array_filter(array_slice($_POST['items'], 0, 100), 'is_array')) : [];
            $entry['received'] = ($_POST['received'] ?? '') === '1' ? 1 : 0;
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM holerite_receipts WHERE owner_user_id = ? ORDER BY reference_month DESC, id DESC');
$stmt->execute([$owner]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
$years = array_unique([date('Y'), ...array_map(static fn($row) => substr($row['reference_month'], 0, 4), $records)]);
rsort($years);
$year = is_string($_GET['year'] ?? null) && in_array($_GET['year'], $years, true) ? $_GET['year'] : substr($records[0]['reference_month'] ?? date('Y-m-d'), 0, 4);
$employer = is_string($_GET['employer'] ?? null) ? $_GET['employer'] : '';
$type = is_string($_GET['type'] ?? null) && isset(holeriteTypes()[$_GET['type']]) ? $_GET['type'] : '';
$companies = array_unique(array_column($records, 'employer'));
sort($companies);
$scope = array_values(array_filter($records, static fn($row) => ($employer === '' || $row['employer'] === $employer) && ($type === '' || $row['receipt_type'] === $type)));
$filtered = array_values(array_filter($scope, static fn($row) => substr($row['reference_month'], 0, 4) === $year));
$allMonths = holeriteMonths($scope);
$months = holeriteMonths($filtered);
$totalGross = array_sum(array_column($months, 'gross_amount'));
$totalDeductions = array_sum(array_column($months, 'deductions_amount'));
$totalNet = array_sum(array_column($months, 'net_amount'));
$monthKeys = array_keys($allMonths);
$compareB = is_string($_GET['compare_b'] ?? null) && isset($allMonths[$_GET['compare_b']]) ? $_GET['compare_b'] : ($monthKeys[count($monthKeys) - 1] ?? '');
$compareA = is_string($_GET['compare_a'] ?? null) && isset($allMonths[$_GET['compare_a']]) ? $_GET['compare_a'] : ($monthKeys[max(0, count($monthKeys) - 2)] ?? '');
$chartMax = max([1, ...array_map(static fn($month) => max($month['gross_amount'], $month['deductions_amount'], abs($month['net_amount'])), $months)]);
$hasNegative = count(array_filter($months, static fn($month) => $month['net_amount'] < 0)) > 0;
$monthLabels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

ob_start();
?>
<div class="c-page h-page">
    <div class="c-page-header">
        <div><h1 class="c-page-title">Meu Holerite</h1></div>
        <div class="c-page-actions">
            <a class="c-btn-secondary" href="<?= holeriteEscape($view === 'edit' ? $url : $url . '?view=edit') ?>"><?= $view === 'edit' ? 'Historico' : 'Novo holerite' ?></a>
        </div>
    </div>
    <div class="c-page-content">
        <?php flash_show(); ?>
        <?php if ($error !== ''): ?><p class="h-error" role="alert"><?= holeriteEscape($error) ?></p><?php endif; ?>
        <?php if ($view === 'edit'): ?>
            <?php require __DIR__ . '/form.php'; ?>
        <?php else: ?>
            <form method="get" class="h-filters">
                <label>Ano<select class="c-input" name="year"><?php foreach ($years as $option): ?><option <?= $option === $year ? 'selected' : '' ?> value="<?= holeriteEscape($option) ?>"><?= holeriteEscape($option) ?></option><?php endforeach; ?></select></label>
                <label>Empresa<select class="c-input" name="employer"><option value="">Todas</option><?php foreach ($companies as $company): ?><option <?= $company === $employer ? 'selected' : '' ?> value="<?= holeriteEscape($company) ?>"><?= holeriteEscape($company) ?></option><?php endforeach; ?></select></label>
                <label>Tipo<select class="c-input" name="type"><option value="">Todos</option><?php foreach (holeriteTypes() as $key => $label): ?><option <?= $key === $type ? 'selected' : '' ?> value="<?= $key ?>"><?= $label ?></option><?php endforeach; ?></select></label>
                <button class="c-btn-secondary" type="submit">Filtrar</button>
            </form>
            <div class="h-metrics">
                <div><span>Proventos no ano</span><strong><?= holeriteMoney(holeriteDecimal($totalGross)) ?></strong></div>
                <div><span>Descontos no ano</span><strong><?= holeriteMoney(holeriteDecimal($totalDeductions)) ?></strong></div>
                <div><span>Liquido no ano</span><strong><?= holeriteMoney(holeriteDecimal($totalNet)) ?></strong></div>
                <div><span>Media por competencia</span><strong><?= holeriteMoney(holeriteDecimal(count($months) ? (int)round($totalNet / count($months)) : 0)) ?></strong></div>
            </div>
            <section class="h-section">
                <div class="h-section-head"><h2>Evolucao mensal</h2><span><?= holeriteEscape($year) ?></span></div>
                <?php if (!$months): ?><p class="h-empty">Nenhum holerite neste periodo.</p><?php else: ?>
                    <div class="h-legend"><span class="h-income">Proventos</span><span class="h-deduction">Descontos</span><span class="h-net">Liquido</span></div>
                    <div class="h-chart-scroll"><div class="h-chart <?= $hasNegative ? 'h-chart-negative' : '' ?>" role="img" aria-label="Proventos, descontos e liquido por competencia; valores no historico abaixo.">
                        <div class="h-plot"><?php for ($m = 1; $m <= 12; $m++): $key = sprintf('%s-%02d', $year, $m); $bucket = $months[$key] ?? null; ?>
                            <div class="h-bars"><?php foreach (['gross_amount' => 'income', 'deductions_amount' => 'deduction', 'net_amount' => 'net'] as $field => $class): $amount = $bucket[$field] ?? 0; ?>
                                <div class="h-bar h-<?= $class ?> <?= $amount < 0 ? 'h-below-zero' : '' ?>" style="height:<?= number_format(abs($amount) / $chartMax * 100, 3, '.', '') ?>%" title="<?= $monthLabels[$m - 1] ?>: <?= $bucket ? holeriteMoney(holeriteDecimal($amount)) : 'Sem registro' ?>"></div>
                            <?php endforeach; ?></div>
                        <?php endfor; ?></div>
                        <div class="h-chart-labels"><?php for ($m = 1; $m <= 12; $m++): ?><span><?= $monthLabels[$m - 1] ?></span><?php endfor; ?></div>
                    </div></div>
                <?php endif; ?>
            </section>
            <section class="h-section">
                <h2>Comparar competencias</h2>
                <?php if (count($monthKeys) < 2): ?><p class="h-empty">Ainda nao ha duas competencias cadastradas.</p><?php else: ?>
                    <form method="get" class="h-filters h-compare">
                        <input type="hidden" name="year" value="<?= holeriteEscape($year) ?>"><input type="hidden" name="employer" value="<?= holeriteEscape($employer) ?>"><input type="hidden" name="type" value="<?= holeriteEscape($type) ?>">
                        <?php foreach (['compare_a' => ['Anterior', $compareA], 'compare_b' => ['Atual', $compareB]] as $name => [$label, $selected]): ?><label><?= $label ?><select class="c-input" name="<?= $name ?>"><?php foreach ($monthKeys as $key): ?><option value="<?= $key ?>" <?= $key === $selected ? 'selected' : '' ?>><?= date('m/Y', strtotime($key . '-01')) ?></option><?php endforeach; ?></select></label><?php endforeach; ?>
                        <button class="c-btn-secondary">Comparar</button>
                    </form>
                    <div class="h-table-scroll"><table class="c-table"><thead><tr><th>Valor</th><th><?= date('m/Y', strtotime($compareA . '-01')) ?></th><th><?= date('m/Y', strtotime($compareB . '-01')) ?></th><th>Diferenca</th><th>Variacao</th></tr></thead><tbody>
                    <?php foreach (['gross_amount' => 'Proventos', 'deductions_amount' => 'Descontos', 'net_amount' => 'Liquido'] as $field => $label): $a = $allMonths[$compareA][$field]; $b = $allMonths[$compareB][$field]; ?>
                        <tr><td><?= $label ?></td><td><?= holeriteMoney(holeriteDecimal($a)) ?></td><td><?= holeriteMoney(holeriteDecimal($b)) ?></td><td><?= holeriteMoney(holeriteDecimal($b - $a)) ?></td><td><?= $a > 0 ? number_format(($b - $a) / $a * 100, 2, ',', '.') . '%' : '-' ?></td></tr>
                    <?php endforeach; ?></tbody></table></div>
                <?php endif; ?>
            </section>
            <section class="h-section">
                <div class="h-section-head"><h2>Historico</h2><span><?= count($filtered) ?> holerite(s)</span></div>
                <div class="h-table-scroll"><table class="c-table h-history"><thead><tr><th>Competencia</th><th>Empresa / tipo</th><th>Salario-base</th><th>Proventos</th><th>Descontos</th><th>Liquido</th><th>Recebido</th><th>Acoes</th></tr></thead><tbody>
                <?php foreach ($filtered as $row): ?><tr>
                    <td><?= date('m/Y', strtotime($row['reference_month'])) ?></td><td><?= holeriteEscape($row['employer']) ?><small><?= holeriteTypes()[$row['receipt_type']] ?? holeriteEscape($row['receipt_type']) ?> / <?= (int)$row['sequence_number'] ?></small></td>
                    <?php foreach (['base_salary', 'gross_amount', 'deductions_amount', 'net_amount'] as $field): ?><td><?= holeriteMoney($row[$field]) ?></td><?php endforeach; ?>
                    <td><?= (int)$row['received'] === 1 ? 'Sim' : 'Nao' ?></td>
                    <td><div class="h-actions"><a class="c-btn-secondary" href="<?= holeriteEscape($url . '?view=edit&id=' . $row['id']) ?>">Abrir</a><form method="post" data-holerite-delete><?= csrf_field() ?><input type="hidden" name="operation" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="c-btn-secondary" type="submit">Excluir</button></form></div></td>
                </tr><?php endforeach; ?>
                <?php if (!$filtered): ?><tr><td colspan="8">Nenhum holerite encontrado.</td></tr><?php endif; ?>
                </tbody></table></div>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/ui.php'; ?>
<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
