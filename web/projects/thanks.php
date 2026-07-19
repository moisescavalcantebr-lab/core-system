<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap/bootstrap.php';

$title = 'Solicitação Enviada';
$status = (string)($_GET['status'] ?? '');
$projectSlug = preg_replace('/[^a-z0-9-]/', '', strtolower((string)($_GET['project'] ?? '')));

$heading = 'Solicitação enviada';
$message = 'Recebemos os dados do projeto. Nossa equipe vai revisar e preparar a criação no painel. O link de acesso será enviado para o e-mail cadastrado.';
$buttonLabel = null;
$buttonHref = null;

if ($status === 'project_exists' && $projectSlug !== '') {
    $heading = 'Projeto já cadastrado';
    $message = 'Já existe um projeto vinculado a este e-mail. Se você ainda não criou a senha, confira o e-mail cadastrado e abra o link enviado para continuar.';
    $buttonLabel = 'Acessar projeto';
    $buttonHref = '/projects/' . $projectSlug . '/web/admin/login.php';
}

if ($status === 'lead_exists') {
    $heading = 'Cadastro já iniciado';
    $message = 'Já existe um cadastro iniciado com este e-mail para esta base. Confira o e-mail cadastrado para continuar a criação do projeto. Veja também a caixa de spam.';
}

if ($status === 'created' && $projectSlug !== '') {
    $heading = 'Projeto criado';
    $message = 'Cadastro concluído. Para continuar, confira o e-mail cadastrado e abra o link enviado para criar sua senha. Veja também a caixa de spam.';
}

if ($status === 'created_email_pending' && $projectSlug !== '') {
    $heading = 'Projeto criado';
    $message = 'Seu projeto foi criado. Não conseguimos confirmar o envio do e-mail agora, então nossa equipe recebeu o registro para acompanhar o acesso.';
}

$extraCss = '
<link rel="stylesheet" href="/web/assets/css/core_page.css">
';

ob_start();
?>

<main class="c-page project-request-page">
    <section class="block block-lead">
        <div class="c-container text-center">
            <h1 class="block-title"><?= htmlspecialchars($heading) ?></h1>
            <p class="block-description">
                <?= htmlspecialchars($message) ?>
            </p>

            <?php if ($buttonLabel !== null && $buttonHref !== null): ?>
                <a class="btn btn-primary" href="<?= htmlspecialchars($buttonHref) ?>">
                    <?= htmlspecialchars($buttonLabel) ?>
                </a>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_base.php';
