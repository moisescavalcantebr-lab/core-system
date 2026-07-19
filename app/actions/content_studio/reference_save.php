<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

ContentStudioService::ensureSchema($pdo);

$type = (string)($_POST['type'] ?? '');
$id = (int)($_POST['id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));

if ($name === '') {
    die('Nome obrigatório.');
}

if ($type === 'channel') {
    $platform = trim((string)($_POST['platform'] ?? '')) ?: 'geral';
    $handle = trim((string)($_POST['handle'] ?? '')) ?: null;

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM content_studio_channels
        WHERE LOWER(name) = LOWER(:name)
          AND LOWER(platform) = LOWER(:platform)
          AND COALESCE(handle, '') = COALESCE(:handle, '')
          AND id <> :id
    ");
    $stmt->execute([
        ':name' => $name,
        ':platform' => $platform,
        ':handle' => $handle,
        ':id' => $id,
    ]);

    if ((int)$stmt->fetchColumn() > 0) {
        flash('error', 'Este canal ja existe no Content Studio.');
        redirect('/web/admin/content_studio/settings.php');
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE content_studio_channels
            SET name = :name, platform = :platform, handle = :handle, url = :url, status = :status
            WHERE id = :id
        ");
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO content_studio_channels (name, platform, handle, url, status)
            VALUES (:name, :platform, :handle, :url, :status)
        ");
    }
    $stmt->execute([
        ':name' => $name,
        ':platform' => $platform,
        ':handle' => $handle,
        ':url' => trim((string)($_POST['url'] ?? '')) ?: null,
        ':status' => (string)($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ...($id > 0 ? [':id' => $id] : []),
    ]);
} elseif ($type === 'niche') {
    $slug = ContentStudioService::slug($name);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM content_studio_niches WHERE slug = :slug AND id <> :id");
    $stmt->execute([':slug' => $slug, ':id' => $id]);

    if ((int)$stmt->fetchColumn() > 0) {
        flash('error', 'Este nicho ja existe no Content Studio.');
        redirect('/web/admin/content_studio/settings.php');
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE content_studio_niches
            SET name = :name, slug = :slug, description = :description, status = :status
            WHERE id = :id
        ");
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO content_studio_niches (name, slug, description, status)
            VALUES (:name, :slug, :description, :status)
        ");
    }
    $stmt->execute([
        ':name' => $name,
        ':slug' => $slug,
        ':description' => trim((string)($_POST['description'] ?? '')) ?: null,
        ':status' => (string)($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ...($id > 0 ? [':id' => $id] : []),
    ]);
} elseif ($type === 'persona') {
    $slug = ContentStudioService::slug($name);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM content_studio_personas WHERE slug = :slug AND id <> :id");
    $stmt->execute([':slug' => $slug, ':id' => $id]);

    if ((int)$stmt->fetchColumn() > 0) {
        flash('error', 'Este personagem ja existe no Content Studio.');
        redirect('/web/admin/content_studio/settings.php');
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE content_studio_personas
            SET name = :name, slug = :slug, description = :description, voice_notes = :voice_notes, status = :status
            WHERE id = :id
        ");
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO content_studio_personas (name, slug, description, voice_notes, status)
            VALUES (:name, :slug, :description, :voice_notes, :status)
        ");
    }
    $stmt->execute([
        ':name' => $name,
        ':slug' => $slug,
        ':description' => trim((string)($_POST['description'] ?? '')) ?: null,
        ':voice_notes' => trim((string)($_POST['voice_notes'] ?? '')) ?: null,
        ':status' => (string)($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ...($id > 0 ? [':id' => $id] : []),
    ]);
} else {
    die('Tipo inválido.');
}

redirect('/web/admin/content_studio/settings.php');
