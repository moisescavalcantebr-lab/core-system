<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap/bootstrap.php';

$stmt = $pdo->query("
    SELECT title, slug, category, sub_category, created_at
    FROM core_page_contents
    WHERE area='public'
      AND type='blog'
      AND status='published'
    ORDER BY created_at DESC, id DESC
");

$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$appName = $coreSettings['app_name'] ?? 'Meu Projeto Web';
$logo = trim((string)($coreSettings['app_logo'] ?? ''));
$logoUrl = $logo !== '' ? '/web/assets/uploads/' . rawurlencode($logo) : '';
$favicon = trim((string)($coreSettings['app_favicon'] ?? ''));
$faviconUrl = $favicon !== '' ? '/web/assets/uploads/' . rawurlencode($favicon) : '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string)$appName) ?> | Blog</title>
    <?php if ($faviconUrl !== ''): ?>
        <link rel="icon" href="<?= htmlspecialchars($faviconUrl) ?>">
    <?php endif; ?>
    <style>
        :root {
            color-scheme: dark;
            --bg: #050817;
            --panel: #10192b;
            --line: #31415b;
            --text: #f5f7fb;
            --muted: #aab4c6;
            --brand: #3b82f6;
            --brand-2: #21c37b;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at 18% 8%, rgba(59, 130, 246, .16), transparent 28%),
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

        .c-store-brand span span {
            display: block;
            color: var(--muted);
            font-size: 12px;
            margin-top: 2px;
        }

        .c-store-brand-has-logo > span,
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

        .c-store-nav a:hover { color: var(--text); }

        .c-blog-index-hero {
            padding: 34px 0 24px;
        }

        .c-blog-index-hero span {
            display: block;
            color: var(--brand-2);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .c-blog-index-hero h1 {
            max-width: 780px;
            margin: 0;
            font-size: clamp(40px, 7vw, 72px);
            line-height: .98;
            letter-spacing: 0;
        }

        .c-blog-index-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            padding: 20px 0 80px;
        }

        .c-blog-index-card {
            display: block;
            min-height: 150px;
            padding: 22px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: rgba(16, 25, 43, .84);
            color: inherit;
            text-decoration: none;
        }

        .c-blog-index-card:hover {
            border-color: var(--brand);
        }

        .c-blog-index-card small {
            display: inline-flex;
            margin-bottom: 16px;
            color: var(--muted);
        }

        .c-blog-index-card h2 {
            margin: 0;
            font-size: 22px;
            line-height: 1.18;
            letter-spacing: 0;
        }

        .c-store-footer {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 12px;
            padding: 26px 0 30px;
            border-top: 1px solid rgba(49, 65, 91, .8);
            color: var(--muted);
            font-size: 13px;
        }

        @media (max-width: 760px) {
            .c-store-header,
            .c-store-main,
            .c-store-footer {
                width: min(100% - 28px, 680px);
            }

            .c-store-nav {
                gap: 12px;
            }

            .c-blog-index-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="c-store-header">
        <a class="c-store-brand <?= $logoUrl !== '' ? 'c-store-brand-has-logo' : '' ?>" href="/web/">
            <?php if ($logoUrl !== ''): ?>
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars((string)$appName) ?>">
            <?php endif; ?>
            <span>
                <strong><?= htmlspecialchars((string)$appName) ?></strong>
                <span>Blog</span>
            </span>
        </a>

        <nav class="c-store-nav" aria-label="Navegacao principal">
            <a href="/web/">Inicio</a>
            <a href="/web/bases.php">Bases</a>
            <a href="/web/blog.php">Blog</a>
        </nav>
    </header>

    <main class="c-store-main">
        <section class="c-blog-index-hero">
            <span>Blog</span>
            <h1>Ideias para organizar e evoluir seus projetos.</h1>
        </section>

        <?php if (!$posts): ?>
            <div class="c-blog-index-card">Nenhum post publicado no momento.</div>
        <?php else: ?>
            <section class="c-blog-index-grid">
                <?php foreach ($posts as $post): ?>
                    <a class="c-blog-index-card" href="/web/p.php?slug=<?= urlencode((string)$post['slug']) ?>">
                        <small>
                            <?= htmlspecialchars(trim((string)($post['category'] ?? '')) ?: 'Blog') ?>
                            <?php if (!empty($post['sub_category'])): ?>
                                / <?= htmlspecialchars((string)$post['sub_category']) ?>
                            <?php endif; ?>
                        </small>
                        <h2><?= htmlspecialchars((string)$post['title']) ?></h2>
                    </a>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <footer class="c-store-footer">
        <span>© <?= date('Y') ?> <?= htmlspecialchars((string)$appName) ?></span>
        <span>Blog</span>
    </footer>
</body>
</html>
