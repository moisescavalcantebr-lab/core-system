<?php
$categoryStmt = $pdo->query("
    SELECT category, COUNT(*) AS total
    FROM core_page_contents
    WHERE type = 'blog'
      AND status = 'published'
      AND area = 'public'
    GROUP BY category
    ORDER BY category ASC
");

$blogCategories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
$currentCategory = trim((string)($page['category'] ?? ''));
?>

<?php if ($blogCategories): ?>
    <nav class="c-blog-category-nav" aria-label="Categorias do blog">
        <a class="<?= $currentCategory === '' ? 'is-active' : '' ?>" href="/web/blog.php">
            Todos
        </a>

        <?php foreach ($blogCategories as $category): ?>
            <?php $categoryName = trim((string)($category['category'] ?? '')) ?: 'Sem categoria'; ?>
            <a class="<?= strcasecmp($currentCategory, $categoryName) === 0 ? 'is-active' : '' ?>"
               href="/web/blog.php?category=<?= urlencode($categoryName) ?>">
                <?= htmlspecialchars($categoryName) ?>
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
