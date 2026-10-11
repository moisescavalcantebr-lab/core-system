<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require_once __DIR__ . '/helpers.php';
$owner = contasOwner();
contasEnsureSchema($pdo);
$title = 'Contas a Pagar e Receber';
$url = PROJECT_URL . '/admin/contas/index.php';
$today = contasToday();
$view = in_array($_GET['view'] ?? '', ['edit','movement'], true) ? $_GET['view'] : 'list';
$id = max(0,(int)($_GET['id'] ?? 0));
$entry = $id > 0 && $view !== 'list' ? contasFind($pdo,$owner,$id) : [];
if (($view === 'movement' && !$entry) || ($id > 0 && $view !== 'list' && !$entry)) {
    http_response_code(404);
    exit('Conta nao encontrada.');
}
if (isset($_GET['pdf']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $pdfId = filter_var($_GET['pdf'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $attachment = $pdfId ? contasAttachment($pdo,$owner,$pdfId) : null;
    if (!$attachment) {
        http_response_code(404);
        exit('PDF nao encontrado.');
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="conta-'.$pdfId.'.pdf"');
    header('Content-Length: '.strlen($attachment['pdf']));
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
        $operation = $_POST['operation'] ?? '';
        $redirectUrl = $url;
        if ($operation === 'save' && $view === 'edit') {
            if (($_POST['form_complete'] ?? '') !== '1') {
                throw new InvalidArgumentException('Envio incompleto. Confira o formulario.');
            }
            contasSave($pdo,$owner,$id,contasNormalize($_POST),contasUpload($_FILES['account_pdf'] ?? null),($_POST['remove_pdf'] ?? '') === '1');
            flash('success','Conta salva.');
        } elseif ($operation === 'pay' && $view === 'movement') {
            if (!is_string($_POST['payment_token'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D',$_POST['payment_token'])) {
                throw new InvalidArgumentException('Envio invalido. Atualize a pagina.');
            }
            if (($_POST['form_complete'] ?? '') !== '1') {
                throw new InvalidArgumentException('Envio incompleto. Confira o formulario.');
            }
            contasPay($pdo,$owner,$id,$_POST);
            flash('success',$entry['kind'] === 'receivable' ? 'Recebimento registrado.' : 'Pagamento registrado.');
            $redirectUrl .= '?view=movement&id='.$id;
        } elseif (in_array($operation,['delete','cancel','remove_payment'],true)) {
            $target = filter_var($_POST['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $payment = filter_var($_POST['payment_id'] ?? 0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
            if (!$target || ($operation === 'remove_payment' && (!$payment || $payment < 1))) {
                throw new InvalidArgumentException('Registro invalido.');
            }
            contasAction($pdo,$owner,$target,$operation,(int)$payment);
            flash('success','Conta atualizada.');
            if ($operation === 'remove_payment') {
                $redirectUrl .= '?view=movement&id='.$target;
            }
        } else {
            throw new InvalidArgumentException('Operacao invalida.');
        }
        redirect($redirectUrl);
    } catch (Throwable $e) {
        $error = $e instanceof PDOException ? 'Nao foi possivel salvar. Tente novamente.' : $e->getMessage();
        if ($view === 'edit') {
            $entry = array_replace($entry,array_filter($_POST,static fn($value)=>is_scalar($value)));
        }
    }
}
$records = contasRecords($pdo,$owner);
$payments = contasPayments($pdo,$owner);
$kind = is_string($_GET['kind'] ?? null) && isset(contasKinds()[$_GET['kind']]) ? $_GET['kind'] : '';
$status = is_string($_GET['status'] ?? null) && isset(contasStatusLabels()[$_GET['status']]) ? $_GET['status'] : '';
$month = is_string($_GET['month'] ?? null) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$_GET['month']) ? $_GET['month'] : '';
$search = is_string($_GET['search'] ?? null) ? mb_substr(trim($_GET['search']),0,150) : '';
$filtered = array_values(array_filter($records,static fn($row)=>($kind === '' || $row['kind'] === $kind) && ($status === '' || contasStatus($row,$today) === $status) && ($month === '' || substr($row['due_date'],0,7) === $month) && ($search === '' || mb_stripos($row['title'].' '.$row['counterparty'].' '.$row['category'],$search) !== false)));
$analytics = contasAnalytics($filtered,[],$today);
$summary = $analytics['summary'];
$categories = array_unique(array_filter(array_column($records,'category')));
sort($categories);
ob_start();
?>
<div class="c-page a-page"><div class="c-page-header"><h1 class="c-page-title">Contas a Pagar e Receber</h1><div class="a-actions"><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/dashboard.php">Dashboard</a><a class="c-btn-secondary" href="<?= contasEscape($view === 'list' ? $url.'?view=edit' : $url) ?>"><?= $view === 'list' ? 'Nova conta' : 'Contas' ?></a></div></div>
<div class="c-page-content"><?php flash_show(); ?><?php if ($error !== ''): ?><p class="a-error" role="alert"><?= contasEscape($error) ?></p><?php endif; ?>
<?php if ($view === 'edit'): require __DIR__.'/form.php'; elseif ($view === 'movement'): require __DIR__.'/movement.php'; else: ?>
    <form method="get" class="a-filters">
        <label>Tipo<select class="c-input" name="kind"><option value="">Todos</option><?php foreach(contasKinds() as $key=>$label): ?><option value="<?= $key ?>" <?= $kind === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <label>Situacao<select class="c-input" name="status"><option value="">Todas</option><?php foreach(contasStatusLabels() as $key=>$label): ?><option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <label>Mes do vencimento<input class="c-input" type="month" name="month" value="<?= contasEscape($month) ?>"></label><label>Buscar<input class="c-input" type="search" name="search" maxlength="150" value="<?= contasEscape($search) ?>"></label><button class="c-btn-secondary">Filtrar</button>
    </form>
    <div class="a-metrics"><?php foreach(['pending_payable'=>'A pagar em aberto','pending_receivable'=>'A receber em aberto','overdue_payable'=>'A pagar vencido','overdue_receivable'=>'A receber vencido'] as $key=>$label): ?><div><span><?= $label ?></span><strong><?= contasMoney($summary[$key]) ?></strong></div><?php endforeach; ?></div>
    <section class="a-section"><h2>Contas</h2><div class="a-scroll"><table class="c-table a-history"><thead><tr><th>Vencimento</th><th>Descricao</th><th>Tipo</th><th>Valor</th><th>Movimentado</th><th>Restante</th><th>Situacao</th><th>Acoes</th></tr></thead><tbody>
    <?php foreach($filtered as $row): $rowStatus = contasStatus($row,$today); ?><tr><td><?= date('d/m/Y',strtotime($row['due_date'])) ?></td><td><?= contasEscape($row['title']) ?><small><?= contasEscape(implode(' / ',array_filter([$row['counterparty'],$row['category']]))) ?></small></td><td><?= contasKinds()[$row['kind']] ?></td><td><?= contasMoney(contasCents($row['amount'])) ?></td><td><?= contasMoney(contasCents($row['paid_amount'])) ?></td><td><?= (int)$row['canceled'] === 1 ? '-' : contasMoney(contasRemaining($row)) ?></td><td><span class="a-status a-status-<?= $rowStatus ?>"><?= contasStatusLabels()[$rowStatus] ?></span></td><td><div class="a-actions"><a class="c-btn-secondary" href="<?= contasEscape($url.'?view=edit&id='.$row['id']) ?>">Editar</a><a class="c-btn-secondary" href="<?= contasEscape($url.'?view=movement&id='.$row['id']) ?>">Movimentacoes</a><?php if($row['attachment_name']): ?><a class="c-btn-secondary" href="<?= contasEscape($url.'?pdf='.$row['id']) ?>" title="Baixar documento PDF">PDF</a><?php endif; ?>
    <?php if(contasCents($row['paid_amount']) === 0): ?><?php foreach(['cancel'=>((int)$row['canceled'] === 1 ? 'Reativar' : 'Cancelar'),'delete'=>'Excluir'] as $operation=>$label): ?><form method="post" <?= $operation === 'delete' ? 'data-contas-confirm="Excluir esta conta e seu documento?"' : '' ?>><?= csrf_field() ?><input type="hidden" name="operation" value="<?= $operation ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="c-btn-secondary"><?= $label ?></button></form><?php endforeach; ?><?php endif; ?>
    </div></td></tr><?php endforeach; ?><?php if(!$filtered): ?><tr><td colspan="8">Nenhuma conta encontrada.</td></tr><?php endif; ?>
    </tbody></table></div></section>
<?php endif; ?></div></div>
<?php require __DIR__.'/ui.php'; $content = ob_get_clean(); require APP_PATH.'/views/layout_admin.php'; ?>
