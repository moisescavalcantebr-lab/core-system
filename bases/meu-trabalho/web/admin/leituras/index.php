<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/project_bootstrap.php';
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/report.php';
$owner = eqOwner();
eqEnsureSchema($pdo);
$title = 'Leituras';
$url = PROJECT_URL.'/admin/leituras/index.php';
$tab = in_array($_GET['tab'] ?? '',['reasons','archives'],true) ? $_GET['tab'] : 'readings';
$view = ($_GET['view'] ?? '') === 'edit' ? 'edit' : 'list';
$error = '';
try {
    $activeMonth = eqWorkspace($pdo,$owner);
    $month = eqMonth($_GET['month'] ?? $activeMonth);
    $filterReason = empty($_GET['reason']) ? 0 : eqId($_GET['reason']);
    $id = empty($_GET['id']) ? 0 : eqId($_GET['id']);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    exit('Filtro ou identificador invalido.');
}
$entry = $view === 'edit' && $id ? eqFind($pdo,$owner,$id,$tab === 'reasons') : [];
if ($tab === 'archives') $view = 'list';
if ($view === 'edit' && $id && !$entry) {
    http_response_code(404);
    exit('Registro nao encontrado.');
}
$listUrl = $url.'?tab='.$tab.'&month='.$month.($filterReason ? '&reason='.$filterReason : '');
$period = eqPeriod($pdo,$owner,$month);
$archived = $period['archived_at'] !== null;
if ($tab === 'readings' && $view === 'edit' && !$id && $archived) $view = 'list';
if ($tab === 'readings' && $view === 'edit' && $id && eqPeriod($pdo,$owner,substr($entry['work_date'],0,7))['archived_at'] !== null) {
    $month = substr($entry['work_date'],0,7);
    $period = eqPeriod($pdo,$owner,$month);
    $archived = true;
    $view = 'list';
    $listUrl = $url.'?month='.$month;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        exit('Requisicao invalida.');
    }
    csrf_verify();
    try {
        $operation = eqText($_POST['operation'] ?? '',30,'Operacao');
        $revision = eqInteger($_POST['revision'] ?? '0','Versao',false,2147483647);
        $redirectUrl = $listUrl;
        if ($operation === 'save' && $view === 'edit') {
            if (($_POST['form_complete'] ?? '') !== '1') throw new InvalidArgumentException('Formulario incompleto. Confira os campos.');
            if ($tab === 'reasons') {
                eqSaveReason($pdo,$owner,$id,$_POST,$revision);
                flash('success','Razao salva.');
            } else {
                $data = eqNormalizeReading($_POST);
                eqSaveReading($pdo,$owner,$id,$data,$revision);
                flash('success','Leitura salva.');
                $redirectUrl = $url.'?month='.substr($data['work_date'],0,7);
            }
        } elseif ($view === 'list' && $tab === 'readings' && in_array($operation,['archive_month','reopen_month'],true)) {
            $nextMonth = eqSetPeriod($pdo,$owner,$month,$operation,$revision);
            flash('success',$operation === 'archive_month' ? 'Mes arquivado. Historico preservado.' : 'Mes reaberto para conferencia.');
            $redirectUrl = $url.'?month='.$nextMonth;
        } elseif ($view === 'list' && (($tab === 'reasons' && in_array($operation,['delete_reason','toggle_reason'],true)) || ($tab === 'readings' && $operation === 'delete_reading'))) {
            eqAction($pdo,$owner,eqId($_POST['id'] ?? ''),$operation,$revision);
            flash('success','Registro atualizado.');
        } else {
            throw new InvalidArgumentException('Operacao invalida.');
        }
        redirect($redirectUrl);
    } catch (Throwable $e) {
        $error = $e instanceof PDOException ? 'Nao foi possivel salvar. Tente novamente.' : $e->getMessage();
        if ($view === 'edit') {
            if (isset($revision)) $entry['revision'] = $revision;
            foreach (['number','location','reason_id','work_date','total','executed','sent','lis','leit','notes'] as $field) {
                if (isset($_POST[$field]) && is_scalar($_POST[$field])) $entry[$field] = $_POST[$field];
            }
        }
    }
}
$reasons = eqReasons($pdo,$owner);
$activeReasons = array_filter($reasons,static fn($r)=>(int)$r['active'] === 1 || ($tab === 'readings' && $id && (int)$r['id'] === (int)($entry['reason_id'] ?? 0)));
$anchor = new DateTimeImmutable($month.'-01');
$pdo->beginTransaction();
try {
    $activeMonth = eqWorkspace($pdo,$owner,true);
    $data = eqAnalytics(eqReadings($pdo,$owner,$anchor->modify('-11 months')->format('Y-m-d'),$anchor->format('Y-m-t'),$filterReason),$month);
    $period = eqPeriod($pdo,$owner,$month);
    $archived = $period['archived_at'] !== null;
    $periodSummary = eqPeriodSummary($pdo,$owner,$month);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}
