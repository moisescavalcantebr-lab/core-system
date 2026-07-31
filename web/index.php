<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap/bootstrap.php';

$stmt = $pdo->query("
    SELECT b.id, b.slug, b.name, b.description, b.showcase_title, b.showcase_summary,
           showcase_cover_image, showcase_banner_image, showcase_featured,
           showcase_order, showcase_status,
           (SELECT COUNT(*) FROM projects p2 WHERE p2.base_id = b.id AND p2.status != 'deleted') AS total_projects,
           (SELECT COUNT(*) FROM leads l WHERE l.base_id = b.id) AS total_leads,
           (
               SELECT p.slug
               FROM core_page_contents p
               WHERE p.area = 'public'
                 AND p.status = 'published'
                 AND p.type = 'page'
                 AND (
                     p.slug = b.slug
                     OR p.slug LIKE CONCAT('%', b.slug, '%')
                     OR LOWER(p.title) LIKE CONCAT('%', REPLACE(b.slug, '-', ' '), '%')
                 )
               ORDER BY
                   CASE
                       WHEN p.slug = b.slug THEN 0
                       WHEN p.slug LIKE CONCAT('%', b.slug, '%') THEN 1
                       ELSE 2
                   END,
                   p.id ASC
               LIMIT 1
           ) AS landing_slug
    FROM bases b
    WHERE b.status = 1
      AND b.slug != 'base'
      AND b.base_stage = 'published'
      AND b.showcase_status = 1
    ORDER BY b.showcase_order ASC, b.name ASC
");

$bases = $stmt->fetchAll(PDO::FETCH_ASSOC);

$featuredBases = [];
foreach ($bases as $base) {
    if ((int)($base['showcase_featured'] ?? 0) === 1) {
        $featuredBases[] = $base;
    }
}

if (count($featuredBases) < 2) {
    $featuredBases = array_slice($bases, 0, 5);
}

$demandBases = $bases;
usort($demandBases, static function (array $a, array $b): int {
    $demandA = (int)($a['total_projects'] ?? 0) + (int)($a['total_leads'] ?? 0);
    $demandB = (int)($b['total_projects'] ?? 0) + (int)($b['total_leads'] ?? 0);

    if ($demandA !== $demandB) {
        return $demandB <=> $demandA;
    }

    $orderA = (int)($a['showcase_order'] ?? 0);
    $orderB = (int)($b['showcase_order'] ?? 0);

    if ($orderA !== $orderB) {
        return $orderA <=> $orderB;
    }

    return strcasecmp((string)$a['name'], (string)$b['name']);
});

$choiceBases = array_slice($demandBases, 0, 5);
$appName = $coreSettings['app_name'] ?? 'Meu Projeto Web';
$logo = trim((string)($coreSettings['app_logo'] ?? ''));
$logoUrl = $logo !== '' ? '/web/assets/uploads/' . rawurlencode($logo) : '';
$favicon = trim((string)($coreSettings['app_favicon'] ?? ''));
$faviconUrl = $favicon !== '' ? '/web/assets/uploads/' . rawurlencode($favicon) : '';

function store_base_features(string $slug): array
{
    return [
        'Base pronta para iniciar um projeto rapidamente',
        'Painel administrativo protegido',
        'Fluxo de cadastro e criacao guiado',
        'Estrutura preparada para evoluir com modulos',
    ];
}

function store_base_badge(string $slug): string
{
    return 'Disponivel';
}

function store_base_landing_url(array $base): string
{
    $baseSlug = (string)$base['slug'];
    $pageSlug = trim((string)($base['landing_slug'] ?? '')) ?: $baseSlug;

    return '/web/p.php?slug=' . rawurlencode($pageSlug) . '&base=' . rawurlencode($baseSlug);
}

function store_base_public_title(array $base): string
{
    $title = trim((string)($base['showcase_title'] ?? ''));

    return $title !== '' ? $title : (string)$base['name'];
}

function store_base_public_summary(array $base): string
{
    $summary = trim((string)($base['showcase_summary'] ?? ''));
    $description = trim((string)($base['description'] ?? ''));
    $internalDescription = 'Base inicial do sistema (nao deletar)';

    if ($summary !== '') {
        return $summary;
    }

    return $description !== '' && $description !== $internalDescription
        ? $description
        : '';
}

function store_base_asset_url(?string $path): string
{
    $path = trim((string)$path);
    return $path !== '' ? '/web/assets/uploads/' . str_replace('%2F', '/', rawurlencode($path)) : '';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($appName) ?> | Bases prontas</title>
    <?php if ($faviconUrl !== ''): ?>
        <link rel="icon" href="<?= htmlspecialchars($faviconUrl) ?>">
    <?php endif; ?>
    <style>
        :root {
            color-scheme: dark;
            --bg: #050817;
            --panel: #10192b;
            --panel-2: #162238;
            --line: #31415b;
            --text: #f5f7fb;
            --muted: #aab4c6;
            --brand: #3b82f6;
            --brand-2: #21c37b;
            --warning: #f2b84b;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at 18% 8%, rgba(59, 130, 246, .16), transparent 28%),
                linear-gradient(180deg, #050817 0%, #080c1c 100%);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
        }

        a {
            color: inherit;
        }

        .c-store-header,
        .c-store-main,
        .c-store-footer {
            width: min(1180px, calc(100% - 40px));
            margin: 0 auto;
        }

        .c-store-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 22px 0;
        }

        .c-store-brand {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
            text-decoration: none;
        }

        .c-store-brand img {
            width: 118px;
            height: 52px;
            object-fit: contain;
        }

        .c-store-brand strong {
            display: block;
            font-size: 16px;
            line-height: 1.2;
        }

        .c-store-brand span {
            display: block;
            color: var(--muted);
            font-size: 12px;
            margin-top: 2px;
        }

        .c-store-brand-has-logo > span {
            display: none;
        }

        .c-store-brand-has-logo strong {
            display: none;
        }

        .c-store-nav {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 18px;
        }

        .c-store-nav a {
            color: var(--muted);
            font-size: 13px;
            text-decoration: none;
        }

        .c-store-hero {
            padding: 22px 0 28px;
        }

        .c-store-rotator {
            position: relative;
            min-height: 410px;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: rgba(16, 25, 43, .92);
        }

        .c-store-slide {
            position: absolute;
            inset: 0;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 28px;
            align-items: end;
            padding: 38px;
            opacity: 0;
            visibility: hidden;
            transition: opacity .28s ease, visibility .28s ease;
        }

        .c-store-slide.is-active {
            opacity: 1;
            visibility: visible;
        }

        .c-store-slide::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(5, 8, 23, .94), rgba(5, 8, 23, .72) 48%, rgba(5, 8, 23, .22)),
                linear-gradient(135deg, rgba(33, 195, 123, .8), rgba(59, 130, 246, .76));
            background-size: cover;
            background-position: center;
            z-index: 0;
        }

        .c-store-slide[data-bg]::before {
            background-image:
                linear-gradient(90deg, rgba(5, 8, 23, .94), rgba(5, 8, 23, .72) 48%, rgba(5, 8, 23, .22)),
                var(--slide-bg);
        }

        .c-store-slide-content,
        .c-store-slide-panel {
            position: relative;
            z-index: 1;
        }

        .c-store-slide-content {
            max-width: 720px;
        }

        .c-store-slide h1 {
            margin: 12px 0 14px;
            max-width: 820px;
            font-size: clamp(34px, 5vw, 64px);
            line-height: .98;
            letter-spacing: 0;
        }

        .c-store-slide p {
            max-width: 650px;
            margin: 0;
            color: var(--muted);
            font-size: 18px;
            line-height: 1.55;
        }

        .c-store-slide-panel {
            display: grid;
            gap: 12px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 10px;
            background: rgba(10, 16, 31, .76);
        }

        .c-store-slide-panel h2 {
            margin: 0;
            font-size: 26px;
            line-height: 1.08;
        }

        .c-store-slide-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .c-store-rotator-nav {
            position: absolute;
            left: 38px;
            right: 38px;
            bottom: 20px;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }

        .c-store-dots {
            display: flex;
            gap: 8px;
        }

        .c-store-dot {
            width: 34px;
            height: 4px;
            border: 0;
            border-radius: 999px;
            background: rgba(255, 255, 255, .28);
            cursor: pointer;
        }

        .c-store-dot.is-active {
            background: var(--brand-2);
        }

        .c-store-body-title {
            margin: 18px 0 -8px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .02em;
        }

        .c-store-copy {
            padding: 36px 0;
        }

        .c-store-kicker {
            color: var(--brand-2);
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            font-size: 12px;
        }

        .c-store-copy h1 {
            margin: 12px 0 14px;
            max-width: 780px;
            font-size: clamp(34px, 5vw, 64px);
            line-height: .98;
            letter-spacing: 0;
        }

        .c-store-copy p {
            max-width: 670px;
            margin: 0;
            color: var(--muted);
            font-size: 18px;
            line-height: 1.55;
        }

        .c-store-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 26px;
        }

        .c-store-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel-2);
            color: var(--text);
            font-weight: 700;
            text-decoration: none;
        }

        .c-store-btn-primary {
            border-color: transparent;
            background: var(--brand);
            color: #fff;
        }

        .c-store-featured,
        .c-store-form,
        .c-store-card,
        .c-store-note {
            border: 1px solid var(--line);
            background: rgba(16, 25, 43, .92);
        }

        .c-store-featured {
            display: flex;
            flex-direction: column;
            gap: 18px;
            padding: 22px;
            border-radius: 10px;
            overflow: hidden;
        }

        .c-store-featured-media {
            min-height: 150px;
            margin: -22px -22px 0;
            background:
                linear-gradient(135deg, rgba(33, 195, 123, .8), rgba(59, 130, 246, .76)),
                repeating-linear-gradient(90deg, transparent 0 22px, rgba(255, 255, 255, .08) 22px 23px);
            background-size: cover;
            background-position: center;
        }

        .c-store-featured-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
        }

        .c-store-featured h2 {
            margin: 0;
            font-size: 28px;
            line-height: 1.1;
        }

        .c-store-featured p {
            margin: 8px 0 0;
            color: var(--muted);
            line-height: 1.45;
        }

        .c-store-badge {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            background: rgba(33, 195, 123, .14);
            color: #4ade80;
            font-weight: 700;
            font-size: 12px;
        }

        .c-store-feature-list {
            display: grid;
            gap: 10px;
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .c-store-feature-list li {
            display: flex;
            gap: 10px;
            color: #dbe4f2;
            line-height: 1.35;
        }

        .c-store-feature-list li::before {
            content: "";
            width: 8px;
            height: 8px;
            flex: 0 0 auto;
            margin-top: 6px;
            border-radius: 50%;
            background: var(--brand-2);
        }

        .c-store-form-section {
            padding: 24px 0 52px;
        }

        .c-store-bases-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px;
        }

        .c-store-card {
            display: flex;
            flex-direction: column;
            min-height: 100%;
            border-radius: 10px;
            overflow: hidden;
        }

        .c-store-card-media {
            min-height: 106px;
            background:
                linear-gradient(135deg, rgba(59, 130, 246, .78), rgba(33, 195, 123, .66)),
                repeating-linear-gradient(90deg, transparent 0 20px, rgba(255, 255, 255, .08) 20px 21px);
            background-size: cover;
            background-position: center;
        }

        .c-store-card-body {
            display: grid;
            gap: 10px;
            padding: 14px;
        }

        .c-store-card h3 {
            margin: 0;
            font-size: 17px;
            line-height: 1.2;
        }

        .c-store-card p {
            min-height: 58px;
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }

        .c-store-card .c-store-btn {
            min-height: 40px;
            padding: 0 12px;
            font-size: 13px;
        }

        .c-store-section-title {
            display: flex;
            align-items: flex-end;
            justify-content: flex-start;
            gap: 18px;
            margin-bottom: 16px;
        }

        .c-store-section-title h2 {
            margin: 0;
            font-size: 28px;
        }

        .c-store-section-title p {
            margin: 6px 0 0;
            color: var(--muted);
        }

        .c-store-form {
            padding: 22px;
            border-radius: 10px;
        }

        .c-store-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .c-store-field {
            display: grid;
            gap: 7px;
        }

        .c-store-field label {
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }

        .c-store-field input,
        .c-store-field select {
            width: 100%;
            min-height: 46px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #070b19;
            color: var(--text);
            padding: 0 12px;
            font: inherit;
        }

        .c-store-field-full {
            grid-column: 1 / -1;
        }

        .c-store-submit {
            width: 100%;
            min-height: 48px;
            border: 0;
            border-radius: 8px;
            background: var(--brand);
            color: #fff;
            font-weight: 800;
            cursor: pointer;
        }

        .c-store-direct-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 16px;
        }

        .c-store-note {
            padding: 18px;
            border-radius: 10px;
            color: var(--muted);
        }

        .c-store-footer {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 24px 0 36px;
            color: #77839a;
            font-size: 12px;
        }

        @media (max-width: 860px) {
            .c-store-slide {
                grid-template-columns: 1fr;
                align-items: end;
                padding: 26px;
            }

            .c-store-slide-panel {
                display: none;
            }

            .c-store-bases-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .c-store-copy {
                padding: 16px 0 4px;
            }

            .c-store-form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 560px) {
            .c-store-header,
            .c-store-main,
            .c-store-footer {
                width: min(100% - 28px, 1180px);
            }

            .c-store-header {
                align-items: flex-start;
            }

            .c-store-nav {
                gap: 10px;
                font-size: 12px;
            }

            .c-store-copy h1 {
                font-size: 36px;
            }

            .c-store-rotator {
                min-height: 440px;
            }

            .c-store-slide h1 {
                font-size: 36px;
            }

            .c-store-copy p {
                font-size: 16px;
            }

            .c-store-slide p {
                font-size: 16px;
            }

            .c-store-bases-grid {
                grid-template-columns: 1fr;
            }

            .c-store-rotator-nav {
                left: 26px;
                right: 26px;
            }

            .c-store-actions,
            .c-store-direct-actions,
            .c-store-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .c-store-featured-head,
            .c-store-section-title {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <header class="c-store-header">
        <a class="c-store-brand <?= $logoUrl !== '' ? 'c-store-brand-has-logo' : '' ?>" href="/web/">
            <?php if ($logoUrl !== ''): ?>
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($appName) ?>">
            <?php endif; ?>
            <span>
                <strong><?= htmlspecialchars($appName) ?></strong>
                <span>Bases prontas para projetos digitais</span>
            </span>
        </a>

        <nav class="c-store-nav" aria-label="Navegacao principal">
            <a href="/web/bases.php">Bases</a>
            <a href="/web/blog.php">Blog</a>
        </nav>
    </header>

    <main class="c-store-main">
        <div class="c-store-body-title">Bases prontas para projetos digitais</div>

        <section class="c-store-hero">
            <?php if ($featuredBases): ?>
                <div class="c-store-rotator" data-store-rotator>
                    <?php foreach ($featuredBases as $index => $base): ?>
                        <?php
                            $baseSlug = (string)$base['slug'];
                            $slideImage = store_base_asset_url($base['showcase_banner_image'] ?: $base['showcase_cover_image'] ?: null);
                            $summary = store_base_public_summary($base);
                        ?>
                        <article
                            class="c-store-slide <?= $index === 0 ? 'is-active' : '' ?>"
                            data-store-slide
                            <?= $slideImage !== '' ? 'data-bg="1" style="--slide-bg: url(\'' . htmlspecialchars($slideImage, ENT_QUOTES) . '\')"' : '' ?>>
                            <div class="c-store-slide-content">
                                <div class="c-store-kicker">Base em destaque</div>
                                <h1><?= htmlspecialchars(store_base_public_title($base)) ?></h1>
                                <p><?= htmlspecialchars($summary !== '' ? $summary : 'Escolha uma base pronta e continue a criacao pelo e-mail cadastrado.') ?></p>
                                <div class="c-store-actions">
                                    <a class="c-store-btn c-store-btn-primary" href="<?= htmlspecialchars(store_base_landing_url($base)) ?>">Escolher base</a>
                                    <a class="c-store-btn" href="/web/bases.php">Ver todas</a>
                                </div>
                            </div>

                            <aside class="c-store-slide-panel">
                                <div class="c-store-slide-meta">
                                    <span class="c-store-badge"><?= htmlspecialchars(store_base_badge($baseSlug)) ?></span>
                                    <span class="c-store-badge"><?= (int)($base['total_projects'] ?? 0) + (int)($base['total_leads'] ?? 0) ?> demanda(s)</span>
                                </div>
                                <h2><?= htmlspecialchars((string)$base['name']) ?></h2>
                                <ul class="c-store-feature-list">
                                    <?php foreach (store_base_features($baseSlug) as $feature): ?>
                                        <li><?= htmlspecialchars($feature) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </aside>
                        </article>
                    <?php endforeach; ?>

                    <?php if (count($featuredBases) > 1): ?>
                        <div class="c-store-rotator-nav">
                            <div class="c-store-dots" aria-label="Bases em destaque">
                                <?php foreach ($featuredBases as $index => $base): ?>
                                    <button class="c-store-dot <?= $index === 0 ? 'is-active' : '' ?>"
                                            type="button"
                                            data-store-dot="<?= $index ?>"
                                            aria-label="Mostrar <?= htmlspecialchars(store_base_public_title($base)) ?>"></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="c-store-note">Nenhuma base publica esta disponivel no momento.</div>
            <?php endif; ?>
        </section>

        <section id="bases" class="c-store-form-section">
            <div class="c-store-section-title">
                <div>
                    <h2>Escolha uma base</h2>
                    <p>Abra o modelo ideal e continue pela página da base escolhida.</p>
                </div>
            </div>

            <?php if ($bases): ?>
                <div class="c-store-bases-grid">
                    <?php foreach ($choiceBases as $base): ?>
                        <?php
                            $baseSlug = (string)$base['slug'];
                            $cardImage = store_base_asset_url($base['showcase_cover_image'] ?: $base['showcase_banner_image'] ?: null);
                            $summary = store_base_public_summary($base);
                        ?>
                        <article class="c-store-card">
                            <div class="c-store-card-media" <?= $cardImage !== '' ? 'style="background-image: linear-gradient(180deg, rgba(5,8,23,.05), rgba(5,8,23,.48)), url(\'' . htmlspecialchars($cardImage, ENT_QUOTES) . '\')"' : '' ?>></div>
                            <div class="c-store-card-body">
                                <span class="c-store-badge"><?= htmlspecialchars(store_base_badge($baseSlug)) ?></span>
                                <span class="c-store-badge"><?= (int)($base['total_projects'] ?? 0) + (int)($base['total_leads'] ?? 0) ?> demanda(s)</span>
                                <h3><?= htmlspecialchars(store_base_public_title($base)) ?></h3>
                                <?php if ($summary !== ''): ?>
                                    <p><?= htmlspecialchars($summary) ?></p>
                                <?php else: ?>
                                    <p>Base pronta para iniciar um projeto com estrutura organizada.</p>
                                <?php endif; ?>
                                <a class="c-store-btn c-store-btn-primary" href="<?= htmlspecialchars(store_base_landing_url($base)) ?>">Escolher base</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="c-store-direct-actions">
                    <a class="c-store-btn" href="/web/bases.php">Ver catálogo completo</a>
                </div>
            <?php else: ?>
                <div class="c-store-note">As bases publicas ainda nao foram liberadas para criacao.</div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="c-store-footer">
        <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?></span>
        <span>Projetos com bases e modulos organizados.</span>
    </footer>
    <script>
        (function () {
            var root = document.querySelector('[data-store-rotator]');
            if (!root) {
                return;
            }

            var slides = Array.prototype.slice.call(root.querySelectorAll('[data-store-slide]'));
            var dots = Array.prototype.slice.call(root.querySelectorAll('[data-store-dot]'));
            var active = 0;
            var timer = null;

            function show(index) {
                if (!slides.length) {
                    return;
                }

                active = (index + slides.length) % slides.length;
                slides.forEach(function (slide, slideIndex) {
                    slide.classList.toggle('is-active', slideIndex === active);
                });
                dots.forEach(function (dot, dotIndex) {
                    dot.classList.toggle('is-active', dotIndex === active);
                });
            }

            function start() {
                if (slides.length < 2) {
                    return;
                }

                window.clearInterval(timer);
                timer = window.setInterval(function () {
                    show(active + 1);
                }, 6500);
            }

            dots.forEach(function (dot) {
                dot.addEventListener('click', function () {
                    show(parseInt(dot.getAttribute('data-store-dot'), 10) || 0);
                    start();
                });
            });

            start();
        }());
    </script>
</body>
</html>
