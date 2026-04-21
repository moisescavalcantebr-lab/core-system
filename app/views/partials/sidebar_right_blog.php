<?php

$stmt = $pdo->query("
    SELECT title, slug
    FROM core_page_contents
    WHERE type='blog'
    AND status='published'
    ORDER BY created_at DESC
    LIMIT 5
");

$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="sidebar-block">

<h3 class="sidebar-title">Recentes</h3>

<ul class="sidebar-list">

<?php foreach($recent as $post): ?>

<li>
    <a href="/public/p.php?slug=<?= urlencode($post['slug']) ?>">
        <?= htmlspecialchars($post['title']) ?>
    </a>
</li>

<?php endforeach; ?>

</ul>

</div>