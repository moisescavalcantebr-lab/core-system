<?php
$stmt = $pdo->query("
    SELECT category, sub_category, title, slug
    FROM core_page_contents
    WHERE type='blog'
    AND status='published'
    AND area='public'
    ORDER BY category, sub_category, created_at DESC
");

$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$tree = [];

foreach($posts as $p){
    $cat = trim((string)($p['category'] ?? '')) ?: 'Sem categoria';
    $sub = trim((string)($p['sub_category'] ?? '')) ?: 'Geral';
    $tree[$cat][$sub][] = $p;
}
?>

<div class="sidebar-block">
    <h3 class="sidebar-title">Categorias</h3>

    <?php foreach($tree as $cat => $subs): ?>
        <div class="menu-category">
            <div class="menu-cat-title">
                <?= htmlspecialchars($cat) ?>
            </div>

            <div class="menu-sub-list">
                <?php foreach($subs as $sub => $items): ?>
                    <div class="menu-sub">
                        <div class="menu-sub-title">
                            <?= htmlspecialchars($sub) ?>
                        </div>

                        <ul class="menu-post-list">
                            <?php foreach($items as $post): ?>
                                <li>
                                    <a href="/web/p.php?slug=<?= urlencode((string)$post['slug']) ?>" class="<?= $currentSlug === $post['slug'] ? 'active' : '' ?>">
                                        <?= htmlspecialchars((string)$post['title']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
