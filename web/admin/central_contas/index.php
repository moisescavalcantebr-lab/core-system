<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require_once APP_PATH . '/helpers/mail_pilot.php';
$mailOwner = mailCenterRequireOwner($pdo, $config);
$pilotReady = mailPilotReady($pdo, $config);
if (!mailCenterReady($pdo)) { http_response_code(503); exit('Central aguardando instalação pelo responsável técnico.'); }
$mailService = new MailAccountService($pdo, $mailOwner);
$accounts = $mailService->accounts();
$categories = $mailService->categories();
$active = count(array_filter($accounts, static fn(array $row): bool => (int)$row['is_active'] === 1));
$mailTab = 'overview';
$title = 'Central de E-mail';
ob_start();
?>
<div class="c-page mail-center">
    <div class="c-page-header"><div><h1 class="c-page-title">Central de E-mail</h1><p class="c-page-subtitle">Suas caixas de e-mail, organizadas em um só lugar</p></div><div class="c-page-actions"><a class="c-btn" href="/web/admin/central_contas/accounts.php#mail-account-form">Adicionar conta</a></div></div>
    <?php require __DIR__ . '/_nav.php'; ?>
    <div class="mail-notice" role="note"><strong>Organização e conexão das contas</strong><p>Gmail e Zoho têm conexão por autorização do provedor, após configuração privada. A caixa unificada reúne mensagens já sincronizadas, sempre com a conta de origem. Outlook permanece em preparação.</p></div>
    <div class="mail-metrics">
        <div class="c-card"><span>Contas cadastradas</span><strong><?= count($accounts) ?></strong></div>
        <div class="c-card"><span>Cadastros ativos</span><strong><?= $active ?></strong></div>
        <div class="c-card"><span>Funções do e-mail</span><strong><?= count($categories) ?></strong></div>
    </div>
    <?php if (!$accounts): ?>
        <div class="c-card mail-empty"><h2>Comece pelas suas caixas</h2><p>Defina um nome e uma finalidade para cada e-mail. Suas senhas continuam no seu gerenciador externo.</p><a href="/web/admin/central_contas/accounts.php#mail-account-form" class="c-btn">Cadastrar primeira conta</a></div>
    <?php else: ?>
        <div class="mail-account-grid">
        <?php foreach ($accounts as $account): ?>
            <article class="c-card mail-account-card mail-account-tinted" style="--mail-account-color:<?= mailCenterAccountColor($account['category_color']??null) ?>">
                <div class="mail-card-heading"><span class="mail-provider"><?= mailCenterEscape($account['provider_name']) ?></span><span class="mail-status"><?= (int)$account['is_active'] ? 'Cadastro ativo' : 'Cadastro desativado' ?></span></div>
                <h2><?= mailCenterEscape($account['label']) ?></h2><p class="mail-address"><?= mailCenterEscape($account['email']) ?></p>
                <p><?= mailCenterEscape($account['purpose'] ?: 'Finalidade ainda não informada.') ?></p>
                <dl><div><dt>Função do e-mail</dt><dd><?= mailCenterEscape($account['category_name'] ?: 'Sem função definida') ?></dd></div><div><dt>Projeto</dt><dd><?= mailCenterEscape($account['project_name'] ?: 'Sem vínculo') ?></dd></div><div><dt>Conexão</dt><dd><?= mailCenterEscape(match($account['connection_status']){'connected'=>'Conectada','reauth_required'=>'Reconectar',default=>'Não conectada'}) ?></dd></div></dl>
                <?php require __DIR__ . '/_connection.php'; ?>
                <a class="c-btn-secondary" href="/web/admin/central_contas/accounts.php?edit=<?= (int)$account['id'] ?>#mail-account-form">Editar conta</a>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php $content = ob_get_clean(); require APP_PATH . '/views/layout_admin.php'; ?>
