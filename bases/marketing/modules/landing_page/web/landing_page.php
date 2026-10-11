<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap/project_bootstrap.php';
require PROJECT_PATH . '/modules/landing_page/web/admin/landing_page/helpers.php';

landingPageEnsureSchema($pdo);

$slug = landingPageSlug((string)($_GET['slug'] ?? ''));
$preview = isset($_GET['preview']) && projectUserRole() === 'ADMIN';
$stmt = $pdo->prepare('SELECT * FROM landing_page_pages WHERE slug = ?' . ($preview ? '' : " AND status = 'published'") . ' LIMIT 1');
$stmt->execute([$slug]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    http_response_code(404);
    echo 'Pagina nao encontrada.';
    exit;
}

$projectName = function_exists('getSetting') ? getSetting('project_name', 'Meu Projeto') : 'Meu Projeto';
$logo = function_exists('getSetting') ? getSetting('project_logo', '') : '';
$theme = (string)($page['theme'] ?? 'dark');
$formTexts = landingPageFormTexts((string)($page['form_language'] ?? 'pt'));
$leadFields = landingPageLeadFields($page);
$success = isset($_GET['lead']) && $_GET['lead'] === 'success';
$successMessage = trim((string)($page['success_message'] ?? ''));
if ($successMessage === '') {
    $successMessage = $formTexts['success'];
}
$offerImage = trim((string)($page['offer_image'] ?? ''));
$banners = landingPageBanners($page, true);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string)$page['title']) ?></title>
    <style>
        :root {
            --bg: #050816;
            --panel: #111827;
            --line: #314158;
            --text: #f8fafc;
            --muted: #aab6ca;
            --accent: #3b82f6;
            --success: #22c55e;
        }
        body.theme-clean {
            --bg: #f7fafc;
            --panel: #ffffff;
            --line: #d7dee9;
            --text: #0f172a;
            --muted: #475569;
            --accent: #2563eb;
        }
        body.theme-contrast {
            --bg: #09090b;
            --panel: #18181b;
            --line: #3f3f46;
            --text: #fafafa;
            --muted: #d4d4d8;
            --accent: #22c55e;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: var(--bg); color: var(--text); }
        a { color: inherit; }
        .hero { max-width:1100px; margin:0 auto; padding:32px 24px; }
        .page-intro { grid-area:heading; min-width:0; }
        .page-copy { grid-area:body; min-width:0; }
        .capture-column { grid-area:capture; display:grid; gap:20px; width:100%; max-width:420px; justify-self:end; }
        .primary-image { width:100%; height:auto; aspect-ratio:16/9; object-fit:contain; border-radius:8px; }
        .page-content { display:grid; grid-template-columns:minmax(0, 1fr) minmax(310px, .78fr); grid-template-areas:"heading capture" "body capture"; grid-template-rows:auto 1fr; gap:24px 28px; align-items:start; }
        h1 { font-size:40px; line-height:1.12; margin:0 0 14px; letter-spacing:0; overflow-wrap:anywhere; }
        .subtitle { color:var(--muted); font-size:17px; max-width:640px; line-height:1.48; }
        .body { color:var(--muted); max-width:620px; line-height:1.52; white-space:pre-line; }
        .banners { margin-top:28px; padding-top:20px; border-top:1px solid var(--line); }
        .banners h2 { font-size:16px; margin:0 0 12px; }
        .banner-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; }
        .banner { border:1px solid var(--line); border-radius:8px; overflow:hidden; background:var(--panel); display:flex; align-items:center; justify-content:center; aspect-ratio:16/9; text-decoration:none; }
        .banner img { width:100%; height:100%; display:block; object-fit:contain; }
        .banner span { padding:16px; overflow-wrap:anywhere; text-align:center; }
        .banner:hover { border-color:var(--accent); }
        .banner:focus-visible { outline:2px solid var(--accent); outline-offset:3px; }
        .lead-card { background:var(--panel); border:1px solid var(--line); padding:24px; border-radius:8px; max-width:420px; width:100%; justify-self:end; }
        .lead-card h2 { margin:0 0 8px; }
        .lead-card p { color:var(--muted); margin-top:0; }
        label { display:grid; gap:6px; margin:12px 0; color:var(--muted); }
        input, textarea { width:100%; border:1px solid var(--line); background:var(--bg); color:var(--text); border-radius:8px; padding:11px 13px; font:inherit; }
        button, .cta { border:0; border-radius:8px; padding:12px 18px; font-weight:700; background:var(--accent); color:#fff; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; }
        button { width:100%; margin-top:10px; }
        .success { border:1px solid rgba(34,197,94,.5); background:rgba(34,197,94,.12); padding:12px; border-radius:8px; color:var(--text); margin-bottom:12px; }
        .form-error { border:1px solid #ef4444; padding:12px; border-radius:8px; margin-bottom:12px; }
        @media (max-width: 860px) {
            .hero { padding:24px 18px; }
            .capture-column { display:contents; }
            .primary-image { grid-area:image; max-width:420px; justify-self:center; }
            h1 { font-size:32px; }
            .page-content { grid-template-columns:1fr; grid-template-areas:"heading" "body" "form"; grid-template-rows:auto; }
            .page-content.has-primary-image { grid-template-areas:"heading" "image" "body" "form"; }
            .lead-card { grid-area:form; justify-self:stretch; max-width:none; }
            .banner-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); }
            .banner span { font-size:13px; padding:10px; }
        }
    </style>
