<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/bootstrap.php';

$manifestPath = STORAGE_PATH . '/paginas/pages_manifest.json';
$pagesPath = STORAGE_PATH . '/paginas/pages';

if (!is_file($manifestPath)) {
    echo "pages_manifest ausente\n";
    exit(0);
}

$manifest = json_decode((string)file_get_contents($manifestPath), true);

if (!is_array($manifest)) {
    fwrite(STDERR, "pages_manifest invalido\n");
    exit(1);
}

$allowedTypes = ['page', 'blog'];
$allowedStatuses = ['draft', 'published'];
$synced = 0;
$skipped = 0;

$stmt = $pdo->prepare("
    INSERT INTO core_page_contents
        (title, slug, type, model_slug, category, sub_category, content_path, status, area, updated_at)
    VALUES
        (:title, :slug, :type, :model_slug, :category, :sub_category, :content_path, :status, 'public', NOW())
    ON DUPLICATE KEY UPDATE
        title = VALUES(title),
        type = VALUES(type),
        model_slug = VALUES(model_slug),
        category = VALUES(category),
        sub_category = VALUES(sub_category),
        content_path = VALUES(content_path),
        status = VALUES(status),
        updated_at = NOW()
");

foreach ($manifest as $page) {
    if (!is_array($page)) {
        $skipped++;
        continue;
    }

    $contentPath = basename((string)($page['content_path'] ?? ''));
    $type = (string)($page['type'] ?? '');
    $status = (string)($page['status'] ?? 'draft');
    $slug = trim((string)($page['slug'] ?? ''));
    $title = trim((string)($page['title'] ?? ''));

    if (
        $contentPath === ''
        || $slug === ''
        || $title === ''
        || !in_array($type, $allowedTypes, true)
        || !is_file($pagesPath . '/' . $contentPath)
    ) {
        $skipped++;
        continue;
    }

    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'draft';
    }

    $stmt->execute([
        'title' => $title,
        'slug' => $slug,
        'type' => $type,
        'model_slug' => trim((string)($page['model_slug'] ?? '')) ?: null,
        'category' => trim((string)($page['category'] ?? '')) ?: null,
        'sub_category' => trim((string)($page['sub_category'] ?? '')) ?: null,
        'content_path' => $contentPath,
        'status' => $status,
    ]);

    $synced++;
}

echo "Paginas sincronizadas: {$synced}; ignoradas: {$skipped}\n";
