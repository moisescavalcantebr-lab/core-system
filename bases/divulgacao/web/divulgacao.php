<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap/project_bootstrap.php';
require PROJECT_PATH . '/modules/divulgacao/web/admin/divulgacao/helpers.php';

divulgacaoEnsureSchema($pdo);

$slug = divulgacaoSlug((string)($_GET['slug'] ?? ''));
$preview = isset($_GET['preview']) && projectUserRole() === 'ADMIN';
$stmt = $pdo->prepare('SELECT * FROM divulgacao_pages WHERE slug = ?' . ($preview ? '' : " AND status = 'published'") . ' LIMIT 1');
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
$formTexts = divulgacaoFormTexts((string)($page['form_language'] ?? 'pt'));
$success = isset($_GET['lead']) && $_GET['lead'] === 'success';
$successMessage = trim((string)($page['success_message'] ?? ''));
if ($successMessage === '') {
    $successMessage = $formTexts['success'];
}
$offerImage = trim((string)($page['offer_image'] ?? ''));
$offerImage2 = trim((string)($page['offer_image_2'] ?? ''));
$offerImages = array_values(array_filter([$offerImage, $offerImage2], static fn (string $image): bool => $image !== ''));
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
        .hero { display:grid; grid-template-columns:minmax(0, 1fr) minmax(310px, .78fr); gap:28px; align-items:center; min-height:100vh; max-width:1100px; margin:0 auto; padding:28px clamp(18px, 4.5vw, 54px); }
        h1 { font-size: clamp(32px, 4.8vw, 56px); line-height: 1; margin:0 0 14px; letter-spacing:0; max-width:660px; }
        .subtitle { color:var(--muted); font-size: clamp(15px, 1.45vw, 18px); max-width:640px; line-height:1.48; }
        .body { margin-top:18px; color:var(--muted); max-width:620px; line-height:1.52; white-space:pre-line; }
        .offer-images { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; margin-top:22px; max-width:580px; }
        .offer-images.is-single { grid-template-columns:minmax(0, .78fr); }
        .offer-image { border:1px solid var(--line); border-radius:10px; overflow:hidden; background:var(--panel); }
        .offer-image img { width:100%; aspect-ratio:16/9; max-height:220px; display:block; object-fit:cover; }
        .lead-card { background:var(--panel); border:1px solid var(--line); padding:24px; border-radius:8px; max-width:420px; width:100%; justify-self:end; }
        .lead-card h2 { margin:0 0 8px; }
        .lead-card p { color:var(--muted); margin-top:0; }
        label { display:grid; gap:6px; margin:12px 0; color:var(--muted); }
        input, textarea { width:100%; border:1px solid var(--line); background:var(--bg); color:var(--text); border-radius:8px; padding:11px 13px; font:inherit; }
        button, .cta { border:0; border-radius:8px; padding:12px 18px; font-weight:700; background:var(--accent); color:#fff; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; }
        button { width:100%; margin-top:10px; }
        .success { border:1px solid rgba(34,197,94,.5); background:rgba(34,197,94,.12); padding:12px; border-radius:8px; color:var(--text); margin-bottom:12px; }
        @media (max-width: 860px) {
            .hero { grid-template-columns:1fr; min-height:auto; padding-top:30px; }
            .lead-card { justify-self:stretch; max-width:none; }
            .offer-images, .offer-images.is-single { grid-template-columns:1fr; max-width:none; }
            .offer-image img { max-height:260px; }
        }
    </style>
</head>
<body class="theme-<?= htmlspecialchars($theme) ?>">
    <main class="hero">
        <section>
            <h1><?= htmlspecialchars((string)$page['headline']) ?></h1>
            <?php if (!empty($page['subtitle'])): ?>
                <div class="subtitle"><?= nl2br(htmlspecialchars((string)$page['subtitle'])) ?></div>
            <?php endif; ?>
            <?php if (!empty($page['body'])): ?>
                <div class="body"><?= htmlspecialchars((string)$page['body']) ?></div>
            <?php endif; ?>
            <?php if ($offerImages !== []): ?>
                <div class="offer-images <?= count($offerImages) === 1 ? 'is-single' : '' ?>">
                    <?php foreach ($offerImages as $image): ?>
                        <div class="offer-image">
                            <img src="<?= PROJECT_URL ?>/<?= htmlspecialchars(ltrim($image, '/')) ?>" alt="">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <aside class="lead-card">
            <h2><?= htmlspecialchars(trim((string)$page['cta_text']) !== '' ? (string)$page['cta_text'] : $formTexts['title']) ?></h2>
            <p><?= htmlspecialchars($formTexts['description']) ?></p>
            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= PROJECT_URL ?>/divulgacao-lead.php">
                <?= csrf_field() ?>
                <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">
                <input type="hidden" name="slug" value="<?= htmlspecialchars((string)$page['slug']) ?>">
                <label><?= htmlspecialchars($formTexts['name']) ?><input name="name" required></label>
                <label><?= htmlspecialchars($formTexts['email']) ?><input type="email" name="email" required></label>
                <label><?= htmlspecialchars($formTexts['phone']) ?><input name="phone" inputmode="tel"></label>
                <button type="submit"><?= htmlspecialchars(trim((string)$page['cta_text']) !== '' ? (string)$page['cta_text'] : $formTexts['title']) ?></button>
            </form>
        </aside>
    </main>
</body>
</html>