</head>
<body class="theme-<?= htmlspecialchars($theme) ?>">
    <main class="hero">
      <div class="page-content <?= $offerImage !== '' ? 'has-primary-image' : '' ?>">
        <header class="page-intro">
            <h1><?= htmlspecialchars((string)$page['headline']) ?></h1>
            <?php if (!empty($page['subtitle'])): ?>
                <div class="subtitle"><?= nl2br(htmlspecialchars((string)$page['subtitle'])) ?></div>
            <?php endif; ?>
        </header>
        <section class="page-copy">
            <?php if (!empty($page['body'])): ?>
                <div class="body"><?= htmlspecialchars((string)$page['body']) ?></div>
            <?php endif; ?>
        </section>

        <div class="capture-column">
          <?php if ($offerImage !== ''): ?>
            <img class="primary-image" src="<?= PROJECT_URL ?>/<?= htmlspecialchars(ltrim($offerImage, '/')) ?>" alt="<?= htmlspecialchars((string)$page['title']) ?>">
          <?php endif; ?>
        <aside class="lead-card">
            <h2><?= htmlspecialchars(trim((string)$page['cta_text']) !== '' ? (string)$page['cta_text'] : $formTexts['title']) ?></h2>
            <p><?= htmlspecialchars($formTexts['description']) ?></p>
            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['lead']) && $_GET['lead'] === 'invalid'): ?>
                <div class="form-error" role="alert"><?= htmlspecialchars($formTexts['invalid']) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= PROJECT_URL ?>/landing_page-lead.php">
                <?= csrf_field() ?>
                <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">
                <input type="hidden" name="slug" value="<?= htmlspecialchars((string)$page['slug']) ?>">
                <?php foreach ($leadFields as $field => $mode): ?>
                    <?php if ($mode === 'hidden') continue; ?>
                    <label>
                        <?= htmlspecialchars($formTexts[$field]) ?><?= $mode === 'optional' ? ' ('.htmlspecialchars($formTexts['optional']).')' : '' ?>
                        <input type="<?= $field === 'email' ? 'email' : ($field === 'phone' ? 'tel' : 'text') ?>" name="<?= $field ?>" autocomplete="<?= $field === 'phone' ? 'tel' : $field ?>" maxlength="<?= ['name' => 140, 'email' => 180, 'phone' => 40][$field] ?>" <?= $mode === 'required' ? 'required' : '' ?>>
                    </label>
                <?php endforeach; ?>
                <button type="submit"><?= htmlspecialchars(trim((string)$page['cta_text']) !== '' ? (string)$page['cta_text'] : $formTexts['title']) ?></button>
            </form>
        </aside>
        </div>
      </div>
        <?php if ($banners !== []): ?>
            <section class="banners" aria-label="Divulgacao complementar">
                <h2>Confira tambem</h2>
                <div class="banner-grid">
                    <?php foreach ($banners as $banner): ?>
                        <a class="banner" href="<?= htmlspecialchars($banner['url']) ?>" rel="noopener">
                            <?php if ($banner['image'] !== ''): ?>
                                <img src="<?= PROJECT_URL ?>/<?= htmlspecialchars(ltrim($banner['image'], '/')) ?>" alt="<?= htmlspecialchars($banner['title'] !== '' ? $banner['title'] : 'Abrir pagina') ?>" loading="lazy">
                            <?php else: ?>
                                <span><?= htmlspecialchars($banner['title'] !== '' ? $banner['title'] : $banner['url']) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
