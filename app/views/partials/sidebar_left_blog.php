<?php
$stmt = $pdo->query("
    SELECT title, slug, category, sub_category
    FROM core_page_contents
    WHERE type='blog'
    AND status='published'
    AND area='public'
    ORDER BY created_at DESC, id DESC
    LIMIT 5
");

$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="sidebar-block">
    <h3 class="sidebar-title">Recentes</h3>

    <ul class="sidebar-list">
        <?php foreach($recent as $post): ?>
            <li>
                <a href="/web/p.php?slug=<?= urlencode((string)$post['slug']) ?>" class="<?= $currentSlug === $post['slug'] ? 'active' : '' ?>">
                    <?= htmlspecialchars((string)$post['title']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<div class="sidebar-block sidebar-ad-slot">
    <span>Publicidade</span>
    <strong>Espaco reservado</strong>
    <p>Pronto para inserir o codigo do AdSense quando a monetizacao estiver ativa.</p>
</div>
