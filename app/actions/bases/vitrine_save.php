<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

$baseId = (int)($_POST['base_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM bases WHERE id = ? LIMIT 1");
$stmt->execute([$baseId]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    flash('error', 'Base nao encontrada.');
    redirect('/web/admin/bases/index.php');
    exit;
}

$showcaseStatus = isset($_POST['showcase_status']) ? 1 : 0;
$showcaseTitle = trim((string)($_POST['showcase_title'] ?? ''));
$showcaseSummary = trim((string)($_POST['showcase_summary'] ?? ''));
$showcaseFeatures = trim((string)($_POST['showcase_features'] ?? ''));
$detailUrl = trim((string)($_POST['showcase_detail_url'] ?? ''));
$ctaText = trim((string)($_POST['showcase_cta_text'] ?? ''));

if ($detailUrl !== '' && !preg_match('#^(/|https?://)#i', $detailUrl)) {
    flash('error', 'Use uma URL de conteudo valida, iniciando com /, http:// ou https://.');
    redirect('/web/admin/bases/vitrine.php?id=' . $baseId);
    exit;
}

$ctaText = $ctaText !== '' ? substr($ctaText, 0, 80) : null;
$showcaseTitle = $showcaseTitle !== '' ? substr($showcaseTitle, 0, 150) : null;
$showcaseSummary = $showcaseSummary !== '' ? $showcaseSummary : null;
$showcaseFeatures = $showcaseFeatures !== '' ? $showcaseFeatures : null;
$detailUrl = $detailUrl !== '' ? substr($detailUrl, 0, 500) : null;

function base_showcase_upload(string $field, string $slug): ?string
{
    if (empty($_FILES[$field]['name'])) {
        return null;
    }

    $allowed = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    $mime = (string)($_FILES[$field]['type'] ?? '');

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Formato inválido. Use PNG, JPG ou WEBP.');
    }

    if (!is_uploaded_file($_FILES[$field]['tmp_name'])) {
        throw new RuntimeException('Upload inválido.');
    }

    $uploadDir = PUBLIC_PATH . '/assets/uploads/base_vitrine';

    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('A pasta de uploads nao tem permissao de escrita.');
    }

    if (!is_writable($uploadDir)) {
        throw new RuntimeException('A pasta de uploads nao tem permissao de escrita.');
    }

    $safeSlug = preg_replace('/[^a-z0-9_-]+/i', '-', $slug) ?: 'base';
    $fileName = $safeSlug . '_' . $field . '_' . time() . '.' . $allowed[$mime];
    $dest = $uploadDir . '/' . $fileName;

    if (!@move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
        throw new RuntimeException('Nao foi possivel salvar a imagem.');
    }

    return 'base_vitrine/' . $fileName;
}

function base_showcase_delete(?string $path): void
{
    if (!$path) {
        return;
    }

    $fullPath = PUBLIC_PATH . '/assets/uploads/' . ltrim($path, '/');

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

try {
    $coverImage = $base['showcase_cover_image'] ?? null;
    $bannerImage = $base['showcase_banner_image'] ?? null;

    if (isset($_POST['remove_cover']) && $_POST['remove_cover'] === '1') {
        base_showcase_delete($coverImage);
        $coverImage = null;
    }

    if (isset($_POST['remove_banner']) && $_POST['remove_banner'] === '1') {
        base_showcase_delete($bannerImage);
        $bannerImage = null;
    }

    $newCover = base_showcase_upload('showcase_cover_image', (string)$base['slug']);
    if ($newCover !== null) {
        base_showcase_delete($coverImage);
        $coverImage = $newCover;
    }

    $newBanner = base_showcase_upload('showcase_banner_image', (string)$base['slug']);
    if ($newBanner !== null) {
        base_showcase_delete($bannerImage);
        $bannerImage = $newBanner;
    }

    $stmt = $pdo->prepare("
        UPDATE bases
        SET showcase_title = :showcase_title,
            showcase_summary = :showcase_summary,
            showcase_features = :showcase_features,
            showcase_cover_image = :showcase_cover_image,
            showcase_banner_image = :showcase_banner_image,
            showcase_detail_url = :showcase_detail_url,
            showcase_cta_text = :showcase_cta_text,
            showcase_featured = :showcase_featured,
            showcase_status = :showcase_status
        WHERE id = :id
    ");

    $stmt->execute([
        'showcase_title' => $showcaseTitle,
        'showcase_summary' => $showcaseSummary,
        'showcase_features' => $showcaseFeatures,
        'showcase_cover_image' => $coverImage,
        'showcase_banner_image' => $bannerImage,
        'showcase_detail_url' => $detailUrl,
        'showcase_cta_text' => $ctaText,
        'showcase_featured' => isset($_POST['showcase_featured']) ? 1 : 0,
        'showcase_status' => $showcaseStatus,
        'id' => $baseId,
    ]);

    flash('success', 'Vitrine salva com sucesso.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect('/web/admin/bases/vitrine.php?id=' . $baseId);
