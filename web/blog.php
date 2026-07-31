<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap/bootstrap.php';

$stmt = $pdo->query("
    SELECT id, title, slug, category, sub_category, content_path, created_at
    FROM core_page_contents
    WHERE area='public'
      AND type='blog'
      AND status='published'
    ORDER BY created_at DESC, id DESC
");

$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$featuredPosts = array_slice($posts, 0, 2);
$morePosts = array_slice($posts, 2);
$categoryCounts = [];

foreach ($posts as $post) {
    $category = trim((string)($post['category'] ?? '')) ?: 'Blog';
    $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
}

$appName = $coreSettings['app_name'] ?? 'Meu Projeto Web';
$logo = trim((string)($coreSettings['app_logo'] ?? ''));
$logoUrl = $logo !== '' ? '/web/assets/uploads/' . rawurlencode($logo) : '';
$favicon = trim((string)($coreSettings['app_favicon'] ?? ''));
$faviconUrl = $favicon !== '' ? '/web/assets/uploads/' . rawurlencode($favicon) : '';

function blog_index_post_data(array $post): array
{
    $path = STORAGE_PATH . '/paginas/pages/' . basename((string)($post['content_path'] ?? ''));
    $data = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
    $blocks = is_array($data['blocks'] ?? null) ? $data['blocks'] : [];
    $summary = '';
    $image = '';

    foreach ($blocks as $block) {
        if (!is_array($block) || ($block['enabled'] ?? true) === false) {
            continue;
        }

        if ($summary === '' && ($block['type'] ?? '') === 'blog_header') {
            $summary = trim((string)($block['subtitle'] ?? ''));
        }

        if ($summary === '' && isset($block['content'])) {
            $summary = trim(strip_tags((string)$block['content']));
        }

        if ($image === '' && isset($block['image'])) {
            $image = trim((string)$block['image']);
        }
    }

    return [
        'summary' => $summary !== '' ? substr($summary, 0, 150) : '',
        'image' => $image,
    ];
}

function blog_index_meta(array $post): string
{
    $meta = trim((string)($post['category'] ?? '')) ?: 'Blog';

    if (!empty($post['sub_category'])) {
        $meta .= ' / ' . trim((string)$post['sub_category']);
    }

    return $meta;
}
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
            min-height: 360px;
            display: grid;
            align-content: center;
            padding: 48px 0 30px;
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
            max-width: 820px;
            margin: 0;
            font-size: clamp(42px, 7vw, 76px);
            line-height: .96;
            letter-spacing: 0;
        }

        .c-blog-index-hero p {
            max-width: 620px;
            margin: 20px 0 0;
            color: var(--muted);
            font-size: 18px;
            line-height: 1.55;
        }

        .c-blog-index-grid,
        .c-blog-more-grid {
            display: grid;
            gap: 18px;
        }

        .c-blog-index-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            padding: 20px 0 34px;
        }

        .c-blog-index-card {
            display: grid;
            min-height: 260px;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: rgba(16, 25, 43, .84);
            color: inherit;
            text-decoration: none;
        }

        .c-blog-index-card:hover {
            border-color: var(--brand);
        }

        .c-blog-index-card-media {
            min-height: 132px;
            background:
                linear-gradient(135deg, rgba(33, 195, 123, .72), rgba(59, 130, 246, .82));
            background-size: cover;
            background-position: center;
        }

        .c-blog-index-card-body {
            padding: 20px;
        }

        .c-blog-index-card small {
            display: inline-flex;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.35;
        }

        .c-blog-index-card h2 {
            margin: 12px 0 0;
            font-size: 23px;
            line-height: 1.14;
            letter-spacing: 0;
        }

        .c-blog-index-card p {
            margin: 12px 0 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .c-blog-section-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 18px;
            margin: 30px 0 16px;
        }

        .c-blog-section-head h2 {
            margin: 0;
            font-size: 24px;
        }

        .c-blog-categories {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }

        .c-blog-category-pill {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 7px 10px;
            border: 1px solid rgba(49, 65, 91, .9);
            border-radius: 8px;
            background: rgba(16, 25, 43, .68);
            color: var(--muted);
            font-size: 12px;
        }

        .c-blog-more-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            padding-bottom: 80px;
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

            .c-blog-more-grid {
                grid-template-columns: 1fr;
            }

            .c-blog-section-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .c-blog-categories {
                justify-content: flex-start;
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
            <p>Conteudos praticos sobre bases, gestao e caminhos para transformar uma ideia em projeto organizado.</p>
        </section>

        <?php if (!$posts): ?>
            <div class="c-blog-index-card"><div class="c-blog-index-card-body">Nenhum post publicado no momento.</div></div>
        <?php else: ?>
            <section class="c-blog-index-grid" aria-label="Artigos em destaque">
                <?php foreach ($featuredPosts as $post): ?>
                    <?php $postData = blog_index_post_data($post); ?>
                    <a class="c-blog-index-card" href="/web/p.php?slug=<?= urlencode((string)$post['slug']) ?>">
                        <div class="c-blog-index-card-media" <?= $postData['image'] !== '' ? 'style="background-image: linear-gradient(180deg, rgba(5,8,23,.05), rgba(5,8,23,.55)), url(\'' . htmlspecialchars($postData['image'], ENT_QUOTES) . '\')"' : '' ?>></div>
                        <div class="c-blog-index-card-body">
                            <small><?= htmlspecialchars(blog_index_meta($post)) ?></small>
                            <h2><?= htmlspecialchars((string)$post['title']) ?></h2>
                            <?php if ($postData['summary'] !== ''): ?>
                                <p><?= htmlspecialchars($postData['summary']) ?></p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </section>

            <div class="c-blog-section-head">
                <h2>Mais artigos</h2>
                <?php if ($categoryCounts): ?>
                    <div class="c-blog-categories" aria-label="Categorias">
                        <?php foreach ($categoryCounts as $category => $count): ?>
                            <span class="c-blog-category-pill"><?= htmlspecialchars((string)$category) ?> · <?= (int)$count ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($morePosts): ?>
                <section class="c-blog-more-grid" aria-label="Lista de artigos">
                    <?php foreach ($morePosts as $post): ?>
                        <?php $postData = blog_index_post_data($post); ?>
                        <a class="c-blog-index-card" href="/web/p.php?slug=<?= urlencode((string)$post['slug']) ?>">
                            <div class="c-blog-index-card-body">
                                <small><?= htmlspecialchars(blog_index_meta($post)) ?></small>
                                <h2><?= htmlspecialchars((string)$post['title']) ?></h2>
                                <?php if ($postData['summary'] !== ''): ?>
                                    <p><?= htmlspecialchars($postData['summary']) ?></p>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer class="c-store-footer">
        <span>© <?= date('Y') ?> <?= htmlspecialchars((string)$appName) ?></span>
        <span>Blog</span>
    </footer>
</body>
</html>
