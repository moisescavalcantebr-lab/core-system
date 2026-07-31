<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap/bootstrap.php';

$stmt = $pdo->query("
    SELECT b.id, b.slug, b.name, b.description, b.showcase_title, b.showcase_summary,
           showcase_cover_image, showcase_banner_image, showcase_order,
           showcase_status,
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
usort($bases, static function (array $a, array $b): int {
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
$appName = $coreSettings['app_name'] ?? 'Meu Projeto Web';
$logo = trim((string)($coreSettings['app_logo'] ?? ''));
$logoUrl = $logo !== '' ? '/web/assets/uploads/' . rawurlencode($logo) : '';
$favicon = trim((string)($coreSettings['app_favicon'] ?? ''));
$faviconUrl = $favicon !== '' ? '/web/assets/uploads/' . rawurlencode($favicon) : '';

function store_catalog_features(string $slug): array
{
    return [
        'Painel administrativo',
        'Fluxo de criacao guiado',
        'Estrutura modular',
        'Pronto para evoluir',
    ];
}

function store_catalog_landing_url(array $base): string
{
    $baseSlug = (string)$base['slug'];
    $pageSlug = trim((string)($base['landing_slug'] ?? '')) ?: $baseSlug;

    return '/web/p.php?slug=' . rawurlencode($pageSlug) . '&base=' . rawurlencode($baseSlug);
}

function store_catalog_public_title(array $base): string
{
    $title = trim((string)($base['showcase_title'] ?? ''));

    return $title !== '' ? $title : (string)$base['name'];
}

function store_catalog_public_summary(array $base): string
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

function store_catalog_asset_url(?string $path): string
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
    <title><?= htmlspecialchars($appName) ?> | Bases</title>
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
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at 16% 8%, rgba(33, 195, 123, .13), transparent 24%),
                radial-gradient(circle at 80% 0%, rgba(59, 130, 246, .14), transparent 26%),
                linear-gradient(180deg, #050817 0%, #080c1c 100%);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
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
            color: inherit;
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

        .c-store-intro {
            padding: 34px 0 20px;
        }

        .c-store-body-title {
            margin: 18px 0 -14px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .02em;
        }

        .c-store-kicker {
            color: var(--brand-2);
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            font-size: 12px;
        }

        .c-store-intro h1 {
            margin: 12px 0 12px;
            max-width: 780px;
            font-size: clamp(34px, 5vw, 58px);
            line-height: 1;
            letter-spacing: 0;
        }

        .c-store-intro p {
            max-width: 720px;
            margin: 0;
            color: var(--muted);
            font-size: 17px;
            line-height: 1.55;
        }

        .c-bases-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 14px;
            padding: 22px 0 56px;
        }

        .c-base-card {
            display: grid;
            min-height: 360px;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: rgba(16, 25, 43, .94);
        }

        .c-base-cover {
            min-height: 118px;
            background:
                linear-gradient(135deg, rgba(33, 195, 123, .85), rgba(59, 130, 246, .82)),
                repeating-linear-gradient(90deg, transparent 0 22px, rgba(255, 255, 255, .08) 22px 23px);
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .c-base-cover::after {
            content: attr(data-title);
            position: absolute;
            left: 14px;
            right: 14px;
            bottom: 13px;
            color: #fff;
            font-size: 22px;
            font-weight: 800;
            line-height: 1;
            text-shadow: 0 8px 26px rgba(0, 0, 0, .35);
        }

        .c-base-body {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 14px;
        }

        .c-base-meta {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .c-base-card h2 {
            margin: 0;
            font-size: 17px;
            line-height: 1.2;
        }

        .c-base-card p {
            margin: 7px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }

        .c-base-badge {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            background: rgba(33, 195, 123, .14);
            color: #4ade80;
            font-size: 12px;
            font-weight: 700;
        }

        .c-base-features {
            display: grid;
            gap: 7px;
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .c-base-features li {
            display: flex;
            gap: 9px;
            color: #dce5f4;
            font-size: 13px;
            line-height: 1.35;
        }

        .c-base-features li::before {
            content: "";
            width: 7px;
            height: 7px;
            flex: 0 0 auto;
            margin-top: 6px;
            border-radius: 50%;
            background: var(--brand-2);
        }

        .c-base-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }

        .c-store-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel-2);
            color: var(--text);
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
        }

        .c-store-btn-primary {
            flex: 1;
            border-color: transparent;
            background: var(--brand);
            color: #fff;
        }

        .c-store-empty {
            padding: 22px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: rgba(16, 25, 43, .94);
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

        @media (max-width: 620px) {
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
            }

            .c-store-intro {
                padding-top: 18px;
            }

            .c-store-intro h1 {
                font-size: 36px;
            }

            .c-store-intro p {
                font-size: 16px;
            }

            .c-base-actions,
            .c-store-footer {
                flex-direction: column;
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
            <a href="/web/">Inicio</a>
            <a href="/web/blog.php">Blog</a>
        </nav>
    </header>

    <main class="c-store-main">
        <div class="c-store-body-title">Bases prontas para projetos digitais</div>

        <section class="c-store-intro">
            <div class="c-store-kicker">Catalogo de bases</div>
            <h1>Escolha a base ideal para comecar.</h1>
            <p>Cada base aprovada aparece aqui como uma vitrine limpa, com resumo, recursos principais e link para a landing completa.</p>
        </section>

        <?php if ($bases): ?>
            <section class="c-bases-grid" aria-label="Bases disponiveis">
                <?php foreach ($bases as $base): ?>
                    <?php $baseSlug = (string)$base['slug']; ?>
                    <article class="c-base-card">
                        <?php $coverImage = store_catalog_asset_url($base['showcase_cover_image'] ?: $base['showcase_banner_image'] ?: null); ?>
                        <div class="c-base-cover"
                             data-title="<?= htmlspecialchars(store_catalog_public_title($base)) ?>"
                             <?= $coverImage !== '' ? 'style="background-image: linear-gradient(180deg, rgba(5,8,23,.05), rgba(5,8,23,.48)), url(\'' . htmlspecialchars($coverImage, ENT_QUOTES) . '\')"' : '' ?>></div>

                        <div class="c-base-body">
                            <div class="c-base-meta">
                                <div>
                                    <h2><?= htmlspecialchars(store_catalog_public_title($base)) ?></h2>
                                    <?php $baseSummary = store_catalog_public_summary($base); ?>
                                    <?php if ($baseSummary !== ''): ?>
                                        <p><?= htmlspecialchars($baseSummary) ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="c-base-badge"><?= (int)($base['total_projects'] ?? 0) + (int)($base['total_leads'] ?? 0) ?> demanda(s)</span>
                            </div>

                            <ul class="c-base-features">
                                <?php foreach (store_catalog_features($baseSlug) as $feature): ?>
                                    <li><?= htmlspecialchars($feature) ?></li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="c-base-actions">
                                <a class="c-store-btn c-store-btn-primary" href="<?= htmlspecialchars(store_catalog_landing_url($base)) ?>">Ver mais</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php else: ?>
            <div class="c-store-empty">Nenhuma base publica esta disponivel no momento.</div>
        <?php endif; ?>
    </main>

    <footer class="c-store-footer">
        <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?></span>
        <span>Vitrine de bases prontas.</span>
    </footer>
</body>
</html>
