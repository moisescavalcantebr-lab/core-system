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
$providers = $mailService->providers();
$projects = $mailService->projects();
$edit = null;
try {
    if (isset($_GET['edit'])) { $edit = $mailService->find(mailCenterId($_GET['edit'])); }
} catch (InvalidArgumentException|OutOfBoundsException $e) { http_response_code(404); exit('Registro não encontrado.'); }
$title = 'Contas e funções';
$mailTab = 'accounts';
ob_start();
?>
<div class="c-page mail-center">
    <div class="c-page-header"><div><h1 class="c-page-title">Contas e funções</h1><p class="c-page-subtitle">Uma finalidade para cada endereço, sem misturar identidades</p></div></div>
    <?php require __DIR__ . '/_nav.php'; ?>
    <div class="mail-notice"><strong>Organização e conexão</strong><p>Cadastre suas caixas sem informar senhas. Gmail e Zoho usam autorização diretamente no provedor, após configuração privada. Outlook permanece em preparação.</p></div>
    <section class="c-card" id="mail-account-form" aria-labelledby="mail-account-heading">
        <h2 id="mail-account-heading"><?= $edit ? 'Editar conta' : 'Adicionar conta' ?></h2>
        <form method="post" action="/app/actions/central_contas/account_save.php" class="mail-form">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>"><input type="hidden" name="revision" value="<?= (int)($edit['revision'] ?? 0) ?>">
            <div class="mail-form-grid">
                <label>Nome da conta<input name="label" value="<?= mailCenterEscape($edit['label'] ?? '') ?>" required maxlength="120" placeholder="Ex.: Gmail — cadastros"></label>
                <label>Provedor<select name="provider_id" required><option value="">Selecione</option><?php foreach ($providers as $provider): ?><option value="<?= (int)$provider['id'] ?>" <?= (int)($edit['provider_id'] ?? 0) === (int)$provider['id'] ? 'selected' : '' ?>><?= mailCenterEscape($provider['name']) ?></option><?php endforeach; ?></select></label>
                <label>Endereço de e-mail<input type="email" name="email" value="<?= mailCenterEscape($edit['email'] ?? '') ?>" required maxlength="254" autocomplete="off" placeholder="voce@exemplo.com"></label>
                <label>Função do e-mail<select name="category_id"><option value="0">Sem função definida</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)($edit['category_id'] ?? 0) === (int)$category['id'] ? 'selected' : '' ?>><?= mailCenterEscape($category['name']) ?></option><?php endforeach; ?></select></label>
                <label>Projeto vinculado<select name="project_id"><option value="0">Sem vínculo</option><?php foreach ($projects as $project): ?><option value="<?= (int)$project['id'] ?>" <?= (int)($edit['project_id'] ?? 0) === (int)$project['id'] ? 'selected' : '' ?>><?= mailCenterEscape($project['name']) ?></option><?php endforeach; ?></select></label>
                <label>Ordem de exibição<input type="number" name="display_order" min="0" max="1000000000" value="<?= (int)($edit['display_order'] ?? 0) ?>"></label>
                <label class="mail-form-full">Observações de uso<input name="purpose" maxlength="500" value="<?= mailCenterEscape($edit['purpose'] ?? '') ?>" placeholder="Ex.: Cadastros em plataformas e sites. Não informe senhas ou códigos."></label>
            </div>
            <div class="mail-actions"><button type="submit" class="c-btn"><?= $edit ? 'Salvar alterações' : 'Cadastrar conta' ?></button><?php if ($edit): ?><a class="c-btn-secondary" href="/web/admin/central_contas/accounts.php">Cancelar edição</a><?php endif; ?></div>
        </form>
    </section>
    <section aria-labelledby="mail-list-heading"><h2 id="mail-list-heading">Suas contas</h2>
        <?php if (!$accounts): ?><div class="c-card"><p>Nenhuma conta cadastrada.</p></div><?php endif; ?>
        <div class="mail-account-grid">
        <?php foreach ($accounts as $account): ?>
            <article class="c-card mail-account-card mail-account-tinted" style="--mail-account-color:<?= mailCenterAccountColor($account['category_color']??null) ?>">
                <div class="mail-card-heading"><span class="mail-provider"><?= mailCenterEscape($account['provider_name']) ?></span><span class="mail-status"><?= (int)$account['is_active'] ? 'Cadastro ativo' : 'Cadastro desativado' ?></span></div>
                <h3><?= mailCenterEscape($account['label']) ?></h3><p class="mail-address"><?= mailCenterEscape($account['email']) ?></p><p><?= mailCenterEscape($account['purpose'] ?: 'Sem finalidade informada.') ?></p>
                <dl><div><dt>Função do e-mail</dt><dd><?= mailCenterEscape($account['category_name'] ?: 'Sem função definida') ?></dd></div><div><dt>Projeto</dt><dd><?= mailCenterEscape($account['project_name'] ?: 'Sem vínculo') ?></dd></div><div><dt>Conexão</dt><dd><?= mailCenterEscape(match($account['connection_status']){'connected'=>'Conectada','reauth_required'=>'Reconectar',default=>'Não conectada'}) ?></dd></div></dl>
                <?php require __DIR__ . '/_connection.php'; ?>
                <div class="mail-actions"><a class="c-btn-secondary" href="?edit=<?= (int)$account['id'] ?>#mail-account-form">Editar</a>
                <form method="post" action="/app/actions/central_contas/account_status.php"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$account['id'] ?>"><input type="hidden" name="revision" value="<?= (int)$account['revision'] ?>"><input type="hidden" name="is_active" value="<?= (int)$account['is_active'] ? 0 : 1 ?>"><button class="c-btn-secondary" type="submit"><?= (int)$account['is_active'] ? 'Desativar cadastro' : 'Ativar cadastro' ?></button></form></div>
            </article>
        <?php endforeach; ?>
        </div>
    </section>
    <section class="c-card" aria-labelledby="mail-category-heading">
        <h2 id="mail-category-heading">Funções do e-mail</h2><p>Defina o uso do e-mail e a cor que identifica suas mensagens: cadastros, trabalho, pessoal ou recuperação.</p>
        <?php foreach ($categories as $category): ?>
        <form class="mail-category-row" method="post" action="/app/actions/central_contas/category_save.php"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$category['id'] ?>"><input type="hidden" name="revision" value="<?= (int)$category['revision'] ?>"><label>Nome<input name="name" maxlength="80" required value="<?= mailCenterEscape($category['name']) ?>"></label><label>Cor de identificação<input type="color" name="color" value="<?= mailCenterEscape($category['color']) ?>"></label><label>Ordem<input type="number" name="display_order" min="0" max="1000000000" value="<?= (int)$category['display_order'] ?>"></label><button type="submit" class="c-btn-secondary">Salvar função</button></form>
        <?php endforeach; ?>
        <form class="mail-category-row" method="post" action="/app/actions/central_contas/category_save.php"><?= csrf_field() ?><input type="hidden" name="id" value="0"><input type="hidden" name="revision" value="0"><label>Nova função<input name="name" maxlength="80" required placeholder="Ex.: Cadastros"></label><label>Cor de identificação<input type="color" name="color" value="#2563eb"></label><label>Ordem<input type="number" name="display_order" min="0" max="1000000000" value="0"></label><button type="submit" class="c-btn">Adicionar função</button></form>
    </section>
    <?php if(mailCenterOrganizationReady($pdo)): mailPilotLoadCode();$mailPreferences=(new MailLocalOrganizationService($pdo,$mailOwner))->preferences(); ?>
    <section class="c-card" id="mail-local-preferences"><h2>Leitura e destaque</h2><form method="post" action="/app/actions/central_contas/preferences_save.php" class="mail-actions"><?= csrf_field() ?><label>Cor das mensagens sinalizadas<input type="color" name="highlight_color" value="<?= mailCenterAccountColor($mailPreferences['highlight_color']) ?>"></label><label>Abrir mensagens nas caixas individuais<select name="reading_mode"><option value="panel" <?= $mailPreferences['reading_mode']==='panel'?'selected':null ?>>No painel, ao lado da lista</option><option value="full" <?= $mailPreferences['reading_mode']==='full'?'selected':null ?>>Na página completa</option></select></label><button class="c-btn-secondary">Salvar preferências</button></form><p>Favoritos, sinalização e fixação ficam apenas na Central. A faixa da conta mantém sua própria cor.</p></section>
    <?php endif; ?>
</div>
<?php $content = ob_get_clean(); require APP_PATH . '/views/layout_admin.php'; ?>



