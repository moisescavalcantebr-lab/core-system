<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap/bootstrap.php';

$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($_GET['slug'] ?? $_GET['base'] ?? '')));

if ($slug === '') {
    http_response_code(404);
    exit('Base nao encontrada.');
}

$stmt = $pdo->prepare("
    SELECT id, slug, name, description, showcase_title, showcase_summary,
           showcase_cover_image, showcase_banner_image, showcase_detail_url,
           showcase_cta_text
    FROM bases
    WHERE slug = :slug
      AND status = 1
      AND slug != 'base'
      AND base_stage = 'published'
      AND showcase_status = 1
    LIMIT 1
");
$stmt->execute(['slug' => $slug]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    http_response_code(404);
    exit('Base nao encontrada.');
}

$appName = $coreSettings['app_name'] ?? 'Meu Projeto Web';
$title = trim((string)($base['showcase_title'] ?? '')) ?: (string)$base['name'];
$summary = trim((string)($base['showcase_summary'] ?? '')) ?: trim((string)($base['description'] ?? ''));
$detailUrl = trim((string)($base['showcase_detail_url'] ?? ''));
$ctaText = trim((string)($base['showcase_cta_text'] ?? '')) ?: 'Quero saber mais';
$bannerImage = trim((string)($base['showcase_banner_image'] ?: $base['showcase_cover_image'] ?: ''));
$bannerUrl = $bannerImage !== '' ? '/web/assets/uploads/' . str_replace('%2F', '/', rawurlencode($bannerImage)) : '';
$favicon = trim((string)($coreSettings['app_favicon'] ?? ''));
$faviconUrl = $favicon !== '' ? '/web/assets/uploads/' . rawurlencode($favicon) : '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> | <?= htmlspecialchars($appName) ?></title>
    <?php if ($faviconUrl !== ''): ?>
        <link rel="icon" href="<?= htmlspecialchars($faviconUrl) ?>">
    <?php endif; ?>
    <style>
        :root {
            color-scheme: dark;
            --bg: #050817;
            --panel: #111b2e;
            --line: #31415b;
            --text: #f5f7fb;
            --muted: #b7c1d4;
            --brand: #3b82f6;
            --brand-2: #21c37b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(180deg, #050817 0%, #080c1c 100%);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
        }
        a { color: inherit; }
        .base-page {
            width: min(1180px, calc(100% - 40px));
            min-height: 100vh;
            margin: 0 auto;
            display: grid;
            align-content: center;
            padding: 28px 0;
        }
        .base-shell {
            position: relative;
            overflow: hidden;
            min-height: 560px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background:
                linear-gradient(90deg, rgba(5, 8, 23, .96), rgba(5, 8, 23, .74) 50%, rgba(5, 8, 23, .28)),
                linear-gradient(135deg, rgba(33, 195, 123, .74), rgba(59, 130, 246, .78));
            background-size: cover;
            background-position: center;
        }
        .base-shell::after {
            content: "";
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(90deg, transparent 0 28px, rgba(255, 255, 255, .04) 28px 29px);
            pointer-events: none;
        }
        .base-content {
            position: relative;
            z-index: 1;
            min-height: 560px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 390px;
            gap: 34px;
            align-items: center;
            padding: 48px;
        }
        .kicker {
            color: var(--brand-2);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        h1 {
            max-width: 760px;
            margin: 14px 0 14px;
            font-size: clamp(42px, 7vw, 76px);
            line-height: .96;
            letter-spacing: 0;
        }
        .summary {
            max-width: 680px;
            margin: 0;
            color: var(--muted);
            font-size: 19px;
            line-height: 1.55;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 28px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: rgba(17, 27, 46, .86);
            color: var(--text);
            font-weight: 800;
            text-decoration: none;
        }
        .btn-primary {
            border-color: transparent;
            background: var(--brand);
            color: #fff;
        }
        .lead-card {
            display: grid;
            gap: 16px;
            padding: 24px;
            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: 10px;
            background: rgba(10, 16, 31, .84);
        }
        .lead-card h2 {
            margin: 0;
            font-size: 26px;
            line-height: 1.1;
        }
        .lead-card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.45;
        }
        .lead-card form {
            display: grid;
            gap: 12px;
        }
        .lead-card input {
            width: 100%;
            min-height: 46px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #070b19;
            color: var(--text);
            padding: 0 13px;
            font: inherit;
        }
        .lead-card button {
            min-height: 46px;
            border: 0;
            border-radius: 8px;
            background: var(--brand);
            color: #fff;
            font-weight: 900;
            cursor: pointer;
        }
        .top-link {
            position: absolute;
            top: 22px;
            left: 22px;
            z-index: 2;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }
        @media (max-width: 860px) {
            .base-content {
                grid-template-columns: 1fr;
                padding: 32px;
            }
            .lead-card {
                max-width: 520px;
            }
        }
        @media (max-width: 560px) {
            .base-page {
                width: min(100% - 28px, 1180px);
                align-content: start;
            }
            .base-shell,
            .base-content {
                min-height: auto;
            }
            h1 {
                font-size: 40px;
            }
            .summary {
                font-size: 16px;
            }
            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <main class="base-page">
        <section class="base-shell" <?= $bannerUrl !== '' ? 'style="background-image: linear-gradient(90deg, rgba(5, 8, 23, .96), rgba(5, 8, 23, .74) 50%, rgba(5, 8, 23, .28)), url(\'' . htmlspecialchars($bannerUrl, ENT_QUOTES) . '\')"' : '' ?>>
            <a class="top-link" href="/web/">Voltar para bases</a>
            <div class="base-content">
                <div>
                    <div class="kicker">Base pronta</div>
                    <h1><?= htmlspecialchars($title) ?></h1>
                    <?php if ($summary !== ''): ?>
                        <p class="summary"><?= htmlspecialchars($summary) ?></p>
                    <?php endif; ?>
                    <div class="actions">
                        <?php if ($detailUrl !== ''): ?>
                            <a class="btn btn-primary" href="<?= htmlspecialchars($detailUrl) ?>"><?= htmlspecialchars($ctaText) ?></a>
                        <?php endif; ?>
                        <a class="btn" href="/web/bases.php">Ver outras bases</a>
                    </div>
                </div>

                <aside class="lead-card" id="lead">
                    <h2>Vamos criar o projeto</h2>
                    <p>Informe seu e-mail para receber o link de continuacao.</p>
                    <form method="post" action="/web/lead_submit.php">
                        <input type="hidden" name="_lead_form" value="1">
                        <input type="hidden" name="base_id" value="<?= (int)$base['id'] ?>">
                        <input type="email" name="email" placeholder="Seu e-mail" maxlength="150" autocomplete="email" required>
                        <button type="submit">Receber link</button>
                    </form>
                </aside>
            </div>
        </section>
    </main>
</body>
</html>