$defaultDate = substr(eqToday(),0,7) === $month ? eqToday() : $month.'-01';
ob_start();
?>
<div class="c-page eq-page"><div class="c-page-header"><h1 class="c-page-title">Leituras</h1><div class="eq-actions"><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/dashboard.php">Dashboard</a><?php if($view === 'list' && $tab !== 'archives' && ($tab === 'reasons' || !$archived)): ?><a class="c-btn-secondary" href="<?= eqEscape($listUrl.'&view=edit') ?>"><?= $tab === 'reasons' ? 'Adicionar razao' : 'Adicionar leitura' ?></a><?php elseif($view === 'edit'): ?><a class="c-btn-secondary" href="<?= eqEscape($listUrl) ?>">Voltar</a><?php endif; ?></div></div>
<div class="c-page-content"><?php flash_show(); ?>
<nav class="eq-tabs" aria-label="Leituras"><a class="c-btn-secondary" <?= $tab === 'readings' ? 'aria-current="page"' : '' ?> href="<?= eqEscape($url) ?>">Leituras</a><a class="c-btn-secondary" <?= $tab === 'reasons' ? 'aria-current="page"' : '' ?> href="<?= eqEscape($url.'?tab=reasons') ?>">Razoes</a><a class="c-btn-secondary" <?= $tab === 'archives' ? 'aria-current="page"' : '' ?> href="<?= eqEscape($url.'?tab=archives') ?>">Arquivos</a></nav>
<?php if($error !== ''): ?><p class="eq-error" role="alert"><?= eqEscape($error) ?></p><?php endif; ?>
<?php if($view === 'edit'): ?>
<form method="post"><fieldset style="border:0;padding:0;margin:0;min-width:0" <?= $tab === 'readings' && $archived ? 'disabled' : '' ?>>
<?= csrf_field() ?><input type="hidden" name="operation" value="save"><input type="hidden" name="revision" value="<?= (int)($entry['revision'] ?? 0) ?>">
<?php if($tab === 'reasons'): ?>
<section class="eq-section"><h2><?= $id ? 'Editar razao' : 'Adicionar razao' ?></h2><div class="eq-form"><label>Razao<input class="c-input" name="number" inputmode="numeric" pattern="[0-9]{1,20}" maxlength="20" required value="<?= eqEscape($entry['number'] ?? '') ?>"></label><label>Local<input class="c-input" name="location" maxlength="120" required value="<?= eqEscape($entry['location'] ?? '') ?>"></label></div></section>
<?php else: ?>
<section class="eq-section"><h2><?= $id ? 'Editar leitura' : 'Adicionar leitura' ?></h2><div class="eq-form"><label>Data<input class="c-input" type="date" name="work_date" required value="<?= eqEscape($entry['work_date'] ?? $defaultDate) ?>"></label><label>Razao / Local<select class="c-input" name="reason_id" required><option value="">Selecione</option><?php foreach($activeReasons as $reason): ?><option value="<?= (int)$reason['id'] ?>" <?= (int)($entry['reason_id'] ?? 0) === (int)$reason['id'] ? 'selected' : '' ?>><?= eqEscape($reason['number'].' / '.$reason['location'].(!(int)$reason['active'] ? ' (inativa)' : '')) ?></option><?php endforeach; ?></select></label></div>
<?php if(!$activeReasons): ?><p><a class="c-btn-secondary" href="<?= eqEscape($url.'?tab=reasons&view=edit') ?>">Cadastrar razao</a></p><?php endif; ?>
<?php if($id): ?><p class="eq-muted">Local no lancamento: <?= eqEscape($entry['location']) ?></p><?php endif; ?></section>
<section class="eq-section"><h2>Dados recebidos</h2><div class="eq-form"><?php foreach(['total'=>'Total de leituras','lis'=>'LIS-S - com impressao','leit'=>'LIS-N - sem impressao'] as $field=>$label): ?><label><?= $label ?><input class="c-input" type="number" name="<?= $field ?>" min="0" max="1000000" step="1" <?= $field === 'total' ? 'required' : '' ?> value="<?= eqEscape($entry[$field] ?? '') ?>"></label><?php endforeach; ?></div></section>
<section class="eq-section"><h2>Resultado do trabalho</h2><div class="eq-form"><?php foreach(['executed'=>'Executadas','sent'=>'Enviadas'] as $field=>$label): ?><label><?= $label ?><input class="c-input" type="number" name="<?= $field ?>" min="0" max="1000000" step="1" value="<?= eqEscape($entry[$field] ?? '') ?>"></label><?php endforeach; ?><label>Pendentes<output class="c-input" id="eq-pending">-</output></label><label class="eq-wide">Observacoes<textarea class="c-input" name="notes" maxlength="1000" rows="3"><?= eqEscape($entry['notes'] ?? '') ?></textarea></label></div></section>
<?php endif; ?>
<input type="hidden" name="form_complete" value="1"><div class="eq-actions eq-section"><button class="c-btn-secondary" <?= $tab === 'readings' && !$activeReasons ? 'disabled' : '' ?>><?= $tab === 'reasons' ? 'Salvar razao' : 'Salvar leitura' ?></button><a class="c-btn-secondary" href="<?= eqEscape($listUrl) ?>">Cancelar</a></div></fieldset></form>
<?php elseif($tab === 'archives'): ?>
<section class="eq-section"><h2>Meses arquivados</h2><div class="eq-scroll"><table class="c-table"><thead><tr><th>Mes</th><th>Arquivado em</th><th>Consulta</th></tr></thead><tbody><?php $archives=eqArchives($pdo,$owner); foreach($archives as $archive): ?><tr><td><?= date('m/Y',strtotime($archive['work_month'].'-01')) ?></td><td><?= date('d/m/Y H:i',strtotime($archive['archived_at'])) ?></td><td><a class="c-btn-secondary" href="<?= eqEscape($url.'?month='.$archive['work_month']) ?>">Consultar</a></td></tr><?php endforeach; ?><?php if(!$archives): ?><tr><td colspan="3">Nenhum mes arquivado.</td></tr><?php endif; ?></tbody></table></div></section>
<?php elseif($tab === 'reasons'): ?>
<section class="eq-section"><h2>Razoes cadastradas</h2><div class="eq-scroll"><table class="c-table eq-reasons"><thead><tr><th>Razao</th><th>Local</th><th>Situacao</th><th>Lancamentos</th><th>Acoes</th></tr></thead><tbody>
<?php foreach($reasons as $reason): ?><tr><td><?= eqEscape($reason['number']) ?></td><td><?= eqEscape($reason['location']) ?></td><td><?= (int)$reason['active'] ? 'Ativa' : 'Inativa' ?></td><td><?= eqNumber($reason['used_count']) ?></td><td><div class="eq-actions"><a class="c-btn-secondary" title="Editar razao" aria-label="Editar razao <?= eqEscape($reason['number']) ?>" href="<?= eqEscape($listUrl.'&view=edit&id='.$reason['id']) ?>">&#9998;</a>
<?php foreach(['toggle_reason'=>((int)$reason['active'] ? 'Desativar' : 'Reativar'),'delete_reason'=>'Excluir'] as $operation=>$label): if($operation === 'delete_reason' && (int)$reason['used_count'] > 0) continue; ?><form method="post" <?= $operation === 'delete_reason' ? 'data-eq-confirm="Excluir esta razao?"' : '' ?>><?= csrf_field() ?><input type="hidden" name="operation" value="<?= $operation ?>"><input type="hidden" name="id" value="<?= (int)$reason['id'] ?>"><input type="hidden" name="revision" value="<?= (int)$reason['revision'] ?>"><button class="c-btn-secondary" title="<?= $label ?> razao" aria-label="<?= $label ?> razao <?= eqEscape($reason['number']) ?>"><?= $operation === 'delete_reason' ? '&#10005;' : $label ?></button></form><?php endforeach; ?></div></td></tr><?php endforeach; ?>
<?php if(!$reasons): ?><tr><td colspan="5">Nenhuma razao cadastrada.</td></tr><?php endif; ?></tbody></table></div></section>
<?php else: ?>
<form method="get" class="eq-filters"><label>Mes<input class="c-input" type="month" name="month" required value="<?= eqEscape($month) ?>"></label><label>Razao / Local<select class="c-input" name="reason"><option value="">Todas</option><?php foreach($reasons as $reason): ?><option value="<?= (int)$reason['id'] ?>" <?= $filterReason === (int)$reason['id'] ? 'selected' : '' ?>><?= eqEscape($reason['number'].' / '.$reason['location']) ?></option><?php endforeach; ?></select></label><button class="c-btn-secondary">Filtrar</button></form>
<div class="eq-period"><div><strong><?= $archived ? 'Mes arquivado' : ($month === $activeMonth ? 'Mes em andamento' : 'Mes aberto') ?> - <?= $anchor->format('m/Y') ?></strong><p class="eq-muted"><?= eqNumber($periodSummary['days']) ?> lancamentos no mes inteiro / <?= eqNumber($periodSummary['missing_executed']) ?> sem executadas / <?= eqNumber($periodSummary['missing_sent']) ?> sem enviadas</p></div>
<?php $confirmation = $archived ? 'Reabrir '.$anchor->format('m/Y').' para corrigir as leituras?' : 'Arquivar o mes inteiro '.$anchor->format('m/Y').' com '.$periodSummary['days'].' lancamentos, '.$periodSummary['missing_executed'].' sem executadas e '.$periodSummary['missing_sent'].' sem enviadas? O historico sera preservado.'; ?>
<form method="post" data-eq-confirm="<?= eqEscape($confirmation) ?>"><?= csrf_field() ?><input type="hidden" name="operation" value="<?= $archived ? 'reopen_month' : 'archive_month' ?>"><input type="hidden" name="revision" value="<?= (int)$period['revision'] ?>"><button class="c-btn-secondary" <?= !$archived && !$periodSummary['days'] ? 'disabled' : '' ?>><?= $archived ? 'Reabrir mes' : ($month === $activeMonth ? 'Arquivar mes e iniciar proximo' : 'Arquivar mes') ?></button></form></div>
<section class="eq-section"><h2>Leituras - <?= $anchor->format('m/Y') ?></h2><?php eqReadingTable($data,$listUrl,$archived); ?></section>
<?php eqReport($data); ?>
<?php endif; ?></div></div>
<script>
(() => {
    const total = document.querySelector('[name=total]'), executed = document.querySelector('[name=executed]'), pending = document.getElementById('eq-pending');
    if (!total || !executed || !pending) return;
    const update = () => { pending.textContent = total.value !== '' && executed.value !== '' && total.checkValidity() && executed.checkValidity() && +total.value >= +executed.value ? (+total.value - +executed.value).toLocaleString('pt-BR') : '-'; };
    total.addEventListener('input',update); executed.addEventListener('input',update); update();
})();
</script>
<?php require __DIR__.'/ui.php'; $content = ob_get_clean(); require APP_PATH.'/views/layout_admin.php'; ?>
